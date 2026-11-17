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
 * @see https://www.agenziaentrate.gov.it/portale/it/iva_2026specifichetecniche
 * @see https://www.agenziaentrate.gov.it/portale/modello-redditi-persone-fisiche-2026/software-di-compilazione
 */
class SpecificheDichiarativi
{

    public const COLONNE_SPECIFICHE = ['modello', 'anno', 'url', 'provvedimento', 'istruzioni'];

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
