<?php

namespace Contabilita\Application\Services\FatturaPa;

use Contabilita\Application\Services\TabelleRiferimento\SpecificheContabilita;

/**
 * Verifica della fattura elettronica sullo schema XSD ufficiale (VFPR12,
 * tracciati FPR12 e FPA12) conservato nel modulo, senza accesso alla rete.
 *
 * Il Sistema di Interscambio scarta i file non conformi, quindi una fattura
 * recapitata lo è di norma: un errore indica un file alterato, prodotto
 * fuori dal SdI o di una versione dello schema più recente di quella
 * installata (da aggiornare con `sisma specifiche-contabilita`). Il file
 * resta comunque importabile, con l'avviso.
 */
class VerificaSchemaFattura
{

    public const NAMESPACE_FATTURA = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';

    public function __construct(private string $schema = '')
    {
        $this->schema = $schema === '' ? SpecificheContabilita::getSchemaFatturaPa() : $schema;
    }

    /**
     * @return string|null null se conforme, altrimenti la descrizione del primo errore
     */
    public function verifica(string $xml): ?string
    {
        $precedente = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \DOMDocument();
        $letto = $dom->loadXML($xml, LIBXML_NONET);
        $namespace = $letto ? $dom->documentElement?->namespaceURI : null;
        $conforme = ($namespace === self::NAMESPACE_FATTURA) && $dom->schemaValidate($this->schema, LIBXML_NONET);
        $errori = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);
        if ($conforme) {
            return null;
        }
        if ($letto && ($namespace !== self::NAMESPACE_FATTURA)) {
            return 'spazio dei nomi ' . ($namespace ?? 'assente') . ' diverso da quello della fattura ordinaria (' . self::NAMESPACE_FATTURA . ')';
        }
        $errore = $errori[0] ?? null;
        return $errore === null ? 'file non conforme' : 'riga ' . $errore->line . ': ' . trim($errore->message);
    }
}
