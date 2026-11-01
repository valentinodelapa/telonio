<?php

namespace Contabilita\Application\Services\TabelleRiferimento;

/**
 * Schemi XSD ufficiali usati dal modulo, conservati in Application/Assets/xsd
 * con i riferimenti tra file resi locali (nessun accesso alla rete durante
 * la validazione):
 *
 * - comunicazione delle liquidazioni periodiche IVA (fornitura IVP18):
 *   archivio delle specifiche e schema aggiornato della comunicazione
 *   pubblicati dall'Agenzia delle Entrate;
 * - fattura elettronica (schema VFPR12, per le fatture ordinarie verso
 *   privati e PA, FPR12 e FPA12);
 * - schema W3C delle firme XML (xmldsig), importato da entrambi.
 *
 * @see https://www.agenziaentrate.gov.it/portale/schede/comunicazioni/liquidazioni-periodiche-iva/st-comunicazione-delle-liquidazioni-periodiche-iva
 * @see https://www.agenziaentrate.gov.it/portale/specifiche-tecniche-versione-1.9.1-%C2%A0-utilizzabili-dal-15-maggio-2026-
 */
class SpecificheContabilita
{

    public const URL_PAGINA_LIPE = 'https://www.agenziaentrate.gov.it/portale/schede/comunicazioni/liquidazioni-periodiche-iva/st-comunicazione-delle-liquidazioni-periodiche-iva';
    public const URL_FATTURAPA = 'https://www.agenziaentrate.gov.it/portale/documents/d/guest/schema_vfpr12_v1-2-3';
    public const URL_XMLDSIG = 'https://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd';
    public const XSD_LIPE = 'lipe/IVP/xsd/fornituraIvp_2018_v1.xsd';
    public const XSD_FATTURAPA = 'fatturapa/Schema_VFPR12_v1.2.3.xsd';
    public const XSD_XMLDSIG = 'common/xmldsig-core-schema.xsd';
    private const FILE_COMUNI_LIPE = ['fornitura_v3.xsd', 'typesDati_v3.xsd', 'typesProvincie_v3.xsd'];
    private const FILE_LIPE = ['fornituraIvp_2018_v1.xsd', 'intestazioneIvp_2018_v1.xsd', 'comunicazioneIvp_2018_v1.xsd'];

    public function __construct(private string $cartella = '')
    {
        $this->cartella = $cartella === '' ? self::getCartellaXsd() : $cartella;
    }

    public static function getCartellaXsd(): string
    {
        return dirname(__DIR__, 2) . '/Assets/xsd';
    }

    public static function getSchemaLipe(): string
    {
        return self::getCartellaXsd() . '/' . self::XSD_LIPE;
    }

    public static function getSchemaFatturaPa(): string
    {
        return self::getCartellaXsd() . '/' . self::XSD_FATTURAPA;
    }

    /**
     * Scarica e installa gli schemi.
     *
     * @return string[] file installati
     */
    public function aggiorna(?string $urlFatturaPa = null): array
    {
        $installati = [];
        $this->scrivi(self::XSD_XMLDSIG, $this->scarica(self::URL_XMLDSIG));
        $installati[] = self::XSD_XMLDSIG;
        [$urlArchivio, $urlComunicazione] = $this->trovaLinkLipe();
        $archivio = tempnam(sys_get_temp_dir(), 'ivp');
        file_put_contents($archivio, $this->scarica($urlArchivio));
        $zip = new \ZipArchive();
        if ($zip->open($archivio) !== true) {
            unlink($archivio);
            throw new \RuntimeException('Archivio delle specifiche LIPE non leggibile: ' . $urlArchivio);
        }
        $file = [];
        foreach (array_merge(self::FILE_COMUNI_LIPE, self::FILE_LIPE) as $nome) {
            $contenuto = $zip->getFromName($nome);
            if ($contenuto === false) {
                $zip->close();
                unlink($archivio);
                throw new \RuntimeException('L\'archivio delle specifiche LIPE non contiene ' . $nome . ': tracciato cambiato.');
            }
            $file[$nome] = $contenuto;
        }
        $zip->close();
        unlink($archivio);
        if ($urlComunicazione !== null) {
            // lo schema della comunicazione pubblicato a parte è più recente di quello dell'archivio
            $file['comunicazioneIvp_2018_v1.xsd'] = $this->scarica($urlComunicazione);
        }
        foreach (self::FILE_COMUNI_LIPE as $nome) {
            $this->scrivi('lipe/common/' . $nome, $file[$nome]);
            $installati[] = 'lipe/common/' . $nome;
        }
        foreach (self::FILE_LIPE as $nome) {
            $this->scrivi('lipe/IVP/xsd/' . $nome, str_replace('schemaLocation="xmldsig-core-schema.xsd"', 'schemaLocation="../../../' . self::XSD_XMLDSIG . '"', $file[$nome]));
            $installati[] = 'lipe/IVP/xsd/' . $nome;
        }
        $fattura = $this->scarica($urlFatturaPa ?? self::URL_FATTURAPA);
        $fattura = preg_replace('#schemaLocation="https?://[^"]*xmldsig-core-schema\.xsd"#', 'schemaLocation="../' . self::XSD_XMLDSIG . '"', $fattura);
        $this->scrivi(self::XSD_FATTURAPA, $fattura);
        $installati[] = self::XSD_FATTURAPA;
        foreach ([self::XSD_LIPE, self::XSD_FATTURAPA] as $schema) {
            $errore = self::verificaSchema($this->cartella . '/' . $schema);
            if ($errore !== null) {
                throw new \RuntimeException('Schema ' . $schema . ' non utilizzabile: ' . $errore);
            }
        }
        return $installati;
    }

    /**
     * Verifica che lo schema si carichi (con i file importati): null se
     * utilizzabile, altrimenti il primo errore.
     */
    public static function verificaSchema(string $schema): ?string
    {
        $precedente = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $documento = new \DOMDocument();
        $documento->loadXML('<Prova/>');
        $documento->schemaValidate($schema, LIBXML_NONET);
        $errori = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);
        foreach ($errori as $errore) {
            // con uno schema valido l'unico errore è l'elemento radice sconosciuto
            if (str_contains($errore->message, 'No matching global declaration') === false) {
                return trim($errore->message);
            }
        }
        return count($errori) > 0 ? null : 'nessun errore sul documento di prova';
    }

    /**
     * @return array{0: string, 1: ?string} archivio delle specifiche e schema aggiornato della comunicazione
     */
    private function trovaLinkLipe(): array
    {
        $html = $this->scarica(self::URL_PAGINA_LIPE);
        if (preg_match('#href="([^"]*SchemaIV\d+\.zip[^"]*)"#', $html, $archivio) !== 1) {
            throw new \RuntimeException('Link all\'archivio delle specifiche LIPE non trovato nella pagina ' . self::URL_PAGINA_LIPE);
        }
        $comunicazione = preg_match('#href="([^"]*comunicazioneIvp_\d{4}_v\d+\.xsd[^"]*)"#', $html, $schema) === 1 ? self::assoluto($schema[1]) : null;
        return [self::assoluto($archivio[1]), $comunicazione];
    }

    private static function assoluto(string $url): string
    {
        $url = html_entity_decode($url);
        return str_starts_with($url, 'http') ? $url : 'https://www.agenziaentrate.gov.it' . $url;
    }

    private function scarica(string $url): string
    {
        $contenuto = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'Mozilla/5.0 (Telonio specifiche-contabilita)']]));
        if (($contenuto === false) || ($contenuto === '')) {
            throw new \RuntimeException('Download non riuscito: ' . $url);
        }
        return $contenuto;
    }

    private function scrivi(string $percorso, string $contenuto): void
    {
        $file = $this->cartella . '/' . $percorso;
        if ((is_dir(dirname($file)) === false) && (mkdir(dirname($file), 0775, true) === false)) {
            throw new \RuntimeException('Impossibile creare la cartella ' . dirname($file));
        }
        $contenuto = str_starts_with($contenuto, "\xEF\xBB\xBF") ? substr($contenuto, 3) : $contenuto;
        file_put_contents($file, str_replace("\r\n", "\n", $contenuto));
    }
}
