<?php

namespace Dichiarativi\Application\Services\Isa;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Variabili precalcolate degli ISA fornite dall'Agenzia delle Entrate
 * (file XML del Cassetto fiscale o della richiesta massiva degli
 * intermediari, schema IsaPrecompilato e fornituraPIS dell'allegato 2.1 al
 * provvedimento del 13 aprile 2026): ogni elemento Isa ha codice fiscale,
 * codice dell'ISA, tipologia di reddito (1 impresa, 2 lavoro autonomo) e
 * gli Elemento con il nome del campo del modello (es. IDF00101) e il
 * valore.
 *
 * L'importazione sostituisce le variabili precalcolate del modello ISA
 * (campi IDF e IFU del catalogo); senza importazione la tipologia di
 * precalcolo è "non disponibile" (IFU00101 = 3, proposta da PropostaIsa).
 */
class PrecalcolateIsa
{

    public const NAMESPACE_ISA = 'urn:xml.isa.sogei.it';

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return int variabili importate
     * @throws \DomainException se il file non è valido o non ha le variabili del contribuente e dell'ISA
     */
    public function importa(Dichiarazione $dichiarazione, string $xml, ?QuadriDichiarazione $quadri = null, bool $salva = true): int
    {
        $documento = new \DOMDocument();
        if (@$documento->loadXML($xml) === false) {
            throw new \DomainException('File delle variabili precalcolate non valido: non è un XML.');
        }
        $xpath = new \DOMXPath($documento);
        $xpath->registerNamespace('isa', self::NAMESPACE_ISA);
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $tipoReddito = $quadri->haCampo('H0100201') && !$quadri->haCampo('F0100101') ? '2' : '1';
        $codiceFiscale = strtoupper((string) $dichiarazione->soggetto->codiceFiscale);
        $trovato = null;
        $presenti = [];
        foreach ($xpath->query('//isa:ISAPrecompilato/isa:Isa') as $isa) {
            $cf = strtoupper(trim((string) $xpath->evaluate('string(isa:CodiceFiscale)', $isa)));
            $codice = trim((string) $xpath->evaluate('string(isa:CodiceIsa)', $isa));
            $tipo = trim((string) $xpath->evaluate('string(isa:TipoReddito)', $isa));
            $presenti[] = $cf . ' ' . $codice . ' (' . $tipo . ')';
            if (($cf === $codiceFiscale) && ($codice === $dichiarazione->isa) && (($tipo === '') || ($tipo === $tipoReddito))) {
                $trovato = $isa;
            }
        }
        if ($trovato === null) {
            throw new \DomainException(sprintf('Il file non contiene le variabili precalcolate di %s per l\'ISA %s%s.', $codiceFiscale, $dichiarazione->isa,
                count($presenti) > 0 ? ' (contiene: ' . implode(', ', array_slice($presenti, 0, 5)) . ')' : ''));
        }
        foreach ($quadri->getValori()[1] ?? [] as $codice => $dati) {
            if (self::isPrecalcolata($codice)) {
                $quadri->imposta($codice, null, 1, false);
            }
        }
        $importate = 0;
        foreach ($xpath->query('isa:Elementi/isa:Elemento', $trovato) as $elemento) {
            $nome = trim((string) $xpath->evaluate('string(isa:Nome)', $elemento));
            $valore = trim((string) $xpath->evaluate('string(isa:Valore)', $elemento));
            if (self::isPrecalcolata($nome) && $quadri->haCampo($nome) && ($valore !== '')) {
                $quadri->imposta($nome, $valore, 1, false);
                // i valori nulli (es. casella "0") non si conservano
                $importate += $quadri->get($nome, 1) === null ? 0 : 1;
            }
        }
        // tipologia di precalcolo: quella fornita o, se manca, "specifico" (precalcolata dell'ISA applicato)
        if ($quadri->get('IFU00101', 1) === null) {
            $quadri->imposta('IFU00101', 1, 1, false);
        }
        if ($salva) {
            $quadri->salva();
        }
        return $importate;
    }

    public static function isPrecalcolata(string $codice): bool
    {
        return str_starts_with($codice, 'IDF') || str_starts_with($codice, 'IFU');
    }
}
