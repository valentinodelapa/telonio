<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

/**
 * Archivio delle tabelle per il modello F24 pubblicato dall'Agenzia delle
 * Entrate ("Tabelle zippate" della pagina Riepilogo tabelle): un file RAR
 * aggiornato a ogni modifica, con le tabelle dei codici tributo, delle
 * causali contributo, delle sedi e degli altri codici in formato xls/xlsx.
 *
 * L'archivio si estrae con bsdtar (libarchive), presente nell'immagine
 * Docker del progetto.
 *
 * @see https://www.agenziaentrate.gov.it/portale/strumenti/codici-attivita-e-tributo/f24-codici-tributo-per-i-versamenti/tabelle-dei-codici-tributo-e-altri-codici-per-il-modello-f24/riepilogo-tabelle
 */
class ArchivioTabelleF24
{

    public const URL_RIEPILOGO = 'https://www.agenziaentrate.gov.it/portale/strumenti/codici-attivita-e-tributo/f24-codici-tributo-per-i-versamenti/tabelle-dei-codici-tributo-e-altri-codici-per-il-modello-f24/riepilogo-tabelle';
    private const BASE = 'https://www.agenziaentrate.gov.it';
    private const MODELLO_LINK = '#href="([^"]*TabF24_(\d{8})\.rar[^"]*)"#i';

    private ?string $cartella = null;
    private ?string $data = null;
    private ?string $url = null;
    private array $temporanei = [];

    /**
     * Prepara i file dell'archivio. La sorgente può essere una cartella già
     * estratta, un file RAR o un URL; null = archivio più recente indicato
     * nella pagina dell'Agenzia.
     */
    public function prepara(?string $sorgente, ?callable $avanzamento = null): void
    {
        if (($sorgente !== null) && is_dir($sorgente)) {
            $this->cartella = $sorgente;
            $this->data = self::dataDaNome($sorgente);
            return;
        }
        if ($sorgente === null) {
            $sorgente = $this->trovaArchivio();
        }
        if (preg_match('#^https?://#', $sorgente) === 1) {
            $this->url = $sorgente;
            $avanzamento !== null && $avanzamento('  download ' . strtok($sorgente, '?'));
            $sorgente = $this->scarica($sorgente);
        }
        $this->data ??= self::dataDaNome($sorgente);
        $this->cartella = $this->estrai($sorgente);
    }

    private function trovaArchivio(): string
    {
        $pagina = $this->leggiUrl(self::URL_RIEPILOGO);
        if (preg_match(self::MODELLO_LINK, $pagina, $corrispondenza) !== 1) {
            throw new \UnexpectedValueException('Link all\'archivio delle tabelle F24 non trovato in ' . self::URL_RIEPILOGO);
        }
        $this->data = $corrispondenza[2];
        $link = html_entity_decode($corrispondenza[1]);
        return str_starts_with($link, 'http') ? $link : self::BASE . $link;
    }

    private function scarica(string $url): string
    {
        $file = sys_get_temp_dir() . '/tabf24-' . bin2hex(random_bytes(6)) . '.rar';
        file_put_contents($file, $this->leggiUrl($url));
        $this->temporanei[] = $file;
        return $file;
    }

    private function leggiUrl(string $url): string
    {
        $contesto = stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'Mozilla/5.0 (CommSuite tabelle-f24)']]);
        $contenuto = @file_get_contents($url, false, $contesto);
        if ($contenuto === false) {
            throw new \RuntimeException('Download non riuscito: ' . $url);
        }
        return $contenuto;
    }

    private function estrai(string $archivio): string
    {
        if (is_file($archivio) === false) {
            throw new \RuntimeException('Archivio non trovato: ' . $archivio);
        }
        $cartella = sys_get_temp_dir() . '/tabf24-' . bin2hex(random_bytes(6));
        mkdir($cartella, 0775, true);
        $this->temporanei[] = $cartella;
        exec('bsdtar -xf ' . escapeshellarg($archivio) . ' -C ' . escapeshellarg($cartella) . ' 2>&1', $output, $esito);
        if ($esito !== 0) {
            throw new \RuntimeException('Estrazione dell\'archivio non riuscita (serve bsdtar): ' . implode(' ', $output));
        }
        return $cartella;
    }

    private static function dataDaNome(string $percorso): ?string
    {
        return preg_match('/TabF24_(\d{8})/i', $percorso, $corrispondenza) === 1 ? $corrispondenza[1] : null;
    }

    /**
     * Percorso del file dell'archivio il cui nome corrisponde all'espressione
     * regolare (cercato anche nelle sottocartelle).
     */
    public function file(string $modello): string
    {
        $trovati = [];
        $iteratore = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cartella, \FilesystemIterator::SKIP_DOTS));
        foreach ($iteratore as $file) {
            if ($file->isFile() && (preg_match($modello, $file->getFilename()) === 1)) {
                $trovati[] = $file->getPathname();
            }
        }
        if (count($trovati) !== 1) {
            throw new \UnexpectedValueException(sprintf('Nell\'archivio %d file corrispondono a %s: il contenuto dell\'archivio è cambiato.', count($trovati), $modello));
        }
        return $trovati[0];
    }

    /**
     * @return string[] percorsi dei file il cui nome corrisponde
     */
    public function tuttiIFile(string $modello): array
    {
        $trovati = [];
        $iteratore = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->cartella, \FilesystemIterator::SKIP_DOTS));
        foreach ($iteratore as $file) {
            if ($file->isFile() && (preg_match($modello, $file->getFilename()) === 1)) {
                $trovati[] = $file->getPathname();
            }
        }
        sort($trovati);
        return $trovati;
    }

    /**
     * Data di aggiornamento dell'archivio (AAAAMMGG dal nome), se nota.
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function pulisci(): void
    {
        foreach (array_reverse($this->temporanei) as $percorso) {
            if (is_dir($percorso)) {
                exec('rm -rf ' . escapeshellarg($percorso));
            } elseif (is_file($percorso)) {
                unlink($percorso);
            }
        }
        $this->temporanei = [];
    }
}
