<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Services\FatturaPa\ImportazioneFatture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fattura elettronica demo ricevuta da Alfa e ancora da registrare: il
 * fornitore (Epsilon) è creato dall'importazione.
 */
class FatturaElettronicaAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(EsercizioAlfa2026Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $esito = (new ImportazioneFatture($this->dataMapper))->importa($this->getEntityByFixtureName(SoggettoAlfaFixture::class), 'IT05123451006_00017.xml', self::XML);
        $this->addEntity($esito->fatture[0] ?? throw new \RuntimeException('Fattura elettronica demo non importata: ' . implode(' ', $esito->errori)));
    }

    private const XML = '<?xml version="1.0" encoding="UTF-8"?><p:FatturaElettronica versione="FPR12" xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2"><FatturaElettronicaHeader><DatiTrasmissione><IdTrasmittente><IdPaese>IT</IdPaese><IdCodice>05123451006</IdCodice></IdTrasmittente><ProgressivoInvio>00017</ProgressivoInvio><FormatoTrasmissione>FPR12</FormatoTrasmissione><CodiceDestinatario>0000000</CodiceDestinatario></DatiTrasmissione><CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>05123451006</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>Epsilon Forniture Ufficio S.r.l.</Denominazione></Anagrafica><RegimeFiscale>RF01</RegimeFiscale></DatiAnagrafici><Sede><Indirizzo>Via Torino</Indirizzo><NumeroCivico>8</NumeroCivico><CAP>20123</CAP><Comune>Milano</Comune><Provincia>MI</Provincia><Nazione>IT</Nazione></Sede></CedentePrestatore><CessionarioCommittente><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>01234560785</IdCodice></IdFiscaleIVA><Anagrafica><Denominazione>Alfa Consulting S.r.l.</Denominazione></Anagrafica></DatiAnagrafici><Sede><Indirizzo>Via del Corso 1</Indirizzo><CAP>00186</CAP><Comune>Roma</Comune><Provincia>RM</Provincia><Nazione>IT</Nazione></Sede></CessionarioCommittente></FatturaElettronicaHeader><FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>TD01</TipoDocumento><Divisa>EUR</Divisa><Data>2026-02-03</Data><Numero>FE/117</Numero><ImportoTotaleDocumento>366.00</ImportoTotaleDocumento></DatiGeneraliDocumento></DatiGenerali><DatiBeniServizi><DettaglioLinee><NumeroLinea>1</NumeroLinea><Descrizione>Cancelleria e toner</Descrizione><Quantita>1.00</Quantita><PrezzoUnitario>300.00</PrezzoUnitario><PrezzoTotale>300.00</PrezzoTotale><AliquotaIVA>22.00</AliquotaIVA></DettaglioLinee><DatiRiepilogo><AliquotaIVA>22.00</AliquotaIVA><ImponibileImporto>300.00</ImponibileImporto><Imposta>66.00</Imposta><EsigibilitaIVA>I</EsigibilitaIVA></DatiRiepilogo></DatiBeniServizi></FatturaElettronicaBody></p:FatturaElettronica>';
}
