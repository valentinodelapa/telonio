<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Services\QuadriDichiarazione;

/**
 * Credito per le imposte pagate all'estero (art. 165, comma 1, Tuir),
 * quadro CE sezione I, con le regole del paragrafo 38 delle specifiche
 * tecniche del modello Redditi PF 2026:
 *
 * - righi da CE1 a CE3 di tutti i moduli (proposti o inseriti a mano): per
 *   l'anno della dichiarazione reddito complessivo (RN1 col. 2 + col. 5 -
 *   col. 3), imposta lorda (RN5) e netta (RN26); quota di imposta lorda
 *   (col. 10) = reddito estero / reddito complessivo (al massimo 1) per
 *   l'imposta lorda; credito (col. 11) = minore tra l'imposta estera e la
 *   quota meno il credito già usato per lo Stato (col. 9); col. 13 per il
 *   credito indiretto (col. 15);
 * - righi CE4 e CE5, uno per anno di produzione: totale delle col. 11 o
 *   13, capienza nell'imposta netta (col. 7 - col. 8) e credito usato,
 *   il minore dei due;
 * - righi CE23 e CE26 (con CE24 e CE25 a mano) e credito del rigo RN29,
 *   col. 2.
 *
 * L'imposta estera oltre il 43 per cento del reddito estero (aliquota
 * marginale più elevata) è segnalata.
 *
 * Sezione II (imprese, art. 165, comma 6; paragrafi 38.2.1-38.2.3), sui
 * righi da CE6 a CE8 inseriti a mano: eccedenze di imposta nazionale ed
 * estera (col. 8, 9, 15, 16 e 17), riepilogo per Stato (righi CE9 e CE10:
 * credito, il minore tra le due eccedenze), prospetto degli otto periodi
 * precedenti per Stato (righi da CE11 a CE16 e da CE17 a CE22) con le
 * eccedenze residue della dichiarazione dell'anno prima (righi CE14 e CE15,
 * ogni anno scalate di una colonna), credito dell'anno e consumo delle
 * eccedenze dalla più vecchia; righi CE24 e CE25.
 */
final class CalcoloCreditoEstero
{

    public const ALIQUOTA_MASSIMA = 0.43;

    /** Righi di intestazione dei due prospetti della sezione II-C */
    private const PROSPETTI = [11, 17];

    /**
     * @return string[] avvisi
     */
    public static function proponi(QuadriDichiarazione $quadri, int $anno, ?QuadriDichiarazione $precedenti = null): array
    {
        $avvisi = [];
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $perAnno = [];
        foreach ($quadri->getModuli() as $modulo) {
            for ($rigo = 1; $rigo <= 3; $rigo++) {
                $c = fn(int $colonna) => sprintf('CE%03d%03d', $rigo, $colonna);
                if ($quadri->get($c(1), $modulo) === null) {
                    continue;
                }
                $quadri->usaModulo($modulo);
                $annoReddito = $quadri->getImporto($c(2), $modulo);
                if ($annoReddito === $anno) {
                    $quadri->proponi($c(5), ($q('RN001002') + $q('RN001005') - $q('RN001003')) ?: null);
                    $quadri->proponi($c(6), $q('RN005001') ?: null);
                    $quadri->proponi($c(7), $q('RN026001') ?: null);
                }
                $reddito = $quadri->getImporto($c(3), $modulo);
                $imposta = $quadri->getImporto($c(4), $modulo);
                $complessivo = $quadri->getImporto($c(5), $modulo);
                if ($imposta > round($reddito * self::ALIQUOTA_MASSIMA)) {
                    $avvisi[] = 'Quadro CE: imposta estera oltre il 43 per cento del reddito estero (rigo CE' . $rigo . ', modulo ' . $modulo . '): non può superare l\'imposta italiana all\'aliquota marginale più elevata.';
                }
                $rapporto = $complessivo > 0 ? min(1.0, $reddito / $complessivo) : 0.0;
                $quota = (int) round($rapporto * $quadri->getImporto($c(6), $modulo));
                $quadri->proponi($c(10), $quota ?: null);
                $credito = min($imposta, max(0, $quota - $quadri->getImporto($c(9), $modulo)));
                $quadri->proponi($c(11), $credito ?: null);
                $indiretto = $quadri->get($c(15), $modulo) !== null;
                if ($indiretto) {
                    $quadri->proponi($c(13), max(0, $credito - $quadri->getImporto($c(12), $modulo)) ?: null);
                }
                $perAnno[$annoReddito] ??= ['credito' => 0, 'capienza' => $quadri->getImporto($c(7), $modulo) - $quadri->getImporto($c(8), $modulo)];
                $perAnno[$annoReddito]['credito'] += $indiretto ? $quadri->getImporto($c(13), $modulo) : $credito;
            }
        }
        $quadri->usaModulo(1);
        if (count($perAnno) > 2) {
            $avvisi[] = 'Quadro CE: redditi esteri di più di due anni, i righi CE4 e CE5 degli anni oltre il secondo vanno nei moduli successivi, a mano.';
        }
        ksort($perAnno);
        $usato = 0;
        $numero = 4;
        foreach (array_slice($perAnno, 0, 2, true) as $annoReddito => $dati) {
            $c = fn(int $colonna) => sprintf('CE%03d%03d', $numero, $colonna);
            $quadri->proponi($c(1), $annoReddito);
            $quadri->proponi($c(2), $dati['credito'] ?: null);
            $quadri->proponi($c(3), max(0, $dati['capienza']) ?: null);
            $credito = max(0, min($dati['credito'], $dati['capienza']));
            $quadri->proponi($c(4), $credito ?: null);
            $usato += $credito;
            $numero++;
        }
        $quadri->proponi('CE023001', $usato ?: null);
        self::proponiSezioneII($quadri, $precedenti);
        $totale = $q('CE023001') + $q('CE024001') - $q('CE025001');
        $quadri->proponi('CE026001', $totale > 0 ? $totale : null);
        $estero = $q('CE026001') + $q('RF102003') + $q('RG037003') + $q('RH021001') + $q('RL004004') - $q('LM040002');
        $quadri->proponi('RN029002', $estero > 0 ? $estero : null);
        return $avvisi;
    }

    /**
     * Sezione II del quadro CE (righi da CE6 a CE22 e righi CE24 e CE25).
     */
    public static function proponiSezioneII(QuadriDichiarazione $quadri, ?QuadriDichiarazione $precedenti = null): void
    {
        $moduli = $quadri->getModuli();
        $perStato = [];
        $giaUtilizzato = 0;
        // sezione II-A: righi da CE6 a CE8
        foreach ($moduli as $modulo) {
            $quadri->usaModulo($modulo);
            for ($rigo = 6; $rigo <= 8; $rigo++) {
                $c = fn(int $colonna) => sprintf('CE%03d%03d', $rigo, $colonna);
                $v = fn(int $colonna) => $quadri->getImporto($c($colonna), $modulo);
                if ($quadri->get($c(1), $modulo) === null) {
                    continue;
                }
                [$nazionale, $estera] = self::eccedenze($v(3), $v(4), $v(5), $v(6), $v(7));
                $quadri->proponi($c(8), $nazionale ?: null);
                $quadri->proponi($c(9), $estera ?: null);
                $nazionale = $v(8);
                $estera = $v(9);
                $precedentiCompilati = false;
                for ($colonna = 10; $colonna <= 14; $colonna++) {
                    $precedentiCompilati = $precedentiCompilati || ($quadri->get($c($colonna), $modulo) !== null);
                }
                if ($precedentiCompilati) {
                    [$residua, $utilizzato] = self::residuoNazionale($nazionale, $quadri->get($c(11), $modulo) !== null ? $v(11) : null, $v(12));
                    $esteraResidua = $estera - $v(14);
                    if ($esteraResidua < 0) {
                        $utilizzato += -$esteraResidua;
                        $esteraResidua = 0;
                    }
                    $quadri->proponi($c(15), $residua ?: null);
                    $quadri->proponi($c(16), $esteraResidua ?: null);
                    $quadri->proponi($c(17), $utilizzato ?: null);
                    $nazionale = $v(15);
                    $estera = $v(16);
                }
                $giaUtilizzato += $v(17);
                $stato = (string) $quadri->get($c(1), $modulo);
                $perStato[$stato] ??= ['nazionale' => 0, 'estera' => 0];
                $perStato[$stato]['nazionale'] += $nazionale;
                $perStato[$stato]['estera'] += $estera;
            }
        }
        // sezione II-B: righi CE9 e CE10, uno per Stato
        $residui = [];
        $credito = 0;
        foreach (array_values(array_keys($perStato)) as $indice => $stato) {
            $quadri->usaModulo(intdiv($indice, 2) + 1);
            $c = fn(int $colonna) => sprintf('CE%03d%03d', 9 + $indice % 2, $colonna);
            ['nazionale' => $nazionale, 'estera' => $estera] = $perStato[$stato];
            $usato = (($nazionale > 0) && ($estera > 0)) ? min($nazionale, $estera) : 0;
            $quadri->proponi($c(1), $stato);
            $quadri->proponi($c(2), $nazionale ?: null);
            $quadri->proponi($c(3), $estera ?: null);
            $quadri->proponi($c(4), $usato ?: null);
            $quadri->proponi($c(5), ($nazionale - $usato) ?: null);
            $quadri->proponi($c(6), ($estera - $usato) ?: null);
            $credito += $usato;
            $residui[$stato] = [$nazionale - $usato, $estera - $usato];
        }
        // sezione II-C: un prospetto per Stato con le eccedenze residue dell'anno o dell'anno prima
        $riporti = $precedenti === null ? [] : self::leggiProspetti($precedenti);
        $prospetti = self::leggiProspetti($quadri);
        $posizioni = array_map(fn(array $prospetto) => $prospetto['posizione'], $prospetti);
        $liberi = [];
        foreach ($moduli as $modulo) {
            foreach (self::PROSPETTI as $intestazione) {
                if (in_array([$modulo, $intestazione], $posizioni, true) === false) {
                    $liberi[] = [$modulo, $intestazione];
                }
            }
        }
        foreach (array_unique(array_merge(array_keys(array_filter($residui, fn(array $residuo) => ($residuo[0] !== 0) || ($residuo[1] !== 0))), array_keys($riporti))) as $stato) {
            if (isset($prospetti[$stato]) || (count($liberi) === 0)) {
                continue;
            }
            [$modulo, $intestazione] = array_shift($liberi);
            $quadri->usaModulo($modulo);
            $quadri->proponi(sprintf('CE%03d001', $intestazione), (string) $stato);
            $prospetti[$stato] = ['posizione' => [$modulo, $intestazione], 'branch' => false, 'casi' => false];
        }
        foreach ($prospetti as $stato => $prospetto) {
            [$modulo, $intestazione] = $prospetto['posizione'];
            $quadri->usaModulo($modulo);
            $r = fn(int $scarto, int $colonna) => sprintf('CE%03d%03d', $intestazione + $scarto, $colonna);
            $v = fn(int $scarto, int $colonna) => $quadri->getImporto($r($scarto, $colonna), $modulo);
            // colonne da 1 a 8: residui della dichiarazione dell'anno prima, scalati di un periodo
            foreach ($riporti[$stato]['residui'] ?? [] as $scarto => $colonne) {
                for ($colonna = 1; $colonna <= 8; $colonna++) {
                    $quadri->proponi($r($scarto - 2, $colonna), ($colonne[$colonna + 1] ?? 0) ?: null);
                }
            }
            if ($prospetto['branch']) {
                $quadri->proponi($r(1, 9), null);
            } elseif ($prospetto['casi'] === false) {
                $quadri->proponi($r(1, 9), ($residui[$stato][0] ?? 0) ?: null);
                $quadri->proponi($r(2, 9), ($residui[$stato][1] ?? 0) ?: null);
            }
            $nazionali = $esteri = [];
            for ($colonna = 1; $colonna <= 9; $colonna++) {
                $nazionali[$colonna] = $v(1, $colonna);
                $esteri[$colonna] = $v(2, $colonna);
            }
            $totaleNazionale = array_sum($nazionali);
            $totaleEstera = array_sum($esteri);
            $quadri->proponi($r(5, 1), $totaleNazionale ?: null);
            $quadri->proponi($r(5, 2), $totaleEstera ?: null);
            if ($prospetto['branch']) {
                $utilizzabile = min($totaleEstera, $v(5, 5));
                $riferimento = 0;
            } else {
                $utilizzabile = (($totaleNazionale > 0) && ($totaleEstera > 0)) ? min($totaleNazionale, $totaleEstera) : 0;
                $riferimento = $utilizzabile + array_sum(array_map(fn(int $importo) => $importo < 0 ? -$importo : 0, $nazionali));
            }
            $quadri->proponi($r(5, 3), $utilizzabile ?: null);
            $quadri->proponi($r(5, 4), $riferimento ?: null);
            // residui (righi CE14 e CE15): le eccedenze si consumano dalla più vecchia
            $residuiNazionali = self::consuma($nazionali, $v(5, 3) === 0 ? null : $v(5, 4), true);
            $residuiEsteri = self::consuma($esteri, $v(5, 3) === 0 ? null : $v(5, 3), false);
            for ($colonna = 2; $colonna <= 9; $colonna++) {
                $quadri->proponi($r(3, $colonna), $residuiNazionali[$colonna] ?: null);
                $quadri->proponi($r(4, $colonna), $residuiEsteri[$colonna] ?: null);
            }
            $credito += $v(5, 3);
        }
        $quadri->usaModulo(1);
        $quadri->proponi('CE024001', $credito ?: null);
        $quadri->proponi('CE025001', $giaUtilizzato ?: null);
    }

    /**
     * Eccedenze di imposta nazionale (col. 8) ed estera (col. 9) del rigo
     * della sezione II-A (paragrafo 38.2.1).
     *
     * @return array{0: int, 1: int}
     */
    public static function eccedenze(int $reddito, int $imposta, int $complessivo, int $eccedenteNetta, int $lorda): array
    {
        if ($reddito >= 0) {
            $quota = (int) round(($complessivo > 0 ? min(1.0, $reddito / $complessivo) : 0.0) * $lorda);
            return [max(0, $quota - $imposta), max(0, $imposta - $quota) + $eccedenteNetta];
        }
        if ($complessivo > 0) {
            return [(int) round($reddito / $complessivo * $lorda), $imposta];
        }
        // perdita estera e reddito complessivo non positivo: al più il 43 per cento della perdita eccedente
        return [abs($complessivo) >= abs($reddito) ? 0 : -(int) round((abs($reddito) - abs($complessivo)) * self::ALIQUOTA_MASSIMA), $imposta];
    }

    /**
     * Eccedenza di imposta nazionale residua (col. 15) e credito già
     * utilizzato (col. 17) del rigo della sezione II-A.
     *
     * @return array{0: ?int, 1: int}
     */
    private static function residuoNazionale(int $eccedenza, ?int $negativa, int $utilizzato): array
    {
        if ($negativa === null) {
            if ($eccedenza < 0) {
                return [$eccedenza, 0];
            }
            $residuo = $eccedenza - $utilizzato;
            return $residuo >= 0 ? [$residuo, 0] : [null, -$residuo];
        }
        $capienza = $eccedenza - $negativa;
        if ($capienza <= 0) {
            return [0, $utilizzato];
        }
        $limite = $capienza - $utilizzato;
        return $limite > 0 ? [$limite, 0] : [0, -$limite];
    }

    /**
     * Residui delle colonne da 2 a 9 dei righi CE14 e CE15 dopo l'utilizzo
     * dell'importo dalla colonna più vecchia (colonna 1 per prima).
     *
     * @param array<int, int> $colonne
     * @return array<int, int>
     */
    private static function consuma(array $colonne, ?int $importo, bool $soloPositive): array
    {
        if ($importo === null) {
            return $colonne;
        }
        $residui = [];
        foreach ($colonne as $colonna => $valore) {
            // eccedenze nazionali negative: non consumano e non si riportano (istruzioni del rigo CE14)
            if ($soloPositive && ($valore <= 0)) {
                $residui[$colonna] = 0;
                continue;
            }
            $differenza = $valore - $importo;
            $residui[$colonna] = max(0, $differenza);
            $importo = $differenza >= 0 ? 0 : -$differenza;
        }
        return $residui;
    }

    /**
     * Prospetti della sezione II-C compilati, per Stato, con i residui
     * (righi CE14 e CE15 o CE20 e CE21).
     *
     * @return array<string, array{posizione: array{0: int, 1: int}, branch: bool, casi: bool, residui: array<int, array<int, int>>}>
     */
    private static function leggiProspetti(QuadriDichiarazione $quadri): array
    {
        $prospetti = [];
        foreach ($quadri->getModuli() as $modulo) {
            foreach (self::PROSPETTI as $intestazione) {
                $stato = $quadri->get(sprintf('CE%03d001', $intestazione), $modulo);
                if ($stato === null) {
                    continue;
                }
                $residui = [];
                foreach ([3, 4] as $scarto) {
                    for ($colonna = 2; $colonna <= 9; $colonna++) {
                        $residui[$scarto][$colonna] = $quadri->getImporto(sprintf('CE%03d%03d', $intestazione + $scarto, $colonna), $modulo);
                    }
                }
                $prospetti[(string) $stato] = ['posizione' => [$modulo, $intestazione],
                    'branch' => $quadri->get(sprintf('CE%03d002', $intestazione), $modulo) !== null,
                    'casi' => $quadri->get(sprintf('CE%03d000', $intestazione), $modulo) !== null,
                    'residui' => $residui];
            }
        }
        return $prospetti;
    }
}
