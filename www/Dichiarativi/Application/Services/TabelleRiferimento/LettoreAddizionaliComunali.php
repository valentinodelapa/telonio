<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

/**
 * Lettura dell'"Elenco delle aliquote per la determinazione
 * dell'addizionale comunale" pubblicato dall'Agenzia delle Entrate con i
 * modelli dichiarativi (saldo dell'anno d'imposta e acconto del successivo)
 * dalle parole del PDF con le coordinate ("pdftotext -tsv").
 *
 * Ogni comune inizia con il codice catastale nella prima colonna; le parole
 * fino al codice successivo (anche nella pagina seguente, per le celle
 * spezzate) appartengono al comune. Le colonne sono riconosciute per
 * ascissa: aliquota unica, aliquote per scaglione (quattro con la
 * tipologia 1: fino a 15.000, 28.000, 50.000 e oltre; tre con la
 * tipologia 2: fino a 28.000, 50.000 e oltre), soglia di esenzione,
 * tipizzazione e descrizione dei casi particolari.
 */
class LettoreAddizionaliComunali
{

    /** Inizio delle colonne (ascissa sinistra, punti PDF) */
    private const COLONNE = [
        'codice' => 0, 'comune' => 75, 'provincia' => 228, 'aliquotaUnica' => 288,
        'aliquota1' => 340, 'aliquota2' => 398, 'aliquota3' => 452, 'aliquota4' => 506,
        'tipologia' => 560, 'esenzione' => 640, 'tipizzazione' => 740, 'casiParticolari' => 830,
        'nuovaIstituzione' => 1240, 'nota' => 1340,
    ];

    /** Margine superiore dei dati (sotto l'intestazione della tabella) */
    private const INIZIO_DATI = 240.0;

    /**
     * @param string $tsv uscita di "pdftotext -tsv" dell'intero documento
     * @return array<string, array{aliquotaUnica: ?float, aliquote: float[], tipologia: ?int, esenzione: ?float, tipizzazione: ?string, casiParticolari: ?string, esenzioneCasi: ?float}> per codice catastale
     */
    public function leggi(string $tsv): array
    {
        $celle = [];
        $corrente = null;
        $ordinata = 0.0;
        $paginaCodice = 0;
        foreach ($this->getPagine($tsv) as $pagina => $parole) {
            // dopo l'ultimo comune: pagine con le note sulle tipologie e la legenda dei codici di tipizzazione
            if (($corrente !== null) && (count(array_filter($parole, fn(array $parola) => ($parola['x'] < self::COLONNE['comune']) && (preg_match('/^[A-Z]\d{3}$/', $parola['testo']) === 1))) === 0)) {
                break;
            }
            usort($parole, fn(array $a, array $b) => [$a['y'], $a['x']] <=> [$b['y'], $b['x']]);
            foreach ($parole as $parola) {
                if (($parola['x'] < self::COLONNE['comune']) && (preg_match('/^[A-Z]\d{3}$/', $parola['testo']) === 1)) {
                    $corrente = $parola['testo'];
                    $celle[$corrente] = [];
                    $ordinata = $parola['y'];
                    $paginaCodice = $pagina;
                    continue;
                }
                if (($corrente === null) || ($parola['y'] < self::INIZIO_DATI)) {
                    continue;
                }
                $colonna = self::getColonna($parola['x']);
                // denominazioni lunghe che sconfinano nelle colonne della provincia e dell'aliquota
                if (in_array($colonna, ['provincia', 'aliquotaUnica'], true) && (preg_match('/^[\p{L}\'().\/-]+$/u', $parola['testo']) === 1) && (strlen($parola['testo']) > 2 || $colonna === 'aliquotaUnica')) {
                    continue;
                }
                // valori fuori dalla riga del codice: soglie dei casi particolari
                if (!in_array($colonna, ['comune', 'tipizzazione', 'casiParticolari', 'nota'], true) && (($pagina !== $paginaCodice) || (abs($parola['y'] - $ordinata) > 4))) {
                    $colonna .= 'Casi';
                }
                $celle[$corrente][$colonna][] = $parola;
            }
        }
        $comuni = [];
        foreach ($celle as $codice => $colonne) {
            $comuni[$codice] = $this->leggiComune($codice, $colonne);
        }
        return $comuni;
    }

    private function leggiComune(string $codice, array $colonne): array
    {
        $numero = function (string $colonna) use ($codice, $colonne): ?float {
            $testo = self::testo($colonne[$colonna] ?? []);
            if ($testo === '') {
                return null;
            }
            $testo = str_replace(',', '.', $testo);
            if (str_starts_with($colonna, 'esenzione') && (preg_match('/^\d{1,3}(\.\d{3})+$/', $testo) === 1)) {
                $testo = str_replace('.', '', $testo);
            }
            if (is_numeric($testo) === false) {
                throw new \UnexpectedValueException(sprintf('Comune %s: valore "%s" non numerico nella colonna %s', $codice, $testo, $colonna));
            }
            return (float) $testo;
        };
        $aliquote = array_values(array_filter([$numero('aliquota1'), $numero('aliquota2'), $numero('aliquota3'), $numero('aliquota4')], fn(?float $aliquota) => $aliquota !== null));
        $tipologia = $numero('tipologia');
        $unica = $numero('aliquotaUnica');
        if (($unica === null) && (count($aliquote) === 0)) {
            throw new \UnexpectedValueException('Comune ' . $codice . ': nessuna aliquota');
        }
        if ((count($aliquote) > 0) && (count($aliquote) !== ($tipologia === 2.0 ? 3 : 4))) {
            throw new \UnexpectedValueException(sprintf('Comune %s: %d aliquote per scaglione con la tipologia %s', $codice, count($aliquote), $tipologia ?? '-'));
        }
        $tipizzazione = self::testo($colonne['tipizzazione'] ?? []);
        $casi = self::testo($colonne['casiParticolari'] ?? []);
        return [
            'aliquotaUnica' => count($aliquote) > 0 ? null : $unica,
            'aliquote' => $aliquote,
            'tipologia' => count($aliquote) > 0 ? (int) ($tipologia ?? 1) : null,
            'esenzione' => $numero('esenzione'),
            'tipizzazione' => $tipizzazione === '' ? null : $tipizzazione,
            'casiParticolari' => $casi === '' ? null : $casi,
            'esenzioneCasi' => $numero('esenzioneCasi'),
        ];
    }

    private static function getColonna(float $x): string
    {
        $trovata = 'codice';
        foreach (self::COLONNE as $colonna => $inizio) {
            if ($x >= $inizio) {
                $trovata = $colonna;
            }
        }
        return $trovata;
    }

    /**
     * Testo della cella: parole per riga (stessa ordinata) e righe in
     * ordine.
     */
    private static function testo(array $parole): string
    {
        $righe = [];
        foreach ($parole as $parola) {
            $righe[(string) round($parola['y'])][] = $parola;
        }
        ksort($righe, SORT_NUMERIC);
        $testo = [];
        foreach ($righe as $riga) {
            usort($riga, fn(array $a, array $b) => $a['x'] <=> $b['x']);
            $testo[] = implode(' ', array_column($riga, 'testo'));
        }
        return trim(preg_replace('/\s+/u', ' ', implode(' ', $testo)));
    }

    /**
     * @return array<int, list<array{x: float, y: float, testo: string}>>
     */
    private function getPagine(string $tsv): array
    {
        $pagine = [];
        foreach (preg_split('/\R/', $tsv) as $riga) {
            $campi = explode("\t", $riga);
            if ((count($campi) < 12) || ($campi[0] !== '5')) {
                continue;
            }
            $testo = trim($campi[11]);
            if ($testo !== '') {
                $pagine[(int) $campi[1]][] = ['x' => (float) $campi[6], 'y' => (float) $campi[7], 'testo' => $testo];
            }
        }
        ksort($pagine);
        return $pagine;
    }
}
