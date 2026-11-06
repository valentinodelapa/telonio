<?php

namespace Contabilita\Application\Services\FatturaPa;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\IndirizzoModel;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\RigaIvaModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Autofattura dell'acquirente da un produttore agricolo esonerato (art. 34,
 * c. 6, D.P.R. 633/1972): file FatturaPA emesso dal cessionario per conto del
 * cedente (soggetto emittente CC), con la percentuale di compensazione come
 * aliquota (FAQ 62 dell'Agenzia delle Entrate del 19 luglio 2019), validato
 * sullo schema XSD. Il file si carica sul portale: la suite non trasmette al
 * Sistema di Interscambio.
 *
 * Tipo documento TD01 e soggetto emittente CC come nella FAQ e nella prassi
 * di settore; le specifiche tecniche non trattano il caso (terzo intermediario
 * non compilato). Regime del cedente RF04, il codice dello schema per gli
 * artt. 34 e 34-bis.
 */
class GeneratoreAutofattura
{

    private const NAMESPACE_FATTURA = VerificaSchemaFattura::NAMESPACE_FATTURA;
    private const SOGGETTO_EMITTENTE = 'CC';
    private const TIPO_DOCUMENTO = 'TD01';
    private const REGIME_CEDENTE = 'RF04';
    private const CODICE_DESTINATARIO_ASSENTE = '0000000';
    private const CAUSALE = 'Autofattura emessa per acquisti da soggetti esonerati a norma dell\'art. 34, 6 c., DPR 633/72';

    private \DOMDocument $documento;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public static function isAmmessa(RegistrazioneContabile $registrazione): bool
    {
        return ($registrazione->causale->getTipoRegistroIva() === TipoRegistroIva::acquisti) && ($registrazione->controparte?->agricoltoreEsonerato === true);
    }

    public static function getNomeFile(RegistrazioneContabile $registrazione): string
    {
        return 'IT' . $registrazione->soggetto->codiceFiscale . '_' . self::getProgressivo($registrazione) . '.xml';
    }

    /**
     * @throws \InvalidArgumentException dati mancanti o file non conforme
     */
    public function genera(RegistrazioneContabile $registrazione): string
    {
        return $this->componi($registrazione, iterator_to_array((new RigaIvaModel($this->dataMapper))->getRighe($registrazione), false));
    }

    /**
     * @param RigaIva[] $righe
     */
    public function componi(RegistrazioneContabile $registrazione, array $righe): string
    {
        if (self::isAmmessa($registrazione) === false) {
            throw new \InvalidArgumentException('la controparte non è indicata come agricoltore esonerato.');
        }
        if (($registrazione->numeroDocumento === null) || ($registrazione->dataDocumento === null)) {
            throw new \InvalidArgumentException('indicare numero e data dell\'autofattura nella registrazione.');
        }
        if (count($righe) === 0) {
            throw new \InvalidArgumentException('la registrazione non ha righe IVA.');
        }
        $this->documento = new \DOMDocument('1.0', 'UTF-8');
        $this->documento->formatOutput = true;
        $radice = $this->documento->createElementNS(self::NAMESPACE_FATTURA, 'p:FatturaElettronica');
        $radice->setAttribute('versione', 'FPR12');
        $this->documento->appendChild($radice);
        $this->aggiungiIntestazione($this->figlio($radice, 'FatturaElettronicaHeader'), $registrazione);
        $this->aggiungiCorpo($this->figlio($radice, 'FatturaElettronicaBody'), $registrazione, $righe);
        $xml = (string) $this->documento->saveXML();
        $errore = (new VerificaSchemaFattura())->verifica($xml);
        if ($errore !== null) {
            throw new \InvalidArgumentException('file non conforme allo schema FatturaPA (' . $errore . ').');
        }
        return $xml;
    }

    private static function getProgressivo(RegistrazioneContabile $registrazione): string
    {
        return strtoupper(str_pad(base_convert((string) $registrazione->id, 10, 36), 5, '0', STR_PAD_LEFT));
    }

    private function aggiungiIntestazione(\DOMElement $intestazione, RegistrazioneContabile $registrazione): void
    {
        $soggetto = $registrazione->soggetto;
        $controparte = $registrazione->controparte;
        $trasmissione = $this->figlio($intestazione, 'DatiTrasmissione');
        $this->aggiungiIdFiscale($trasmissione, 'IdTrasmittente', $soggetto->codiceFiscale);
        $this->figlio($trasmissione, 'ProgressivoInvio', self::getProgressivo($registrazione));
        $this->figlio($trasmissione, 'FormatoTrasmissione', 'FPR12');
        $this->figlio($trasmissione, 'CodiceDestinatario', $controparte->codiceDestinatario ?? self::CODICE_DESTINATARIO_ASSENTE);
        if ($controparte->pec !== null) {
            $this->figlio($trasmissione, 'PECDestinatario', $controparte->pec);
        }
        $this->aggiungiCedente($this->figlio($intestazione, 'CedentePrestatore'), $controparte);
        $this->aggiungiCessionario($this->figlio($intestazione, 'CessionarioCommittente'), $soggetto, $registrazione);
        $this->figlio($intestazione, 'SoggettoEmittente', self::SOGGETTO_EMITTENTE);
    }

    private function aggiungiCedente(\DOMElement $cedente, Controparte $controparte): void
    {
        $identificativo = $controparte->partitaIva ?? $controparte->codiceFiscale;
        if ($identificativo === null) {
            throw new \InvalidArgumentException('indicare la partita IVA o il codice fiscale dell\'agricoltore.');
        }
        if (($controparte->indirizzo === null) || ($controparte->cap === null) || ($controparte->comune === null)) {
            throw new \InvalidArgumentException('indicare indirizzo, CAP e comune dell\'agricoltore nella controparte.');
        }
        $anagrafici = $this->figlio($cedente, 'DatiAnagrafici');
        $this->aggiungiIdFiscale($anagrafici, 'IdFiscaleIVA', $identificativo);
        if ($controparte->codiceFiscale !== null) {
            $this->figlio($anagrafici, 'CodiceFiscale', $controparte->codiceFiscale);
        }
        $this->figlio($this->figlio($anagrafici, 'Anagrafica'), 'Denominazione', $controparte->denominazione);
        $this->figlio($anagrafici, 'RegimeFiscale', self::REGIME_CEDENTE);
        $this->aggiungiSede($cedente, $controparte->indirizzo, $controparte->cap, $controparte->comune, $controparte->provincia);
    }

    private function aggiungiCessionario(\DOMElement $cessionario, Soggetto $soggetto, RegistrazioneContabile $registrazione): void
    {
        $anagrafici = $this->figlio($cessionario, 'DatiAnagrafici');
        if ($soggetto->partitaIva !== null) {
            $this->aggiungiIdFiscale($anagrafici, 'IdFiscaleIVA', $soggetto->partitaIva);
        }
        $this->figlio($anagrafici, 'CodiceFiscale', $soggetto->codiceFiscale);
        $this->figlio($this->figlio($anagrafici, 'Anagrafica'), 'Denominazione', $soggetto->nominativo);
        $sede = $this->getSede($soggetto, $registrazione);
        $via = trim($sede->indirizzo . ' ' . ($sede->numeroCivico ?? ''));
        $this->aggiungiSede($cessionario, $via, (string) $sede->cap, (string) $sede->comune?->denominazione, $sede->comune?->siglaProvincia);
    }

    private function getSede(Soggetto $soggetto, RegistrazioneContabile $registrazione): Indirizzo
    {
        $indirizzi = [];
        foreach ((new IndirizzoModel($this->dataMapper))->getVigentiAl($soggetto, $registrazione->dataDocumento) as $indirizzo) {
            $indirizzi[$indirizzo->tipoIndirizzo->value] = $indirizzo;
        }
        foreach ([TipoIndirizzo::sedeLegale, TipoIndirizzo::domicilioFiscale, TipoIndirizzo::residenza, TipoIndirizzo::sedeOperativa] as $tipo) {
            if (isset($indirizzi[$tipo->value]) && ($indirizzi[$tipo->value]->cap !== null) && ($indirizzi[$tipo->value]->comune !== null)) {
                return $indirizzi[$tipo->value];
            }
        }
        throw new \InvalidArgumentException('manca la sede dell\'azienda nell\'Anagrafica.');
    }

    private function aggiungiSede(\DOMElement $padre, string $indirizzo, string $cap, string $comune, ?string $provincia): void
    {
        $sede = $this->figlio($padre, 'Sede');
        $this->figlio($sede, 'Indirizzo', $indirizzo);
        $this->figlio($sede, 'CAP', $cap);
        $this->figlio($sede, 'Comune', $comune);
        if ($provincia !== null) {
            $this->figlio($sede, 'Provincia', $provincia);
        }
        $this->figlio($sede, 'Nazione', 'IT');
    }

    /**
     * @param RigaIva[] $righe
     */
    private function aggiungiCorpo(\DOMElement $corpo, RegistrazioneContabile $registrazione, array $righe): void
    {
        $totale = array_sum(array_map(fn(RigaIva $riga) => $riga->imponibile + $riga->imposta, $righe));
        $generali = $this->figlio($this->figlio($corpo, 'DatiGenerali'), 'DatiGeneraliDocumento');
        $this->figlio($generali, 'TipoDocumento', self::TIPO_DOCUMENTO);
        $this->figlio($generali, 'Divisa', 'EUR');
        $this->figlio($generali, 'Data', $registrazione->dataDocumento->format('Y-m-d'));
        $this->figlio($generali, 'Numero', $registrazione->numeroDocumento);
        $this->figlio($generali, 'ImportoTotaleDocumento', self::importo($totale));
        $this->figlio($generali, 'Causale', self::CAUSALE);
        $beni = $this->figlio($corpo, 'DatiBeniServizi');
        $riepilogo = [];
        foreach ($righe as $indice => $riga) {
            if ($riga->codiceIva->natura !== null) {
                throw new \InvalidArgumentException('l\'autofattura richiede un codice IVA con aliquota o percentuale di compensazione.');
            }
            $aliquota = self::importo($riga->codiceIva->aliquota);
            $linea = $this->figlio($beni, 'DettaglioLinee');
            $this->figlio($linea, 'NumeroLinea', (string) ($indice + 1));
            $this->figlio($linea, 'Descrizione', $registrazione->descrizione ?? 'Acquisto di prodotti agricoli');
            $this->figlio($linea, 'PrezzoUnitario', self::importo($riga->imponibile));
            $this->figlio($linea, 'PrezzoTotale', self::importo($riga->imponibile));
            $this->figlio($linea, 'AliquotaIVA', $aliquota);
            $riepilogo[$aliquota]['imponibile'] = ($riepilogo[$aliquota]['imponibile'] ?? 0.0) + $riga->imponibile;
            $riepilogo[$aliquota]['imposta'] = ($riepilogo[$aliquota]['imposta'] ?? 0.0) + $riga->imposta;
        }
        foreach ($riepilogo as $aliquota => $importi) {
            $dati = $this->figlio($beni, 'DatiRiepilogo');
            $this->figlio($dati, 'AliquotaIVA', (string) $aliquota);
            $this->figlio($dati, 'ImponibileImporto', self::importo($importi['imponibile']));
            $this->figlio($dati, 'Imposta', self::importo($importi['imposta']));
            $this->figlio($dati, 'EsigibilitaIVA', 'I');
        }
    }

    private function aggiungiIdFiscale(\DOMElement $padre, string $nome, string $codice): void
    {
        $id = $this->figlio($padre, $nome);
        $this->figlio($id, 'IdPaese', 'IT');
        $this->figlio($id, 'IdCodice', $codice);
    }

    private function figlio(\DOMElement $padre, string $nome, ?string $valore = null): \DOMElement
    {
        $nodo = $this->documento->createElementNS('', $nome);
        if ($valore !== null) {
            $nodo->appendChild($this->documento->createTextNode($valore));
        }
        $padre->appendChild($nodo);
        return $nodo;
    }

    private static function importo(float $valore): string
    {
        return number_format($valore, 2, '.', '');
    }
}
