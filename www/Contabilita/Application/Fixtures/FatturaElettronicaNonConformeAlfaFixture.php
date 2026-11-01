<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Services\FatturaPa\ImportazioneFatture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fattura elettronica demo ricevuta da Alfa e non conforme allo schema
 * (manca il regime fiscale del cedente, obbligatorio): importata con
 * l'avviso, ancora da registrare.
 */
class FatturaElettronicaNonConformeAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(EsercizioAlfa2026Fixture::class)
                ->addDependency(FatturaElettronicaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $esito = (new ImportazioneFatture($this->dataMapper))->importa($this->getEntityByFixtureName(SoggettoAlfaFixture::class), 'IT06234561007_00031.xml', self::XML);
        $this->addEntity($esito->fatture[0] ?? throw new \RuntimeException('Fattura elettronica demo non importata: ' . implode(' ', $esito->errori)));
    }

    private const XML = '<?xml version="1.0" encoding="UTF-8"?><p:FatturaElettronica versione="FPR12" xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2"><FatturaElettronicaHeader><DatiTrasmissione><IdTrasmittente><IdPaese>IT</IdPaese><IdCodice>06234561007</IdCodice></IdTrasmittente><ProgressivoInvio>00031</ProgressivoInvio><FormatoTrasmissione>FPR12</FormatoTrasmissione><CodiceDestinatario>0000000</CodiceDestinatario></DatiTrasmissione><CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>06234561007</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>Zeta Pulizie S.r.l.</Denominazione></Anagrafica></DatiAnagrafici><Sede><Indirizzo>Via Napoli</Indirizzo><NumeroCivico>21</NumeroCivico><CAP>00184</CAP><Comune>Roma</Comune><Provincia>RM</Provincia><Nazione>IT</Nazione></Sede></CedentePrestatore><CessionarioCommittente><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01234567897</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>Alfa Consulting S.r.l.</Denominazione></Anagrafica></DatiAnagrafici><Sede><Indirizzo>Via del Corso 1</Indirizzo><CAP>00186</CAP><Comune>Roma</Comune><Provincia>RM</Provincia><Nazione>IT</Nazione></Sede></CessionarioCommittente></FatturaElettronicaHeader><FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>TD01</TipoDocumento><Divisa>EUR</Divisa><Data>2026-03-05</Data><Numero>31/2026</Numero><ImportoTotaleDocumento>183.00</ImportoTotaleDocumento></DatiGeneraliDocumento></DatiGenerali><DatiBeniServizi><DettaglioLinee><NumeroLinea>1</NumeroLinea><Descrizione>Pulizia uffici marzo</Descrizione><PrezzoTotale>150.00</PrezzoTotale><AliquotaIVA>22.00</AliquotaIVA></DettaglioLinee><DatiRiepilogo><AliquotaIVA>22.00</AliquotaIVA><ImponibileImporto>150.00</ImponibileImporto><Imposta>33.00</Imposta><EsigibilitaIVA>I</EsigibilitaIVA></DatiRiepilogo></DatiBeniServizi></FatturaElettronicaBody></p:FatturaElettronica>';
}
