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
 * @see https://www.agenziaentrate.gov.it/portale/it/iva_2026specifichetecniche
 * @see https://www.agenziaentrate.gov.it/portale/modello-redditi-persone-fisiche-2026/software-di-compilazione
 */
class SpecificheDichiarativi
{

    public const COLONNE_SPECIFICHE = ['modello', 'anno', 'url', 'provvedimento'];

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

    private function scriviCsv(string $nome, array $colonne, array $righe): void
    {
        $file = @fopen($this->cartella . $nome, 'w') ?: throw new \RuntimeException('Impossibile scrivere ' . $nome);
        fputcsv($file, $colonne, ';', '"', '');
        foreach ($righe as $riga) {
            fputcsv($file, $riga, ';', '"', '');
        }
        fclose($file);
    }

    protected function scarica(string $url): string
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Telonio)']);
        $contenuto = curl_exec($curl);
        $codice = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if (($contenuto === false) || ($codice !== 200) || (str_starts_with((string) $contenuto, '%PDF') === false)) {
            throw new \RuntimeException('Download non riuscito (HTTP ' . $codice . '): ' . $url);
        }
        return $contenuto;
    }
}
