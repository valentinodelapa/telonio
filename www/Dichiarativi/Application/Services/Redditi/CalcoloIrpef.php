<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Calcoli dell'IRPEF dell'anno d'imposta 2025 secondo le specifiche
 * tecniche del modello Redditi PF 2026:
 * - imposta lorda per scaglioni (par. 19, art. 11 Tuir: 23% fino a
 *   28.000, 35% fino a 50.000, 43% oltre);
 * - detrazioni per familiari a carico (par. 20, art. 12 Tuir come
 *   modificato dalla L. 207/2024): coniuge, figli da 21 a 29 anni o con
 *   disabilità, altri familiari;
 * - detrazioni per lavoro dipendente, pensione e altri redditi (par. 21,
 *   art. 13 Tuir), non cumulabili;
 * - trattamento integrativo (par. 28, D.L. 3/2020), somma esente e
 *   ulteriore detrazione per il lavoro dipendente (par. 29, art. 1, cc. 4
 *   e 6, L. 207/2024).
 *
 * I quozienti usano le prime quattro cifre decimali (troncamento).
 */
class CalcoloIrpef
{

    public const SCAGLIONI = [[28000.0, 23.0], [50000.0, 35.0], [null, 43.0]];

    public static function impostaLorda(float $imponibile): float
    {
        return CalcoloAddizionali::perScaglioni(max(0.0, $imponibile), self::SCAGLIONI);
    }

    public static function quoziente(float $valore): float
    {
        return floor($valore * 10000) / 10000;
    }

    /**
     * Coniuge a carico (par. 20.1); maggiorazioni non rapportate ai mesi
     * (tabella delle specifiche, p. 143).
     */
    public static function detrazioneConiuge(float $reddito, int $mesi): float
    {
        if (($mesi <= 0) || ($reddito > 80000)) {
            return 0.0;
        }
        if ($reddito <= 15000) {
            $quoziente = self::quoziente($reddito / 15000);
            return $quoziente > 0 ? (800 - 110 * $quoziente) * $mesi / 12 : 0.0;
        }
        if ($reddito <= 40000) {
            $maggiorazione = match (true) {
                ($reddito > 29000) && ($reddito <= 29200) => 10,
                ($reddito > 29200) && ($reddito <= 34700) => 20,
                ($reddito > 34700) && ($reddito <= 35000) => 30,
                ($reddito > 35000) && ($reddito <= 35100) => 20,
                ($reddito > 35100) && ($reddito <= 35200) => 10,
                default => 0,
            };
            return 690 * $mesi / 12 + $maggiorazione;
        }
        return 690 * self::quoziente((80000 - $reddito) / 40000) * $mesi / 12;
    }

    /**
     * Figli da 21 a 29 anni o con disabilità (par. 20.2): 950 euro teorici
     * per mesi e percentuale, per (95.000 + 15.000 per ogni figlio dopo il
     * primo - reddito) / (95.000 + incremento).
     *
     * @param list<array{mesi: int, percentuale: float}> $figli righi del prospetto (mesi da 21 anni, percentuale 100, 50 o 0)
     */
    public static function detrazioneFigli(float $reddito, array $figli, int $numeroFigli): float
    {
        if (($numeroFigli === 0) || (count($figli) === 0)) {
            return 0.0;
        }
        $base = 95000 + ($numeroFigli - 1) * 15000;
        $quoziente = self::quoziente(($base - $reddito) / $base);
        if (($quoziente <= 0) || ($quoziente >= 1)) {
            return 0.0;
        }
        $teorica = array_sum(array_map(fn(array $figlio) => 950 * $figlio['mesi'] / 12 * $figlio['percentuale'] / 100, $figli));
        return $teorica * $quoziente;
    }

    /**
     * Altri familiari conviventi (ascendenti, par. 20.3): 750 euro teorici
     * per (80.000 - reddito) / 80.000.
     *
     * @param list<array{mesi: int, percentuale: float}> $familiari
     */
    public static function detrazioneAltriFamiliari(float $reddito, array $familiari): float
    {
        $quoziente = self::quoziente((80000 - $reddito) / 80000);
        if (($quoziente <= 0) || ($quoziente >= 1)) {
            return 0.0;
        }
        return array_sum(array_map(fn(array $familiare) => 750 * $familiare['mesi'] / 12 * $familiare['percentuale'] / 100, $familiari)) * $quoziente;
    }

    /**
     * Lavoro dipendente (par. 21.1).
     *
     * @return array{detrazione: float, rapportata: float, minima: float}
     */
    public static function detrazioneLavoroDipendente(float $reddito, int $giorni, bool $determinato): array
    {
        if ($reddito > 50000) {
            return ['detrazione' => 0.0, 'rapportata' => 0.0, 'minima' => 0.0];
        }
        if ($reddito <= 15000) {
            $rapportata = 1955 * $giorni / 365;
            $minima = $determinato ? 1380.0 : 690.0;
            return ['detrazione' => max($rapportata, $minima), 'rapportata' => $rapportata, 'minima' => $rapportata < $minima ? $minima : 0.0];
        }
        $maggiorazione = ($reddito > 25000) && ($reddito <= 35000) ? 65.0 : 0.0;
        $detrazione = $reddito <= 28000
                ? (1910 + 1190 * self::quoziente((28000 - $reddito) / 13000)) * $giorni / 365
                : 1910 * self::quoziente((50000 - $reddito) / 22000) * $giorni / 365;
        return ['detrazione' => $detrazione + $maggiorazione, 'rapportata' => $detrazione + $maggiorazione, 'minima' => 0.0];
    }

    /**
     * Pensione (par. 21.2).
     *
     * @return array{detrazione: float, rapportata: float, minima: float}
     */
    public static function detrazionePensione(float $reddito, int $giorni): array
    {
        if ($reddito > 50000) {
            return ['detrazione' => 0.0, 'rapportata' => 0.0, 'minima' => 0.0];
        }
        if ($reddito <= 8500) {
            $rapportata = 1955 * $giorni / 365;
            return ['detrazione' => max($rapportata, 713.0), 'rapportata' => $rapportata, 'minima' => $rapportata < 713 ? 713.0 : 0.0];
        }
        $maggiorazione = ($reddito > 25000) && ($reddito <= 29000) ? 50.0 : 0.0;
        $detrazione = $reddito <= 28000
                ? (700 + 1255 * self::quoziente((28000 - $reddito) / 19500)) * $giorni / 365
                : 700 * self::quoziente((50000 - $reddito) / 22000) * $giorni / 365;
        return ['detrazione' => $detrazione + $maggiorazione, 'rapportata' => $detrazione + $maggiorazione, 'minima' => 0.0];
    }

    /**
     * Redditi assimilati, lavoro autonomo, impresa minore e alcuni redditi
     * diversi (par. 21.4), non rapportata al periodo.
     */
    public static function detrazioneAltriRedditi(float $reddito, bool $maggiorazione = false): float
    {
        return match (true) {
            $reddito <= 5500 => 1265.0,
            $reddito <= 28000 => 500 + 765 * self::quoziente((28000 - $reddito) / 22500) + ($maggiorazione && ($reddito > 11000) && ($reddito <= 17000) ? 50 : 0),
            $reddito <= 50000 => 500 * self::quoziente((50000 - $reddito) / 22000),
            default => 0.0,
        };
    }

    /**
     * Detrazioni del rigo RN7 con lavoro dipendente e pensione insieme
     * (par. 21.3: minime non cumulabili, maggiorazioni non cumulabili) e
     * con altri redditi (par. 21.6: la più favorevole).
     *
     * @return array{1: float, 2: float, 4: float} colonne del rigo RN7
     */
    public static function detrazioniLavoro(float $reddito, int $giorniLavoro, bool $determinato, int $giorniPensione, bool $altriRedditi, bool $lavoro, bool $pensione): array
    {
        $dipendente = $lavoro && ($giorniLavoro > 0) ? self::detrazioneLavoroDipendente($reddito, $giorniLavoro, $determinato) : null;
        $pensionato = $pensione && ($giorniPensione > 0) ? self::detrazionePensione($reddito, $giorniPensione) : null;
        $colonne = [1 => $dipendente['detrazione'] ?? 0.0, 2 => $pensionato['detrazione'] ?? 0.0, 4 => 0.0];
        if (($dipendente !== null) && ($pensionato !== null) && (($dipendente['minima'] > 0) || ($pensionato['minima'] > 0))) {
            $minima = max($dipendente['minima'], $pensionato['minima']);
            $rapportate = $dipendente['rapportata'] + $pensionato['rapportata'];
            $colonne = $minima > $rapportate
                    ? ($dipendente['minima'] >= $pensionato['minima'] ? [1 => $minima, 2 => 0.0, 4 => 0.0] : [1 => 0.0, 2 => $minima, 4 => 0.0])
                    : [1 => $dipendente['rapportata'], 2 => $pensionato['rapportata'], 4 => 0.0];
        }
        if ($altriRedditi) {
            $altri = self::detrazioneAltriRedditi($reddito);
            if ($altri > $colonne[1] + $colonne[2]) {
                $colonne = [1 => 0.0, 2 => 0.0, 4 => $altri];
            }
        }
        return $colonne;
    }

    /**
     * Somma che non concorre al reddito (art. 1, c. 4, L. 207/2024): con
     * reddito di riferimento fino a 20.000 euro, una percentuale del
     * reddito di lavoro dipendente secondo il reddito rapportato all'anno.
     */
    public static function sommaEsente(float $redditoRiferimento, float $redditoLavoro, int $giorni): float
    {
        if (($redditoRiferimento > 20000) || ($redditoLavoro <= 0) || ($giorni <= 0)) {
            return 0.0;
        }
        $annuo = $redditoLavoro * 365 / $giorni;
        $percentuale = match (true) {
            $annuo <= 8500 => 7.1,
            $annuo <= 15000 => 5.3,
            default => 4.8,
        };
        return $redditoLavoro * $percentuale / 100;
    }

    /**
     * Ulteriore detrazione (art. 1, c. 6, L. 207/2024), rapportata al
     * periodo di lavoro.
     */
    public static function ulterioreDetrazione(float $redditoRiferimento, int $giorni): float
    {
        return match (true) {
            ($redditoRiferimento <= 20000) || ($redditoRiferimento > 40000) => 0.0,
            $redditoRiferimento <= 32000 => 1000 * $giorni / 365,
            default => 1000 * (40000 - $redditoRiferimento) / 8000 * $giorni / 365,
        };
    }

    /**
     * Trattamento integrativo spettante (par. 28): 1.200 euro per il
     * periodo di lavoro fino a 15.000 euro con capienza dell'imposta sui
     * redditi di lavoro rispetto alle detrazioni ridotte di 75 euro; tra
     * 15.000 e 28.000 la differenza (fino a 1.200) tra le detrazioni
     * indicate dalla norma e l'imposta lorda.
     *
     * @param float $detrazioniCapienza detrazioni per familiari, lavoro dipendente e rate fino al 2021 (par. 28.4.1)
     */
    public static function trattamentoIntegrativo(float $redditoRiferimento, float $redditiLavoro, int $giorni, bool $determinato, float $detrazioniCapienza, float $impostaLorda): float
    {
        if (($redditiLavoro <= 0) || ($giorni <= 0) || ($redditoRiferimento > 28000)) {
            return 0.0;
        }
        $impostaLavoro = self::impostaLorda($redditiLavoro);
        $detrazioneLavoro = self::detrazioneLavoroDipendente($redditiLavoro, $giorni, $determinato)['detrazione'];
        $riduzione = $redditoRiferimento <= 15000 ? 75 * $giorni / 365 : 0.0;
        if ($impostaLavoro - max(0.0, $detrazioneLavoro - $riduzione) <= 0) {
            return 0.0;
        }
        if ($redditoRiferimento <= 15000) {
            return 1200 * $giorni / 365;
        }
        $capienza = max(0.0, $detrazioniCapienza - $impostaLorda);
        return $capienza > 0 ? min($capienza, 1200) * $giorni / 365 : 0.0;
    }
}
