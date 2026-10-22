<?php

namespace Imu\Application\Services\TabelleRiferimento;

use Imu\Application\Enumerations\FattispecieImu;

/**
 * Lettura del prospetto delle aliquote IMU pubblicato dal Dipartimento
 * delle Finanze (D.M. 7 luglio 2023 e successivi, allegato A), dal testo
 * estratto dal PDF con "pdftotext -layout".
 *
 * Il prospetto ha: intestazione (comune, ID e anno, delibera), le voci di
 * base con l'aliquota allineata a destra, le aliquote differenziate su tre
 * colonne (fattispecie di base, condizioni, aliquota), l'elenco delle
 * esenzioni del comune e le precisazioni. Le voci di base si leggono per
 * etichetta; delle differenziate si ricavano fattispecie, condizioni (come
 * testo) e aliquota: l'impaginazione del PDF può spezzare le colonne, per
 * questo la descrizione va confrontata con il PDF prima di applicarla.
 *
 * Se l'intestazione o le voci di base non sono riconosciute la lettura si
 * ferma (tracciato cambiato).
 */
class LettoreProspetto
{

    private const VOCI_MINIME = 4;
    private const MARGINE_MASSIMO = 4;
    private const ETICHETTE_DIFFERENZIATE = [
        'AP' => 'Abitazione principale di categoria catastale A/1, A/8 e A/9 e relative pertinenze',
        'RS' => 'Fabbricati rurali ad uso strumentale (inclusa la categoria catastale D/10)',
        'GD' => 'Fabbricati appartenenti al gruppo catastale D',
        'TA' => 'Terreni agricoli',
        'AF' => 'Aree fabbricabili',
        'AL' => 'Altri fabbricati (fabbricati diversi dall\'abitazione principale e dai fabbricati appartenenti al gruppo catastale D)',
        'BM' => 'Fabbricati costruiti e destinati dall\'impresa costruttrice alla vendita',
    ];

    /**
     * @return array{comune: string, idProspetto: int, anno: int, numeroDelibera: ?string, dataDelibera: ?string,
     *         aliquote: array<string, float>, assimilazioneAnziani: bool,
     *         differenziate: array<int, array{fattispecie: ?string, descrizione: string, aliquota: float}>, esenzioni: ?string}
     */
    public function leggi(string $testo): array
    {
        $testo = str_replace(["\f", "\r"], ["\n", ''], $testo);
        if ((preg_match('/Prospetto aliquote IMU - Comune di (.+)/u', $testo, $comune) !== 1)
                || (preg_match('/ID Prospetto (\d+) riferito all\'anno (\d{4})/u', $testo, $intestazione) !== 1)) {
            throw new \RuntimeException('Intestazione del prospetto non riconosciuta: il tracciato è cambiato.');
        }
        preg_match('~delibera n\S*\s+(\S+)\s+del\s+(\d{2})/(\d{2})/(\d{4})~u', $testo, $delibera);
        $righe = explode("\n", $testo);
        [$inizio, $fine, $esenzioni] = $this->delimita($righe);
        $prospetto = [
            'comune' => trim($comune[1]),
            'idProspetto' => (int) $intestazione[1],
            'anno' => (int) $intestazione[2],
            'numeroDelibera' => $delibera[1] ?? null,
            'dataDelibera' => isset($delibera[4]) ? $delibera[4] . '-' . $delibera[3] . '-' . $delibera[2] : null,
            'aliquote' => [],
            'assimilazioneAnziani' => false,
            'differenziate' => [],
            'esenzioni' => $esenzioni,
        ];
        $this->leggiVoci(array_slice($righe, $inizio, $fine - $inizio), $prospetto);
        if (count($prospetto['aliquote']) < self::VOCI_MINIME) {
            throw new \RuntimeException('Voci di base del prospetto non riconosciute (' . count($prospetto['aliquote']) . '): il tracciato è cambiato.');
        }
        return $prospetto;
    }

    /**
     * Righe delle aliquote (dopo l'intestazione, prima delle esenzioni) e
     * testo delle esenzioni.
     *
     * @return array{0: int, 1: int, 2: ?string}
     */
    private function delimita(array $righe): array
    {
        $inizio = 0;
        $fine = count($righe);
        $finePrecisazioni = null;
        $inizioEsenzioni = null;
        foreach ($righe as $indice => $riga) {
            if (str_contains($riga, 'Comune oggetto di fusione')) {
                $inizio = $indice + 1;
            } elseif (str_starts_with(trim($riga), 'Elenco esenzioni')) {
                $inizioEsenzioni = $indice + 1;
                $fine = min($fine, $indice);
            } elseif (str_starts_with(trim($riga), 'Precisazioni') || str_starts_with(trim($riga), 'Documento generato')) {
                $fine = min($fine, $indice);
                $finePrecisazioni ??= $indice;
            }
        }
        $esenzioni = null;
        if ($inizioEsenzioni !== null) {
            $testo = [];
            foreach (array_slice($righe, $inizioEsenzioni, ($finePrecisazioni ?? count($righe)) - $inizioEsenzioni) as $riga) {
                if (self::isRigaVuota($riga) === false) {
                    $testo[] = trim($riga);
                }
            }
            $esenzioni = count($testo) > 0 ? implode("\n", $testo) : null;
        }
        return [$inizio, $fine, $esenzioni];
    }

    private static function isRigaVuota(string $riga): bool
    {
        return (trim($riga) === '') || (preg_match('/^\s*Pagina \d+ di \d+\s*$/', $riga) === 1);
    }

    /**
     * Righe della colonna delle fattispecie: il margine sinistro dipende
     * dalla pagina e dalla versione di pdftotext (da 0 a qualche spazio),
     * la colonna delle condizioni inizia molto più a destra.
     */
    private static function isColonnaSinistra(string $riga): bool
    {
        return (strlen($riga) - strlen(ltrim($riga, ' '))) <= self::MARGINE_MASSIMO;
    }

    private function leggiVoci(array $righe, array &$prospetto): void
    {
        $corrente = null;
        $etichettaResidua = [];
        $fattispecieCorrente = null;
        foreach ($righe as $riga) {
            $riga = rtrim($riga);
            if (self::isRigaVuota($riga)) {
                continue;
            }
            if (str_starts_with(ltrim($riga), 'Assimilazione all')) {
                $prospetto['assimilazioneAnziani'] = preg_match('/\sSI$/', $riga) === 1;
                $corrente = null;
                continue;
            }
            $aliquota = null;
            if (preg_match('/^(.*\S)?\s+(\d{1,2}(?:,\d+)?)%$/u', $riga, $parti) === 1) {
                $aliquota = (float) str_replace(',', '.', $parti[2]);
                $riga = $parti[1] ?? '';
            }
            if (($aliquota !== null) && ($voce = self::getVoceBase($riga)) !== null) {
                $prospetto['aliquote'][$voce] = $aliquota;
                $corrente = null;
                continue;
            }
            if ($aliquota === null) {
                if ($corrente !== null) {
                    $prospetto['differenziate'][$corrente]['descrizione'] .= "\n" . self::consumaEtichetta($riga, $etichettaResidua);
                }
                continue;
            }
            $inizioRiga = self::isColonnaSinistra($riga);
            if ($inizioRiga && (self::iniziaCon($riga, $etichettaResidua) === false)) {
                $fattispecieCorrente = self::getFattispecieDifferenziata($riga);
                $etichettaResidua = $fattispecieCorrente === null ? [] : preg_split('/\s+/u', self::ETICHETTE_DIFFERENZIATE[$fattispecieCorrente]);
            } elseif ($inizioRiga === false) {
                $fattispecieCorrente = null;
                $etichettaResidua = [];
            }
            $prospetto['differenziate'][] = ['fattispecie' => $fattispecieCorrente, 'descrizione' => self::consumaEtichetta($riga, $etichettaResidua), 'aliquota' => $aliquota];
            $corrente = array_key_last($prospetto['differenziate']);
        }
        foreach ($prospetto['differenziate'] as &$differenziata) {
            $differenziata['descrizione'] = trim(preg_replace(['/[ \t]+/u', '/\n+/u'], [' ', "\n"], $differenziata['descrizione']));
        }
    }

    /**
     * Voce di base: etichetta della fattispecie su una sola colonna.
     */
    private static function getVoceBase(string $riga): ?string
    {
        if ((self::isColonnaSinistra($riga) === false) || (preg_match('/\S\s{2,}\S/u', trim($riga)) === 1)) {
            return null;
        }
        foreach (FattispecieImu::cases() as $fattispecie) {
            $voce = self::normalizza($fattispecie->getVoceProspetto());
            $testo = self::normalizza($riga);
            if (($testo !== '') && (str_starts_with($voce, $testo) || str_starts_with($testo, $voce)) && (mb_strlen($testo) >= min(mb_strlen($voce), 15))) {
                return $fattispecie->value;
            }
        }
        return null;
    }

    private static function getFattispecieDifferenziata(string $riga): ?string
    {
        $testo = self::normalizza($riga);
        foreach (self::ETICHETTE_DIFFERENZIATE as $fattispecie => $etichetta) {
            $inizio = mb_substr(self::normalizza($etichetta), 0, 15);
            if (str_starts_with($testo, $inizio)) {
                return $fattispecie;
            }
        }
        return null;
    }

    private static function iniziaCon(string $riga, array $etichettaResidua): bool
    {
        $parole = preg_split('/\s+/u', trim($riga));
        return (count($etichettaResidua) > 0) && (mb_strtolower($parole[0]) === mb_strtolower($etichettaResidua[0]));
    }

    /**
     * Toglie dall'inizio della riga le parole dell'etichetta della
     * fattispecie (colonna di sinistra) e restituisce le condizioni.
     */
    private static function consumaEtichetta(string $riga, array &$etichettaResidua): string
    {
        if (self::isColonnaSinistra($riga) === false) {
            return trim($riga);
        }
        $parole = preg_split('/(\s+)/u', trim($riga), -1, PREG_SPLIT_DELIM_CAPTURE);
        $consumate = 0;
        while ((count($etichettaResidua) > 0) && (count($parole) > 0) && (mb_strtolower($parole[0]) === mb_strtolower($etichettaResidua[0]))) {
            array_shift($etichettaResidua);
            array_shift($parole);
            array_shift($parole);
            $consumate++;
        }
        return trim(implode('', $parole));
    }

    private static function normalizza(string $testo): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}\/]+/u', '', str_replace(['’', '‘'], "'", $testo)));
    }
}
