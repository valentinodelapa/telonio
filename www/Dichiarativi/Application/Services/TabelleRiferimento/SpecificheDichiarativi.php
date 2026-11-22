<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

/**
 * Aggiornamento delle sorgenti del catalogo dei campi dalle specifiche
 * tecniche ufficiali: per ogni modello e anno elencato in
 * Database/sorgenti/specifiche.csv (con l'indirizzo del PDF pubblicato
 * dall'Agenzia delle Entrate e il provvedimento) scarica il PDF, ne estrae
 * le parole con le coordinate ("pdftotext -tsv", poppler-utils), legge
 * l'elenco dei campi (LettoreSpecifiche) e riscrive campi-<modello>-<anno>.csv
 * e quadri-<modello>-<anno>.csv. Le sorgenti si importano poi con
 * TabelleDichiarativi ("sisma tabelle-dichiarativi").
 *
 * Le specifiche degli ISA sono un archivio 7z con un file Excel per ISA
 * (estratto con bsdtar, libarchive-tools, e convertito con xls2csv,
 * catdoc): se ne leggono i campi (LettoreSpecificheIsa) e dalle istruzioni
 * "Parte generale" (colonna "istruzioni" di specifiche.csv) l'elenco degli
 * ISA con le attività; si riscrivono campi-ISA-<anno>.csv e isa-<anno>.csv
 * (le denominazioni dei quadri, comuni a tutti gli ISA, sono in
 * quadri-ISA-<anno>.csv).
 *
 * Se le specifiche non hanno il titolo di un quadro del catalogo (Redditi SP
 * 2026), la denominazione viene dall'indice delle istruzioni del modello
 * (colonna "istruzioni" di specifiche.csv: righe "N. QUADRO XX - TITOLO").
 *
 * @see https://www.agenziaentrate.gov.it/portale/it/iva_2026specifichetecniche
 * @see https://www.agenziaentrate.gov.it/portale/modello-redditi-persone-fisiche-2026/software-di-compilazione
 */
class SpecificheDichiarativi
{

    public const COLONNE_SPECIFICHE = ['modello', 'anno', 'url', 'provvedimento', 'istruzioni', 'schema'];

    /** Namespace degli elementi del modello IRAP nello schema XSD */
    public const NAMESPACE_IRAP = 'urn:www.agenziaentrate.gov.it:specificheTecniche:sco:ira';

        /**
     * Denominazioni dei quadri dei modelli le cui specifiche e istruzioni non
     * hanno i titoli nella forma "Quadro XX": per la Certificazione Unica le
     * sezioni delle specifiche (intestazioni delle pagine dei record C, D, G,
     * H e L).
     */
    public const DENOMINAZIONI_QUADRI = [
        'CUR' => [
            'CT' => 'Comunicazione per la ricezione telematica dei dati del modello 730-4',
            'DA' => 'Dati anagrafici del percipiente (frontespizio)',
            'DB' => 'Lavoro dipendente e assimilati: dati fiscali',
            'DC' => 'Lavoro dipendente e assimilati: dati previdenziali e assistenziali',
            'DF' => 'Trattamento di fine rapporto e indennità equipollenti',
            'DN' => 'Annotazioni',
            'AU' => 'Lavoro autonomo, provvigioni e redditi diversi',
            'LB' => 'Locazioni brevi',
        ],
    ];

/**
     * Formati incoerenti nelle specifiche ufficiali, corretti alla lettura:
     * il codice del rigo CR31 del modello Redditi PF 2026 è indicato N1 ma
     * i valori ammessi vanno da 12 a 21.
     */
    public const CORREZIONI_FORMATO = ['RPF' => [2026 => ['CR031001' => 'N2']]];

    public function __construct(private string $cartella = TabelleDichiarativi::CARTELLA)
    {

    }

    /**
     * @return array<string, array{campi: int, senzaFormato: string[], quadri: int}> esito per "<modello>-<anno>"
     */
    public function aggiorna(?string $modello = null, ?int $anno = null): array
    {
        if (trim((string) shell_exec('command -v pdftotext')) === '') {
            throw new \RuntimeException('pdftotext non disponibile: installare poppler-utils (vedi Dockerfile)');
        }
        $esiti = [];
        foreach ((new TabelleDichiarativi($this->cartella))->leggiSpecifiche() as $specifica) {
            if ((($modello !== null) && ($specifica['modello'] !== $modello)) || (($anno !== null) && ($specifica['anno'] !== $anno))) {
                continue;
            }
            $esiti[$specifica['modello'] . '-' . $specifica['anno']] = $this->aggiornaSpecifica($specifica);
        }
        if (count($esiti) === 0) {
            throw new \UnexpectedValueException('Nessuna specifica in specifiche.csv per il modello e l\'anno richiesti.');
        }
        return $esiti;
    }

    /**
     * @param array{modello: string, anno: int, url: string, provvedimento: string} $specifica
     */
    private function aggiornaSpecifica(array $specifica): array
    {
        if ($specifica['modello'] === 'ISA') {
            return $this->aggiornaSpecificheIsa($specifica);
        }
        if ($specifica['modello'] === 'IRA') {
            return $this->aggiornaSpecificheIrap($specifica);
        }
        $pdf = tempnam(sys_get_temp_dir(), 'spec');
        try {
            file_put_contents($pdf, $this->scarica($specifica['url']));
            $tsv = shell_exec('pdftotext -enc UTF-8 -tsv ' . escapeshellarg($pdf) . ' - 2>/dev/null');
        } finally {
            unlink($pdf);
        }
        if (!is_string($tsv) || ($tsv === '')) {
            throw new \RuntimeException('Testo delle specifiche non estratto: ' . $specifica['url']);
        }
        $lettore = new LettoreSpecifiche();
        $campi = $lettore->leggi($tsv);
        if (count($campi) < 100) {
            throw new \UnexpectedValueException(sprintf('Specifiche %s %d: trovati solo %d campi, impaginazione del PDF cambiata?', $specifica['modello'], $specifica['anno'], count($campi)));
        }
        foreach (self::CORREZIONI_FORMATO[$specifica['modello']][$specifica['anno']] ?? [] as $codice => $formato) {
            if (isset($campi[$codice])) {
                $campi[$codice]['formato'] = $formato;
            }
        }
        ksort($campi);
        $quadri = $lettore->getQuadri();
        $mancanti = array_diff(array_unique(array_map(fn(string $codice) => substr($codice, 0, 2), array_keys($campi))), array_keys($quadri));
        if ((count($mancanti) > 0) && ($specifica['istruzioni'] ?? null) !== null) {
            $pdf = tempnam(sys_get_temp_dir(), 'istr');
            try {
                file_put_contents($pdf, $this->scarica($specifica['istruzioni']));
                $testo = (string) shell_exec('pdftotext -enc UTF-8 -layout ' . escapeshellarg($pdf) . ' - 2>/dev/null');
            } finally {
                unlink($pdf);
            }
            $quadri += array_intersect_key(self::leggiTitoliIstruzioni($testo), array_flip($mancanti));
        }
        $quadri += array_intersect_key(self::DENOMINAZIONI_QUADRI[$specifica['modello']] ?? [], array_flip($mancanti));
        ksort($quadri);
        $nome = $specifica['modello'] . '-' . $specifica['anno'] . '.csv';
        $this->scriviCsv('campi-' . $nome, TabelleDichiarativi::COLONNE_CAMPI, array_map(fn(string $codice, array $campo) => [$codice, $campo['formato'], $campo['descrizione']], array_keys($campi), $campi));
        $this->scriviCsv('quadri-' . $nome, TabelleDichiarativi::COLONNE_QUADRI, array_map(fn(string $quadro, string $denominazione) => [$quadro, $denominazione], array_keys($quadri), $quadri));
        return [
            'campi' => count($campi),
            'senzaFormato' => array_keys(array_filter($campi, fn(array $campo) => $campo['formato'] === null)),
            'quadri' => count($quadri),
        ];
    }

    /**
     * @param array{modello: string, anno: int, url: string, provvedimento: string, istruzioni: ?string} $specifica
     */
    private function aggiornaSpecificheIsa(array $specifica): array
    {
        foreach (['bsdtar' => 'libarchive-tools', 'xls2csv' => 'catdoc', 'pdftotext' => 'poppler-utils'] as $comando => $pacchetto) {
            if (trim((string) shell_exec('command -v ' . $comando)) === '') {
                throw new \RuntimeException($comando . ' non disponibile: installare ' . $pacchetto . ' (vedi Dockerfile)');
            }
        }
        if ($specifica['istruzioni'] === null) {
            throw new \UnexpectedValueException('specifiche.csv: manca l\'indirizzo delle istruzioni ISA "Parte generale" (colonna istruzioni)');
        }
        $lettore = new LettoreSpecificheIsa();
        $cartella = sys_get_temp_dir() . '/specifiche-isa-' . bin2hex(random_bytes(4));
        mkdir($cartella);
        try {
            file_put_contents($cartella . '/specifiche.7z', $this->scarica($specifica['url'], "7z\xBC\xAF\x27\x1C"));
            exec(sprintf('bsdtar -xf %s -C %s 2>&1', escapeshellarg($cartella . '/specifiche.7z'), escapeshellarg($cartella)), $uscita, $esito);
            $archivi = self::trova($cartella, '/\.zip$/i');
            foreach ($archivi as $archivio) {
                exec(sprintf('bsdtar -xf %s -C %s 2>&1', escapeshellarg($archivio), escapeshellarg(dirname($archivio))), $uscita, $esito);
            }
            $campi = [];
            foreach (self::trova($cartella, '/^[A-Z]{2}\d{2}[A-Z]_SPECDEF\.xls$/') as $file) {
                $isa = substr(basename($file), 0, 5);
                $testo = shell_exec('xls2csv -d utf-8 -c ";" ' . escapeshellarg($file) . ' 2>/dev/null');
                foreach ($lettore->leggiCampi((string) $testo) as $codice => $campo) {
                    $campi[] = [$isa, $codice, LettoreSpecificheIsa::FORMATI[$campo['formato']] ?? $campo['formato'], $campo['descrizione']];
                }
            }
            $pdf = $cartella . '/istruzioni.pdf';
            file_put_contents($pdf, $this->scarica($specifica['istruzioni']));
            $elenco = $lettore->leggiElenco((string) shell_exec('pdftotext -enc UTF-8 -layout ' . escapeshellarg($pdf) . ' - 2>/dev/null'));
        } finally {
            foreach (array_reverse(self::trova($cartella, '/./', true)) as $elemento) {
                is_dir($elemento) ? @rmdir($elemento) : @unlink($elemento);
            }
            @rmdir($cartella);
        }
        $isaSpecifiche = array_values(array_unique(array_column($campi, 0)));
        if (count($isaSpecifiche) < 100) {
            throw new \UnexpectedValueException(sprintf('Specifiche ISA %d: trovati solo %d ISA, archivio cambiato?', $specifica['anno'], count($isaSpecifiche)));
        }
        $senzaElenco = array_diff($isaSpecifiche, array_keys($elenco));
        if (count($senzaElenco) > 0) {
            throw new \UnexpectedValueException('ISA delle specifiche assenti dalla tabella 1 delle istruzioni: ' . implode(', ', $senzaElenco));
        }
        usort($campi, fn(array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        ksort($elenco);
        $this->scriviCsv('campi-ISA-' . $specifica['anno'] . '.csv', TabelleDichiarativi::COLONNE_CAMPI_ISA, $campi);
        $this->scriviCsv('isa-' . $specifica['anno'] . '.csv', TabelleDichiarativi::COLONNE_ISA,
            array_map(fn(string $codice, array $isa) => [$codice, $isa['descrizione'], implode(' ', $isa['attivita'])], array_keys($elenco), $elenco));
        return ['campi' => count($campi), 'senzaFormato' => [], 'quadri' => count($isaSpecifiche)];
    }

    /**
     * Modello IRAP (fornitura XML): scarica le specifiche (PDF) e
     * l'archivio degli schemi XSD (colonna "schema" di specifiche.csv),
     * estrae gli schemi in xsd-IRA-<anno> tra le sorgenti (servono anche al
     * generatore del file), legge i campi con il loro percorso XML
     * (LettoreSpecificheIrap) e le denominazioni dei quadri dalle istruzioni;
     * riscrive campi-, xml- e quadri-IRA-<anno>.csv.
     *
     * @param array{modello: string, anno: int, url: string, provvedimento: string, istruzioni: ?string, schema: ?string} $specifica
     */
    private function aggiornaSpecificheIrap(array $specifica): array
    {
        if (($specifica['schema'] ?? null) === null) {
            throw new \UnexpectedValueException('specifiche.csv: manca l\'indirizzo dell\'archivio degli schemi XSD IRAP (colonna schema)');
        }
        if (trim((string) shell_exec('command -v bsdtar')) === '') {
            throw new \RuntimeException('bsdtar non disponibile: installare libarchive-tools (vedi Dockerfile)');
        }
        $nome = $specifica['modello'] . '-' . $specifica['anno'];
        $temporanea = sys_get_temp_dir() . '/specifiche-irap-' . bin2hex(random_bytes(4));
        mkdir($temporanea);
        try {
            file_put_contents($temporanea . '/specifiche.pdf', $this->scarica($specifica['url']));
            $testo = (string) shell_exec('pdftotext -enc UTF-8 -layout ' . escapeshellarg($temporanea . '/specifiche.pdf') . ' - 2>/dev/null');
            file_put_contents($temporanea . '/schemi.rar', $this->scarica($specifica['schema'], 'Rar!'));
            exec(sprintf('bsdtar -xf %s -C %s 2>&1', escapeshellarg($temporanea . '/schemi.rar'), escapeshellarg($temporanea)), $uscita, $esito);
            if ($esito !== 0) {
                throw new \RuntimeException('Archivio degli schemi XSD non estratto: ' . implode(' ', $uscita));
            }
            $titoli = [];
            if (($specifica['istruzioni'] ?? null) !== null) {
                file_put_contents($temporanea . '/istruzioni.pdf', $this->scarica($specifica['istruzioni']));
                $titoli = self::leggiTitoliIstruzioni((string) shell_exec('pdftotext -enc UTF-8 -layout ' . escapeshellarg($temporanea . '/istruzioni.pdf') . ' - 2>/dev/null'));
            }
            // schemi tra le sorgenti, al posto dei precedenti
            $destinazione = $this->cartella . 'xsd-' . $nome;
            if (is_dir($destinazione)) {
                foreach (array_reverse(self::trova($destinazione, '/./', true)) as $elemento) {
                    is_dir($elemento) ? @rmdir($elemento) : @unlink($elemento);
                }
            } else {
                mkdir($destinazione);
            }
            foreach (self::trova($temporanea, '/\.xsd$/i') as $xsd) {
                $relativo = substr($xsd, strlen($temporanea) + 1);
                $relativo = preg_replace('#^xsd/#', '', $relativo);
                if (is_dir(dirname($destinazione . '/' . $relativo)) === false) {
                    mkdir(dirname($destinazione . '/' . $relativo), 0777, true);
                }
                copy($xsd, $destinazione . '/' . $relativo);
            }
        } finally {
            foreach (array_reverse(self::trova($temporanea, '/./', true)) as $elemento) {
                is_dir($elemento) ? @rmdir($elemento) : @unlink($elemento);
            }
            @rmdir($temporanea);
        }
        $lettore = new LettoreSpecificheIrap();
        $campi = $lettore->leggi($testo, new SchemaXsd($destinazione), self::NAMESPACE_IRAP);
        if (count($campi) < 500) {
            throw new \UnexpectedValueException(sprintf('Specifiche IRAP %d: trovati solo %d campi, impaginazione del PDF cambiata?', $specifica['anno'], count($campi)));
        }
        ksort($campi);
        $quadri = array_intersect_key($titoli, array_flip(array_unique(array_map(fn(string $codice) => substr($codice, 0, 2), array_keys($campi)))));
        ksort($quadri);
        $this->scriviCsv('campi-' . $nome . '.csv', TabelleDichiarativi::COLONNE_CAMPI, array_map(fn(string $codice, array $campo) => [$codice, $campo['formato'], $campo['descrizione']], array_keys($campi), $campi));
        $this->scriviCsv('xml-' . $nome . '.csv', ['codice', 'percorso'], array_map(fn(string $codice, array $campo) => [$codice, $campo['percorso']], array_keys($campi), $campi));
        $this->scriviCsv('quadri-' . $nome . '.csv', TabelleDichiarativi::COLONNE_QUADRI, array_map(fn(string $quadro, string $denominazione) => [$quadro, $denominazione], array_keys($quadri), $quadri));
        return [
            'campi' => count($campi),
            'senzaFormato' => array_map(fn(array $codice) => $codice[1] . ' (' . $codice[0] . ', non nello schema)', $lettore->getNonTrovati()),
            'quadri' => count($quadri),
        ];
    }

    /**
     * Denominazioni dei quadri dall'indice delle istruzioni ("N. QUADRO XX -
     * TITOLO", "N – QUADRO XX – TITOLO" o "CN. QUADRO XX" con il titolo
     * sulla riga successiva, con un'altra riga se il titolo continua), in minuscolo con l'iniziale maiuscola; restano
     * maiuscole le sigle (parole fino a tre lettere che non sono
     * preposizioni, articoli o negazioni).
     *
     * @return array<string, string> sigla => denominazione
     */
    public static function leggiTitoliIstruzioni(string $testo): array
    {
        $righe = explode("\n", $testo);
        $quadri = [];
        $continua = fn(string $riga) => ($riga !== '') && (mb_strtoupper($riga) === $riga) && (preg_match('/^(\d|C\d|QUADRO)/u', $riga) !== 1);
        foreach ($righe as $indice => $riga) {
            if (preg_match('/^\s*(?:C?\d+\s*[.–-]\s+)?QUADRO\s+([A-Z][A-Z0-9])\s*(?:[-–]\s*(.+?))?\s*$/u', $riga, $parti) !== 1) {
                continue;
            }
            // titolo sulla stessa riga o, senza (istruzioni del modello CNM: "C1. QUADRO NF"), sulla riga successiva
            $titolo = $parti[2] ?? '';
            $successiva = $indice + 1;
            if ($titolo === '') {
                $titolo = trim($righe[$successiva] ?? '');
                $successiva++;
                if ($continua($titolo) === false) {
                    continue;
                }
            }
            $seguente = trim($righe[$successiva] ?? '');
            if ($continua($seguente)) {
                $titolo .= ' ' . $seguente;
            }
            $quadri[$parti[1]] ??= self::comeFrase($titolo);
        }
        return $quadri;
    }

    private static function comeFrase(string $titolo): string
    {
        $minuscole = ['A', 'AD', 'AI', 'AL', 'ALLA', 'ALLE', 'AGLI', 'CON', 'DA', 'DAI', 'DAL', 'DALLA', 'DEI', 'DEL', 'DELLA', 'DELLE', 'DEGLI', 'DELL', 'DI', 'E', 'ED', 'GLI', 'I', 'IL', 'IN', 'LA', 'LE', 'NEI', 'NEL', 'NELLA', 'O', 'PER', 'SU', 'SUI', 'SUL', 'TRA', 'UN', 'UNA', 'NON', 'ALTRE', 'ALTRI', 'SPESE', 'RISULTATO', 'REDDITI', 'REDDITO', 'CREDITI', 'BASE', 'ELENCO', 'USCITA', 'VALORI', 'FONTE', 'ALCUNE', 'DATI', 'ART', 'CUI', 'DE'];
        $parole = preg_split('/(\s+)/u', preg_replace('/\s+/u', ' ', trim($titolo)), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($parole as $posizione => $parola) {
            $nuda = preg_replace('/[^\p{L}]/u', '', $parola);
            $sigla = ($nuda !== '') && (mb_strlen($nuda) <= 3) && (in_array(str_replace(['’', "'"], '', $nuda), $minuscole, true) === false) && (preg_match("/[’']/u", $parola) !== 1);
            $parole[$posizione] = $sigla ? $parola : mb_strtolower($parola);
        }
        // sigle fiscali sempre maiuscole, anche dopo l'apostrofo ("dell'IRES")
        $frase = preg_replace_callback('/(?<=^|[\s’\'(])(tuir|ires|irap|iva|ivie|ivafe|cfc|ace)(?=$|[\s,.)])/u', fn(array $sigla) => mb_strtoupper($sigla[1]), implode(' ', $parole));
        return mb_strtoupper(mb_substr($frase, 0, 1)) . mb_substr($frase, 1);
    }

    /**
     * @return string[] percorsi dei file (e, con $cartelle, delle cartelle) sotto $cartella il cui nome corrisponde all'espressione
     */
    private static function trova(string $cartella, string $espressione, bool $cartelle = false): array
    {
        $trovati = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cartella, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $elemento) {
            if (($cartelle || $elemento->isFile()) && (preg_match($espressione, $elemento->getFilename()) === 1)) {
                $trovati[] = $elemento->getPathname();
            }
        }
        sort($trovati);
        return $trovati;
    }

    private function scriviCsv(string $nome, array $colonne, array $righe): void
    {
        $file = @fopen($this->cartella . $nome, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $nome);
        fputcsv($file, $colonne, ';', '"', '');
        foreach ($righe as $riga) {
            fputcsv($file, $riga, ';', '"', '');
        }
        fclose($file);
    }

    protected function scarica(string $url, string $firma = '%PDF'): string
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Telonio)']);
        $contenuto = curl_exec($curl);
        $codice = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if (($contenuto === false) || ($codice !== 200) || (str_starts_with((string) $contenuto, $firma) === false)) {
            throw new \RuntimeException('Download non riuscito (HTTP ' . $codice . '): ' . $url);
        }
        return $contenuto;
    }
}
