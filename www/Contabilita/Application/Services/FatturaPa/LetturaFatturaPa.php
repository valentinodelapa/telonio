<?php

namespace Contabilita\Application\Services\FatturaPa;

use Contabilita\Application\Enumerations\ModalitaPagamento;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\TipoDocumentoFattura;

/**
 * Lettura del file XML della fattura elettronica secondo le specifiche
 * tecniche (versione 1.2.x dello schema FatturaPA, tracciati FPR12 e
 * FPA12): si leggono i dati necessari alla registrazione, ignorando il
 * prefisso dello spazio dei nomi (diverso tra i software emittenti). Il
 * file con DOCTYPE è rifiutato (nessuna entità esterna).
 */
class LetturaFatturaPa
{

    private \DOMXPath $xpath;

    /**
     * @throws \UnexpectedValueException se il file non è una fattura elettronica leggibile
     */
    public function leggi(string $xml): DocumentoFatturaPa
    {
        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new \UnexpectedValueException('Il file contiene una dichiarazione DOCTYPE: non è una fattura elettronica valida.');
        }
        $dom = new \DOMDocument();
        $precedente = libxml_use_internal_errors(true);
        $letto = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($precedente);
        if (($letto === false) || ($dom->documentElement === null) || ($dom->documentElement->localName !== 'FatturaElettronica')) {
            throw new \UnexpectedValueException('Il file non è una fattura elettronica (elemento FatturaElettronica assente).');
        }
        $this->xpath = new \DOMXPath($dom);
        $radice = $dom->documentElement;
        $documento = new DocumentoFatturaPa();
        $documento->formato = $radice->getAttribute('versione') ?: null;
        $intestazione = $this->nodo($radice, 'FatturaElettronicaHeader');
        $documento->cedente = $this->leggiSoggetto($this->nodo($intestazione, 'CedentePrestatore'));
        $documento->cessionario = $this->leggiSoggetto($this->nodo($intestazione, 'CessionarioCommittente'));
        $indice = 0;
        foreach ($this->nodi($radice, 'FatturaElettronicaBody') as $corpo) {
            $documento->corpi[] = $this->leggiCorpo($corpo, ++$indice);
        }
        if (count($documento->corpi) === 0) {
            throw new \UnexpectedValueException('La fattura elettronica non contiene documenti (FatturaElettronicaBody).');
        }
        return $documento;
    }

    private function leggiSoggetto(\DOMElement $nodo): SoggettoFattura
    {
        $soggetto = new SoggettoFattura();
        $soggetto->codicePaese = $this->testo($nodo, 'DatiAnagrafici/IdFiscaleIVA/IdPaese');
        $soggetto->partitaIva = $this->testo($nodo, 'DatiAnagrafici/IdFiscaleIVA/IdCodice');
        $soggetto->codiceFiscale = $this->testo($nodo, 'DatiAnagrafici/CodiceFiscale');
        $soggetto->denominazione = $this->testo($nodo, 'DatiAnagrafici/Anagrafica/Denominazione');
        $soggetto->nome = $this->testo($nodo, 'DatiAnagrafici/Anagrafica/Nome');
        $soggetto->cognome = $this->testo($nodo, 'DatiAnagrafici/Anagrafica/Cognome');
        $soggetto->indirizzo = $this->testo($nodo, 'Sede/Indirizzo');
        $soggetto->numeroCivico = $this->testo($nodo, 'Sede/NumeroCivico');
        $soggetto->cap = $this->testo($nodo, 'Sede/CAP');
        $soggetto->comune = $this->testo($nodo, 'Sede/Comune');
        $soggetto->provincia = $this->testo($nodo, 'Sede/Provincia');
        $soggetto->nazione = $this->testo($nodo, 'Sede/Nazione');
        $soggetto->codicePaese = $soggetto->codicePaese === null ? null : strtoupper($soggetto->codicePaese);
        $soggetto->codiceFiscale = $soggetto->codiceFiscale === null ? null : strtoupper($soggetto->codiceFiscale);
        if ($soggetto->getDenominazione() === '') {
            throw new \UnexpectedValueException('Fattura senza denominazione o nome del cedente o del cessionario.');
        }
        return $soggetto;
    }

    private function leggiCorpo(\DOMElement $nodo, int $indice): CorpoFattura
    {
        $corpo = new CorpoFattura();
        $corpo->indice = $indice;
        $generali = 'DatiGenerali/DatiGeneraliDocumento/';
        $tipo = TipoDocumentoFattura::tryFrom((string) $this->testo($nodo, $generali . 'TipoDocumento'));
        $data = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->testo($nodo, $generali . 'Data'));
        $numero = $this->testo($nodo, $generali . 'Numero');
        if (($tipo === null) || ($data === false) || ($numero === null)) {
            throw new \UnexpectedValueException('Documento ' . $indice . ': tipo documento, data o numero mancanti o non validi.');
        }
        $corpo->tipoDocumento = $tipo;
        $corpo->data = $data;
        $corpo->numero = mb_substr($numero, 0, 30);
        $corpo->divisa = strtoupper($this->testo($nodo, $generali . 'Divisa') ?? 'EUR');
        $totale = $this->testo($nodo, $generali . 'ImportoTotaleDocumento');
        $corpo->importoTotale = $totale === null ? null : (float) $totale;
        $corpo->causale = $this->testo($nodo, $generali . 'Causale');
        foreach ($this->nodi($nodo, 'DatiBeniServizi/DatiRiepilogo') as $riepilogo) {
            $naturaTesto = $this->testo($riepilogo, 'Natura');
            $natura = $naturaTesto === null ? null : NaturaIva::daFile($naturaTesto);
            if (($naturaTesto !== null) && ($natura === null)) {
                throw new \UnexpectedValueException('Documento ' . $indice . ': natura ' . $naturaTesto . ' non riconosciuta.');
            }
            $corpo->riepiloghi[] = [
                'aliquota' => (float) ($this->testo($riepilogo, 'AliquotaIVA') ?? 0),
                'natura' => $natura,
                'imponibile' => round((float) ($this->testo($riepilogo, 'ImponibileImporto') ?? 0), 2),
                'imposta' => round((float) ($this->testo($riepilogo, 'Imposta') ?? 0), 2),
                'esigibilita' => EsigibilitaIva::tryFrom((string) $this->testo($riepilogo, 'EsigibilitaIVA')) ?? EsigibilitaIva::immediata,
                'riferimentoNormativo' => $this->testo($riepilogo, 'RiferimentoNormativo'),
            ];
        }
        if (count($corpo->riepiloghi) === 0) {
            throw new \UnexpectedValueException('Documento ' . $indice . ': nessun riepilogo IVA (DatiRiepilogo).');
        }
        foreach ($this->nodi($nodo, 'DatiPagamento') as $datiPagamento) {
            $corpo->condizioniPagamento ??= $this->testo($datiPagamento, 'CondizioniPagamento');
            foreach ($this->nodi($datiPagamento, 'DettaglioPagamento') as $dettaglio) {
                $corpo->pagamenti[] = ['modalita' => ModalitaPagamento::tryFrom((string) $this->testo($dettaglio, 'ModalitaPagamento')),
                    'scadenza' => $this->scadenzaPagamento($dettaglio), 'importo' => round((float) ($this->testo($dettaglio, 'ImportoPagamento') ?? 0), 2),
                    'iban' => $this->testo($dettaglio, 'IBAN')];
            }
        }
        foreach (array_slice(iterator_to_array($this->nodi($nodo, 'DatiBeniServizi/DettaglioLinee')), 0, 3) as $linea) {
            $descrizione = $this->testo($linea, 'Descrizione');
            if ($descrizione !== null) {
                $corpo->descrizioni[] = $descrizione;
            }
        }
        return $corpo;
    }

    /**
     * Scadenza del pagamento: la data indicata o la data di riferimento più
     * i giorni dei termini; null se manca (vale la data del documento).
     */
    private function scadenzaPagamento(\DOMElement $dettaglio): ?\DateTimeImmutable
    {
        $scadenza = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->testo($dettaglio, 'DataScadenzaPagamento'));
        if ($scadenza !== false) {
            return $scadenza;
        }
        $riferimento = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->testo($dettaglio, 'DataRiferimentoTerminiPagamento'));
        $giorni = $this->testo($dettaglio, 'GiorniTerminiPagamento');
        return ($riferimento !== false) && ($giorni !== null) && ctype_digit($giorni) ? $riferimento->modify('+' . (int) $giorni . ' days') : null;
    }

    /**
     * Percorso relativo per nome locale degli elementi (a/b/c).
     */
    private function espressione(string $percorso): string
    {
        return implode('/', array_map(fn(string $nome) => '*[local-name()="' . $nome . '"]', explode('/', $percorso)));
    }

    private function nodi(\DOMElement $contesto, string $percorso): \DOMNodeList
    {
        return $this->xpath->query($this->espressione($percorso), $contesto);
    }

    private function nodo(\DOMElement $contesto, string $percorso): \DOMElement
    {
        $nodo = $this->nodi($contesto, $percorso)->item(0);
        return $nodo instanceof \DOMElement ? $nodo : throw new \UnexpectedValueException('Elemento ' . $percorso . ' assente.');
    }

    private function testo(\DOMElement $contesto, string $percorso): ?string
    {
        $nodo = $this->nodi($contesto, $percorso)->item(0);
        if ($nodo === null) {
            return null;
        }
        $testo = trim($nodo->textContent);
        return $testo === '' ? null : $testo;
    }
}
