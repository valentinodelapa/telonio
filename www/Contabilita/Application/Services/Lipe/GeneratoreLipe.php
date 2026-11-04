<?php

namespace Contabilita\Application\Services\Lipe;

use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Entities\ModuloLipe;
use Contabilita\Application\Models\ModuloLipeModel;
use Contabilita\Application\Services\TabelleRiferimento\SpecificheContabilita;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * File della comunicazione delle liquidazioni periodiche IVA (fornitura
 * IVP18, specifiche tecniche approvate con provvedimento del 14 marzo
 * 2024): XML in UTF-8, validato sullo schema ufficiale prima del
 * download. Il file va firmato (con la firma digitale o il sistema
 * dell'intermediario) e trasmesso con Entratel o dal portale Fatture e
 * corrispettivi. Per l'IVA di gruppo il frontespizio delle società
 * partecipanti indica la partita IVA della controllante (e l'ultimo mese
 * di controllo quando il controllo cessa), quello della comunicazione del
 * gruppo la casella "Liquidazione del gruppo".
 */
class GeneratoreLipe
{

    public const NAMESPACE_IVP = 'urn:www.agenziaentrate.gov.it:specificheTecniche:sco:ivp';
    public const CODICE_FORNITURA = 'IVP18';
    private const CF_PERSONA_FISICA = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/';
    private const CF = '/^([0-9]{11}|[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z])$/';

    private ?\DOMDocument $documento = null;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Controlli bloccanti delle specifiche non coperti dai dati delle
     * liquidazioni.
     *
     * @return string[]
     */
    public function getErrori(ComunicazioneLipe $comunicazione): array
    {
        $errori = [];
        $soggetto = $comunicazione->soggetto;
        if (preg_match(self::CF, (string) $soggetto->codiceFiscale) !== 1) {
            $errori[] = 'Codice fiscale del contribuente assente o non valido.';
        }
        if (preg_match('/^[0-7][0-9]{10}$/', (string) $soggetto->partitaIva) !== 1) {
            $errori[] = 'Partita IVA del contribuente assente o non valida.';
        }
        $dichiarante = $comunicazione->dichiarante;
        if (($dichiarante === null) && ($soggetto->isPersonaFisica() === false)) {
            $errori[] = 'Per i contribuenti diversi dalle persone fisiche indicare il dichiarante (rappresentante che firma) e il codice di carica.';
        }
        if ($dichiarante !== null) {
            if (preg_match(self::CF_PERSONA_FISICA, (string) $dichiarante->codiceFiscale) !== 1) {
                $errori[] = 'Il dichiarante deve essere una persona fisica con codice fiscale di 16 caratteri.';
            }
            if ($comunicazione->codiceCarica === null) {
                $errori[] = 'Indicare il codice di carica del dichiarante.';
            }
        }
        if ($comunicazione->intermediario !== null) {
            if (preg_match(self::CF, (string) $comunicazione->intermediario->soggetto->codiceFiscale) !== 1) {
                $errori[] = 'Codice fiscale dell\'intermediario non valido.';
            }
            if (($comunicazione->impegno === null) || ($comunicazione->dataImpegno === null)) {
                $errori[] = 'Indicare il tipo e la data dell\'impegno alla presentazione telematica.';
            } elseif ($comunicazione->dataImpegno->format('Y-m-d') > date('Y-m-d')) {
                $errori[] = 'La data dell\'impegno non può essere futura.';
            }
        }
        if (count($this->getModuli($comunicazione)) === 0) {
            $errori[] = 'Nessun modulo: preparare la comunicazione dalle liquidazioni.';
        }
        return $errori;
    }

    /**
     * @throws \InvalidArgumentException se la comunicazione non supera i controlli
     * @throws \UnexpectedValueException se il file non è conforme allo schema
     */
    public function genera(ComunicazioneLipe $comunicazione): string
    {
        $errori = $this->getErrori($comunicazione);
        if (count($errori) > 0) {
            throw new \InvalidArgumentException(implode(' ', $errori));
        }
        $this->documento = new \DOMDocument('1.0', 'UTF-8');
        $this->documento->formatOutput = true;
        $fornitura = $this->documento->appendChild($this->documento->createElementNS(self::NAMESPACE_IVP, 'iv:Fornitura'));
        $intestazione = $this->aggiungi($fornitura, 'Intestazione');
        $this->aggiungi($intestazione, 'CodiceFornitura', self::CODICE_FORNITURA);
        $dichiarante = $comunicazione->dichiarante;
        $this->aggiungi($intestazione, 'CodiceFiscaleDichiarante', $dichiarante?->codiceFiscale ?? $comunicazione->soggetto->codiceFiscale);
        $this->aggiungi($intestazione, 'CodiceCarica', $dichiarante === null ? null : $comunicazione->codiceCarica->value);
        $documento = $this->aggiungi($fornitura, 'Comunicazione');
        $documento->setAttribute('identificativo', $comunicazione->getIdentificativo());
        $this->aggiungiFrontespizio($this->aggiungi($documento, 'Frontespizio'), $comunicazione);
        $dati = $this->aggiungi($documento, 'DatiContabili');
        foreach ($this->getModuli($comunicazione) as $modulo) {
            $this->aggiungiModulo($this->aggiungi($dati, 'Modulo'), $modulo);
        }
        $xml = $this->documento->saveXML();
        $errore = self::verifica($xml);
        if ($errore !== null) {
            throw new \UnexpectedValueException('File non conforme allo schema della comunicazione: ' . $errore);
        }
        return $xml;
    }

    /**
     * @return string|null null se il file è conforme allo schema IVP18, altrimenti il primo errore
     */
    public static function verifica(string $xml): ?string
    {
        $precedente = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \DOMDocument();
        $valido = $dom->loadXML($xml, LIBXML_NONET) && $dom->schemaValidate(SpecificheContabilita::getSchemaLipe(), LIBXML_NONET);
        $errori = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);
        return $valido ? null : trim(($errori[0] ?? null)?->message ?? 'file non leggibile');
    }

    /**
     * Nome del file: codice paese e codice fiscale del contribuente,
     * "_LI_" e progressivo della comunicazione.
     */
    public static function getNomeFile(ComunicazioneLipe $comunicazione): string
    {
        return 'IT' . $comunicazione->soggetto->codiceFiscale . '_LI_' . $comunicazione->getIdentificativo() . '.xml';
    }

    /**
     * @return ModuloLipe[]
     */
    public function getModuli(ComunicazioneLipe $comunicazione): array
    {
        return isset($comunicazione->id) ? iterator_to_array((new ModuloLipeModel($this->dataMapper))->getModuli($comunicazione)) : [];
    }

    private function aggiungiFrontespizio(\DOMElement $frontespizio, ComunicazioneLipe $comunicazione): void
    {
        $soggetto = $comunicazione->soggetto;
        $this->aggiungi($frontespizio, 'CodiceFiscale', $soggetto->codiceFiscale);
        $this->aggiungi($frontespizio, 'AnnoImposta', (string) $comunicazione->anno);
        $this->aggiungi($frontespizio, 'PartitaIVA', $soggetto->partitaIva);
        [$partitaIvaControllante, $ultimoMese] = (new PreparazioneLipe($this->dataMapper))->getDatiGruppo($comunicazione, $this->getModuli($comunicazione));
        $this->aggiungi($frontespizio, 'PIVAControllante', $partitaIvaControllante);
        $this->aggiungi($frontespizio, 'UltimoMese', ($ultimoMese ?? 0) < 1 ? null : (string) $ultimoMese);
        $this->aggiungi($frontespizio, 'LiquidazioneGruppo', $comunicazione->gruppoIva === null ? null : '1');
        if ($comunicazione->dichiarante !== null) {
            $this->aggiungi($frontespizio, 'CFDichiarante', $comunicazione->dichiarante->codiceFiscale);
            $this->aggiungi($frontespizio, 'CodiceCaricaDichiarante', $comunicazione->codiceCarica->value);
        }
        $this->aggiungi($frontespizio, 'FirmaDichiarazione', '1');
        if ($comunicazione->intermediario !== null) {
            $this->aggiungi($frontespizio, 'CFIntermediario', $comunicazione->intermediario->soggetto->codiceFiscale);
            $this->aggiungi($frontespizio, 'ImpegnoPresentazione', $comunicazione->impegno->value);
            $this->aggiungi($frontespizio, 'DataImpegno', $comunicazione->dataImpegno->format('dmY'));
            $this->aggiungi($frontespizio, 'FirmaIntermediario', '1');
        }
        $this->aggiungi($frontespizio, 'IdentificativoProdSoftware', 'TELONIO');
    }

    private function aggiungiModulo(\DOMElement $nodo, ModuloLipe $modulo): void
    {
        $this->aggiungi($nodo, 'NumeroModulo', (string) $modulo->numeroModulo);
        $this->aggiungi($nodo, 'Mese', $modulo->mese === null ? null : (string) $modulo->mese);
        $this->aggiungi($nodo, 'Trimestre', $modulo->trimestre === null ? null : (string) $modulo->trimestre);
        // comunicazione del gruppo: righi VP2 e VP3 non compilati
        $gruppo = $modulo->liquidazioneGruppoIva !== null;
        $this->aggiungi($nodo, 'TotaleOperazioniAttive', $gruppo ? null : self::importo($modulo->operazioniAttive, true));
        $this->aggiungi($nodo, 'TotaleOperazioniPassive', $gruppo ? null : self::importo($modulo->operazioniPassive, true));
        $this->aggiungi($nodo, 'IvaEsigibile', self::importo($modulo->ivaEsigibile, true));
        $this->aggiungi($nodo, 'IvaDetratta', self::importo($modulo->ivaDetratta, true));
        $ivaPeriodo = $modulo->getIvaPeriodo();
        $this->aggiungi($nodo, 'IvaDovuta', self::importo(max(0.0, $ivaPeriodo)));
        $this->aggiungi($nodo, 'IvaCredito', self::importo(max(0.0, -$ivaPeriodo)));
        $this->aggiungi($nodo, 'DebitoPrecedente', self::importo($modulo->debitoPrecedente));
        $this->aggiungi($nodo, 'CreditoPeriodoPrecedente', self::importo($modulo->creditoPrecedente));
        $this->aggiungi($nodo, 'CreditoAnnoPrecedente', self::importo($modulo->creditoAnnoPrecedente));
        $this->aggiungi($nodo, 'VersamentiAutoUE', self::importo($modulo->versamentiAuto));
        $this->aggiungi($nodo, 'CreditiImposta', $modulo->isQuartoTrimestreTrimestrali() ? null : self::importo($modulo->creditiImposta));
        $this->aggiungi($nodo, 'InteressiDovuti', self::importo($modulo->interessi));
        if ($modulo->acconto > 0) {
            $this->aggiungi($nodo, 'Metodo', $modulo->metodoAcconto);
            $this->aggiungi($nodo, 'Acconto', self::importo($modulo->acconto));
        }
        if ($modulo->isQuartoTrimestreTrimestrali() === false) {
            $this->aggiungi($nodo, 'ImportoDaVersare', self::importo($modulo->importoDaVersare));
            $this->aggiungi($nodo, 'ImportoACredito', self::importo($modulo->importoACredito));
        }
    }

    /**
     * Importo con la virgola decimale; null (elemento omesso) se zero,
     * salvo i righi da VP2 a VP5, sempre indicati.
     */
    private static function importo(float $valore, bool $relativo = false): ?string
    {
        $valore = round($valore, 2);
        if (($valore == 0) && ($relativo === false)) {
            return null;
        }
        return number_format($valore, 2, ',', '');
    }

    private function aggiungi(\DOMNode $padre, string $nome, ?string $valore = ''): ?\DOMElement
    {
        if ($valore === null) {
            return null;
        }
        $nodo = $this->documento->createElementNS(self::NAMESPACE_IVP, 'iv:' . $nome);
        if ($valore !== '') {
            $nodo->appendChild($this->documento->createTextNode($valore));
        }
        return $padre->appendChild($nodo);
    }
}
