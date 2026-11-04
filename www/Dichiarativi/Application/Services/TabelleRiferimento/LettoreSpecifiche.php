<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

/**
 * Lettura dell'elenco dei campi non posizionali (campo-codice e
 * campo-valore dei record a struttura variabile) dalle specifiche tecniche
 * delle dichiarazioni dell'Agenzia delle Entrate, a partire dalle parole
 * del PDF con le loro coordinate ("pdftotext -tsv", poppler-utils).
 *
 * In ogni pagina le colonne della tabella si ricavano dalle intestazioni
 * "Descrizione", "Formato" e "Valori": il codice del campo (due lettere,
 * tre cifre del rigo, tre caratteri della colonna) sta a sinistra della
 * descrizione e il formato nella sua colonna. Le righe della descrizione
 * sono attribuite al codice più vicino in verticale, perché nel PDF la
 * descrizione su più righe è centrata sul codice; le righe che iniziano a
 * sinistra della descrizione senza un codice sono titoli di quadro o di
 * sezione e note a tutta pagina, quindi escluse (i titoli "Quadro XX - ..."
 * danno la denominazione dei quadri).
 *
 * Le descrizioni sono un ausilio per l'interfaccia; codici e formati sono
 * i dati vincolanti, verificati dal comando che aggiorna il catalogo.
 */
class LettoreSpecifiche
{

    public const ESPRESSIONE_CODICE = '/^[A-Z][A-Z0-9][0-9]{3}[0-9A-Z]{3}$/';
    public const ESPRESSIONE_FORMATO = '/^(AN|NU|NP|CB|CB12|CF|CN|PI|DA|DT|DN|D4|D6|PC|PN|PR|PE|QU|VP|VN|N[0-9]{1,2})$/';
    private const TOLLERANZA_RIGA = 2.0;

    /** @var array<string, array{descrizione: string, formato: ?string, pagina: int}> */
    private array $campi = [];

    /** @var array<string, string> */
    private array $quadri = [];

    /**
     * @param string $tsv uscita di "pdftotext -tsv" dell'intero documento
     * @return array<string, array{descrizione: string, formato: ?string, pagina: int}> codice => dati, nell'ordine del documento
     */
    public function leggi(string $tsv): array
    {
        $this->campi = [];
        $this->quadri = [];
        $colonne = null;
        foreach ($this->getPagine($tsv) as $numero => $parole) {
            $intestazione = $this->getColonne($parole);
            $colonne = $intestazione ?? ($colonne === null ? null : ['intestazione' => 0.0] + $colonne);
            if ($colonne !== null) {
                $this->leggiPagina($numero, $parole, $colonne);
            }
        }
        return $this->campi;
    }

    /**
     * Denominazioni dei quadri trovate nei titoli delle tabelle.
     *
     * @return array<string, string> sigla => denominazione
     */
    public function getQuadri(): array
    {
        return $this->quadri;
    }

    /**
     * @return array<int, list<array{x: float, y: float, fine: float, testo: string}>>
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
            if ($testo === '') {
                continue;
            }
            $pagine[(int) $campi[1]][] = ['x' => (float) $campi[6], 'y' => (float) $campi[7], 'fine' => (float) $campi[6] + (float) $campi[8], 'testo' => $testo];
        }
        return $pagine;
    }

    /**
     * Inizio della descrizione, del formato e dei valori ammessi
     * dall'intestazione della tabella, se presente nella pagina.
     *
     * @return array{descrizione: float, formato: float, valori: float, intestazione: float}|null
     */
    private function getColonne(array $parole): ?array
    {
        $trovate = [];
        $fineIntestazione = 0.0;
        foreach ($parole as $parola) {
            if (in_array($parola['testo'], ['Descrizione', 'Formato', 'Valori'], true) && (isset($trovate[$parola['testo']]) === false)) {
                $trovate[$parola['testo']] = $parola['x'];
                $fineIntestazione = max($fineIntestazione, $parola['y']);
            }
        }
        if (count($trovate) < 3) {
            return null;
        }
        $codici = array_filter($parole, fn(array $parola) => preg_match(self::ESPRESSIONE_CODICE, $parola['testo']) === 1 && $parola['x'] < $trovate['Descrizione']);
        $fineCodici = count($codici) > 0 ? max(array_column($codici, 'fine')) : $trovate['Descrizione'] - 1;
        return [
            'descrizione' => min($fineCodici + 1, $trovate['Descrizione']),
            'formato' => $trovate['Formato'] - 3,
            'valori' => $trovate['Valori'] - 4,
            'intestazione' => $fineIntestazione,
        ];
    }

    private function leggiPagina(int $numero, array $parole, array $colonne): void
    {
        $codici = [];
        foreach ($parole as $parola) {
            if (($parola['x'] < $colonne['descrizione']) && (preg_match(self::ESPRESSIONE_CODICE, $parola['testo']) === 1)) {
                $codici[] = $parola;
            }
        }
        if (count($codici) === 0) {
            return;
        }
        usort($codici, fn(array $a, array $b) => $a['y'] <=> $b['y']);
        $descrizioni = array_fill(0, count($codici), []);
        $formati = array_fill(0, count($codici), null);
        foreach ($this->getRighe($parole) as $riga) {
            $prima = $riga[0];
            if ($prima['y'] <= $colonne['intestazione'] + self::TOLLERANZA_RIGA) {
                continue;
            }
            $conCodice = preg_match(self::ESPRESSIONE_CODICE, $prima['testo']) === 1;
            if (($prima['x'] < $colonne['descrizione']) && ($conCodice === false)) {
                $this->leggiTitolo($riga);
                continue;
            }
            if (($conCodice === false) && self::isNota($riga, $colonne)) {
                continue;
            }
            $indice = self::getPiuVicino($codici, $prima['y']);
            foreach ($riga as $parola) {
                if (($parola['x'] >= $colonne['descrizione']) && ($parola['x'] < $colonne['formato'])) {
                    $descrizioni[$indice][] = $parola['testo'];
                } elseif (($parola['x'] >= $colonne['formato']) && ($parola['x'] < $colonne['valori']) && ($formati[$indice] === null) && (preg_match(self::ESPRESSIONE_FORMATO, $parola['testo']) === 1)) {
                    $formati[$indice] = $parola['testo'];
                }
            }
        }
        foreach ($codici as $indice => $codice) {
            // pagine con le colonne spostate rispetto all'ultima intestazione: il formato finisce nella descrizione
            if ($formati[$indice] === null) {
                foreach ($descrizioni[$indice] as $posizione => $parola) {
                    if (preg_match(self::ESPRESSIONE_FORMATO, $parola) === 1) {
                        $formati[$indice] = $parola;
                        unset($descrizioni[$indice][$posizione]);
                        break;
                    }
                }
            }
            $descrizione = self::normalizza(implode(' ', $descrizioni[$indice]));
            if (isset($this->campi[$codice['testo']])) {
                $this->campi[$codice['testo']]['formato'] ??= $formati[$indice];
                if ($this->campi[$codice['testo']]['descrizione'] === '') {
                    $this->campi[$codice['testo']]['descrizione'] = $descrizione;
                }
                continue;
            }
            $this->campi[$codice['testo']] = ['descrizione' => $descrizione, 'formato' => $formati[$indice], 'pagina' => $numero];
        }
    }

    /**
     * Parole raggruppate per riga (stessa ordinata, con tolleranza),
     * ciascuna ordinata da sinistra.
     *
     * @return list<list<array{x: float, y: float, fine: float, testo: string}>>
     */
    private function getRighe(array $parole): array
    {
        usort($parole, fn(array $a, array $b) => [$a['y'], $a['x']] <=> [$b['y'], $b['x']]);
        $righe = [];
        $corrente = [];
        $ordinata = null;
        foreach ($parole as $parola) {
            if (($ordinata !== null) && (abs($parola['y'] - $ordinata) > self::TOLLERANZA_RIGA)) {
                $righe[] = $corrente;
                $corrente = [];
            }
            if ($corrente === []) {
                $ordinata = $parola['y'];
            }
            $corrente[] = $parola;
        }
        if ($corrente !== []) {
            $righe[] = $corrente;
        }
        foreach ($righe as &$riga) {
            usort($riga, fn(array $a, array $b) => $a['x'] <=> $b['x']);
        }
        return $righe;
    }

    private function leggiTitolo(array $riga): void
    {
        $testo = implode(' ', array_column($riga, 'testo'));
        if (preg_match('/^QUADRO\s+([A-Z]{2})\s*[-–]\s*(.+)$/iu', $testo, $parti) === 1) {
            $this->quadri[strtoupper($parti[1])] ??= self::normalizza($parti[2]);
        }
    }

    /**
     * Riga di testo che attraversa la colonna del formato (nota a tutta
     * pagina), da non attribuire alla descrizione di un campo.
     */
    private static function isNota(array $riga, array $colonne): bool
    {
        foreach ($riga as $parola) {
            if (($parola['x'] >= $colonne['formato']) && ($parola['x'] < $colonne['valori']) && (preg_match(self::ESPRESSIONE_FORMATO, $parola['testo']) !== 1)) {
                return true;
            }
            if (($parola['x'] < $colonne['formato']) && ($parola['fine'] > $colonne['formato'] + 15)) {
                return true;
            }
        }
        return false;
    }

    private static function getPiuVicino(array $codici, float $ordinata): int
    {
        $migliore = 0;
        foreach ($codici as $indice => $codice) {
            if (abs($codice['y'] - $ordinata) < abs($codici[$migliore]['y'] - $ordinata)) {
                $migliore = $indice;
            }
        }
        return $migliore;
    }

    private static function normalizza(string $testo): string
    {
        $testo = preg_replace('/\s+/u', ' ', trim($testo));
        $testo = preg_replace('/(\p{L})- (\p{Ll})/u', '$1$2', $testo);
        return mb_substr($testo, 0, 500);
    }
}
