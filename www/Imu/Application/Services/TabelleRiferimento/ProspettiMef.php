<?php

namespace Imu\Application\Services\TabelleRiferimento;

/**
 * Prospetti delle aliquote IMU pubblicati dal Dipartimento delle Finanze
 * (servizio "Ricerca delibere", sezione IMU):
 * - elenco dei prospetti dell'anno: CSV "Delibere aliquote" (codice
 *   catastale, comune, delibera, data di pubblicazione, nome del PDF);
 * - PDF del prospetto: link nella pagina dei risultati per comune e anno
 *   (il link richiede una chiave che il CSV non riporta);
 * - testo del PDF con pdftotext (poppler-utils).
 *
 * Con una cartella di copia i file scaricati sono salvati e, se presenti,
 * riletti da lì.
 *
 * @see https://www1.finanze.gov.it/finanze2/dipartimentopolitichefiscali/fiscalitalocale/nuova_imu/sceltaregione.htm
 */
class ProspettiMef
{

    public const URL = 'https://www1.finanze.gov.it/finanze2/dipartimentopolitichefiscali/fiscalitalocale/nuova_imu/';
    private const SITO = 'https://www1.finanze.gov.it';
    private const COLONNE_ELENCO = ['CODICE_CATASTALE', 'COMUNE', 'NUMERO', 'DATA_DELIBERA', 'DATA_PUBBLICAZIONE', 'PR', 'REGIONE', 'FILE_DELIBERA'];
    private const PAUSA_MICROSECONDI = 300000;

    /** @var array<int, array<string, array>> */
    private array $elenchi = [];
    private int $scaricati = 0;

    public function __construct(private ?string $cartellaCopia = null)
    {
        if (($cartellaCopia !== null) && (is_dir($cartellaCopia) === false) && (mkdir($cartellaCopia, 0775, true) === false)) {
            throw new \RuntimeException('Impossibile creare la cartella ' . $cartellaCopia);
        }
    }

    /**
     * Prospetti pubblicati per l'anno, per codice catastale: uno per comune,
     * o uno per ex comune nei comuni nati da fusione con aliquote
     * differenziate. Delle rettifiche (errata corrige) si tiene la
     * pubblicazione più recente.
     *
     * @return array<string, array<int, array{codiceCatastale: string, comune: string, exComune: ?string, numeroDelibera: string, dataDelibera: ?string, dataPubblicazione: ?string, file: string, note: ?string}>>
     */
    public function getElenco(int $anno): array
    {
        if (isset($this->elenchi[$anno])) {
            return $this->elenchi[$anno];
        }
        $csv = $this->leggi(self::URL . 'dati/download_csv_IMU.php?tipo=D&anno=' . $anno, 'elenco-' . $anno . '.csv');
        if (mb_check_encoding($csv, 'UTF-8') === false) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        }
        $righe = array_values(array_filter(explode("\n", str_replace("\r", '', $csv)), fn(string $riga) => trim($riga) !== ''));
        $intestazione = str_getcsv(array_shift($righe) ?? '', ';', '"', '');
        if (array_slice($intestazione, 0, count(self::COLONNE_ELENCO)) !== self::COLONNE_ELENCO) {
            throw new \RuntimeException('Intestazione dell\'elenco dei prospetti ' . $anno . ' non riconosciuta: ' . implode(';', $intestazione));
        }
        $colonne = count($intestazione);
        $elenco = [];
        foreach ($righe as $riga) {
            $campi = array_combine($intestazione, array_slice(array_pad(str_getcsv($riga, ';', '"', ''), $colonne, ''), 0, $colonne));
            $codice = strtoupper(trim($campi['CODICE_CATASTALE']));
            if (preg_match('/^[A-Z]\d{3}$/', $codice) !== 1) {
                continue;
            }
            $fuso = strtoupper(trim($campi['COMUNE FUSO CON ALIQUOTE DIFFERENZIATE'] ?? '')) === 'SI';
            $exComune = $fuso && (trim($campi['EX_COMUNE'] ?? '') !== '') ? trim($campi['EX_COMUNE']) : null;
            $voce = [
                'codiceCatastale' => $codice,
                'comune' => trim($campi['COMUNE']),
                'exComune' => $exComune,
                'numeroDelibera' => trim($campi['NUMERO']),
                'dataDelibera' => self::data($campi['DATA_DELIBERA']),
                'dataPubblicazione' => self::data($campi['DATA_PUBBLICAZIONE']),
                'file' => trim($campi['FILE_DELIBERA']),
                'note' => trim($campi['NOTE'] ?? '') !== '' ? trim($campi['NOTE']) : null,
            ];
            $chiave = $exComune ?? '';
            $esistente = $elenco[$codice][$chiave] ?? null;
            if (($esistente === null) || ([$voce['dataPubblicazione'], (int) $voce['file']] > [$esistente['dataPubblicazione'], (int) $esistente['file']])) {
                $elenco[$codice][$chiave] = $voce;
            }
        }
        if (count($elenco) === 0) {
            throw new \RuntimeException('Elenco dei prospetti ' . $anno . ' vuoto.');
        }
        foreach ($elenco as $codice => $voci) {
            $elenco[$codice] = array_values($voci);
        }
        return $this->elenchi[$anno] = $elenco;
    }

    /**
     * Testo del prospetto (pdftotext -layout) e URL del PDF.
     *
     * @return array{0: string, 1: string}
     */
    public function getTesto(string $codiceCatastale, int $anno, string $file): array
    {
        $risultati = $this->leggi(self::URL . 'risultato.htm', 'risultato-' . $codiceCatastale . '-' . $anno . '.html', ['anno' => (string) $anno, 'cc' => $codiceCatastale]);
        preg_match_all('~href="([^"]*download_lib\.php\?key=[^"&]+&(?:amp;)?nome=([^"]+))"~i', $risultati, $link, PREG_SET_ORDER);
        $url = null;
        foreach ($link as [, $indirizzo, $nome]) {
            if (html_entity_decode($nome) === $file) {
                $url = self::SITO . html_entity_decode($indirizzo);
            }
        }
        if ($url === null) {
            throw new \RuntimeException('PDF ' . $file . ' non trovato tra i documenti del comune ' . $codiceCatastale . ' per il ' . $anno . '.');
        }
        $pdf = $this->leggi($url, $file);
        if (str_starts_with($pdf, '%PDF') === false) {
            throw new \RuntimeException('Il file scaricato non è un PDF: ' . $url);
        }
        return [$this->estraiTesto($pdf), $url];
    }

    private function estraiTesto(string $pdf): string
    {
        if (trim((string) shell_exec('command -v pdftotext')) === '') {
            throw new \RuntimeException('pdftotext non disponibile: installare poppler-utils (vedi Dockerfile)');
        }
        $file = tempnam(sys_get_temp_dir(), 'imu');
        file_put_contents($file, $pdf);
        try {
            $testo = shell_exec('pdftotext -enc UTF-8 -layout ' . escapeshellarg($file) . ' - 2>/dev/null');
        } finally {
            unlink($file);
        }
        if (($testo === null) || ($testo === false) || (trim($testo) === '')) {
            throw new \RuntimeException('Testo del prospetto non estratto.');
        }
        return $testo;
    }

    /**
     * @param array<string, string>|null $parametri POST
     */
    private function leggi(string $url, string $nomeCopia, ?array $parametri = null): string
    {
        $file = $this->cartellaCopia === null ? null : rtrim($this->cartellaCopia, '/') . '/' . preg_replace('/[^\w.-]+/', '-', $nomeCopia);
        if (($file !== null) && is_file($file)) {
            return file_get_contents($file);
        }
        if ($this->scaricati++ > 0) {
            usleep(self::PAUSA_MICROSECONDI);
        }
        $opzioni = ['timeout' => 60, 'user_agent' => 'Mozilla/5.0 (Telonio aliquote-imu)'];
        if ($parametri !== null) {
            $opzioni += ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => http_build_query($parametri)];
        }
        $contenuto = @file_get_contents($url, false, stream_context_create(['http' => $opzioni]));
        if ($contenuto === false) {
            throw new \RuntimeException('Download non riuscito: ' . $url);
        }
        if ($file !== null) {
            file_put_contents($file, $contenuto);
        }
        return $contenuto;
    }

    private static function data(string $data): ?string
    {
        $data = \DateTimeImmutable::createFromFormat('!d/m/Y', trim($data));
        return $data === false ? null : $data->format('Y-m-d');
    }
}
