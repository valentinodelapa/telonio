<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\DirezioneFattura;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\StatoFatturaElettronica;
use Contabilita\Application\Enumerations\TipoDocumentoFattura;
use Contabilita\Application\Services\FatturaPa\EstrazioneP7m;
use Contabilita\Application\Services\FatturaPa\ImportazioneFatture;
use Contabilita\Application\Services\FatturaPa\LetturaFatturaPa;
use Contabilita\Application\Services\FatturaPa\PropostaDaFattura;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

class FatturaPaTest extends ContabilitaTestCase
{

    public static function xml(string $cedente = '04123451009', string $cessionario = '01234567897', string $tipo = 'TD01', string $riepiloghi = ''): string
    {
        $riepiloghi = $riepiloghi !== '' ? $riepiloghi
                : '<DatiRiepilogo><AliquotaIVA>22.00</AliquotaIVA><ImponibileImporto>1000.00</ImponibileImporto><Imposta>220.00</Imposta><EsigibilitaIVA>I</EsigibilitaIVA></DatiRiepilogo>'
                . '<DatiRiepilogo><AliquotaIVA>0.00</AliquotaIVA><Natura>N6.3</Natura><ImponibileImporto>100.00</ImponibileImporto><Imposta>0.00</Imposta></DatiRiepilogo>';
        return '<?xml version="1.0" encoding="UTF-8"?><p:FatturaElettronica versione="FPR12" xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2">'
                . '<FatturaElettronicaHeader><CedentePrestatore><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>' . $cedente . '</IdCodice></IdFiscaleIVA>'
                . '<Anagrafica><Denominazione>Delta Edilizia S.r.l.</Denominazione></Anagrafica></DatiAnagrafici><Sede><Indirizzo>Via Napoli</Indirizzo><NumeroCivico>3</NumeroCivico><CAP>80100</CAP><Comune>Napoli</Comune><Provincia>NA</Provincia><Nazione>IT</Nazione></Sede></CedentePrestatore>'
                . '<CessionarioCommittente><DatiAnagrafici><IdFiscaleIVA><IdPaese>IT</IdPaese><IdCodice>' . $cessionario . '</IdCodice></IdFiscaleIVA><Anagrafica><Nome>Mario</Nome><Cognome>Bianchi</Cognome></Anagrafica></DatiAnagrafici></CessionarioCommittente></FatturaElettronicaHeader>'
                . '<FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>' . $tipo . '</TipoDocumento><Divisa>EUR</Divisa><Data>2026-02-05</Data><Numero>45/2026</Numero><ImportoTotaleDocumento>1320.00</ImportoTotaleDocumento></DatiGeneraliDocumento></DatiGenerali>'
                . '<DatiBeniServizi><DettaglioLinee><NumeroLinea>1</NumeroLinea><Descrizione>Manutenzione</Descrizione><PrezzoTotale>1000.00</PrezzoTotale><AliquotaIVA>22.00</AliquotaIVA></DettaglioLinee>' . $riepiloghi . '</DatiBeniServizi></FatturaElettronicaBody>'
                . '<FatturaElettronicaBody><DatiGenerali><DatiGeneraliDocumento><TipoDocumento>TD04</TipoDocumento><Data>2026-02-06</Data><Numero>46/2026</Numero></DatiGeneraliDocumento></DatiGenerali>'
                . '<DatiBeniServizi><DatiRiepilogo><AliquotaIVA>22.00</AliquotaIVA><ImponibileImporto>10.00</ImponibileImporto><Imposta>2.20</Imposta><EsigibilitaIVA>S</EsigibilitaIVA></DatiRiepilogo></DatiBeniServizi></FatturaElettronicaBody>'
                . '</p:FatturaElettronica>';
    }

    public function testLetturaDelLotto(): void
    {
        $documento = (new LetturaFatturaPa())->leggi(self::xml());
        $this->assertSame('FPR12', $documento->formato);
        $this->assertSame('Delta Edilizia S.r.l.', $documento->cedente->getDenominazione());
        $this->assertSame('Bianchi Mario', $documento->cessionario->getDenominazione());
        $this->assertTrue($documento->cessionario->corrisponde('01234567897', null));
        $this->assertFalse($documento->cedente->corrisponde('01234567897', null));
        $this->assertCount(2, $documento->corpi);
        $corpo = $documento->getCorpo(1);
        $this->assertSame(TipoDocumentoFattura::fattura, $corpo->tipoDocumento);
        $this->assertSame('2026-02-05', $corpo->data->format('Y-m-d'));
        $this->assertSame(1320.0, $corpo->importoTotale);
        $this->assertSame(1320.0, $corpo->getTotale());
        $this->assertSame(NaturaIva::inversioneSubappaltoEdile, $corpo->riepiloghi[1]['natura']);
        $this->assertSame(['Manutenzione'], $corpo->descrizioni);
        $this->assertSame(EsigibilitaIva::scissionePagamenti, $documento->getCorpo(2)->getEsigibilita());
        $this->assertNull($documento->getCorpo(3));
    }

    public function testFileNonValidi(): void
    {
        foreach (['<a/>', '<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><p:FatturaElettronica/>', 'non xml', str_replace('<TipoDocumento>TD01', '<TipoDocumento>TD99', self::xml())] as $contenuto) {
            try {
                (new LetturaFatturaPa())->leggi($contenuto);
                $this->fail('File accettato: ' . substr($contenuto, 0, 30));
            } catch (\UnexpectedValueException $errore) {
                $this->assertNotSame('', $errore->getMessage());
            }
        }
    }

    /**
     * File firmati con un certificato di prova (openssl cms -sign): DER a
     * lunghezza definita e BER a lunghezza indefinita (-stream) codificato
     * in base64.
     */
    public function testEstrazioneDallaBustaFirmata(): void
    {
        $estrazione = new EstrazioneP7m();
        $xml = self::xml();
        $this->assertSame($xml, $estrazione->estrai("\xEF\xBB\xBF" . $xml));
        foreach (['fattura-der', 'lotto-ber-base64'] as $nome) {
            $atteso = file_get_contents(__DIR__ . '/File/' . $nome . '.xml');
            $this->assertSame($atteso, $estrazione->estrai(file_get_contents(__DIR__ . '/File/' . $nome . '.xml.p7m')), $nome);
        }
        $der = file_get_contents(__DIR__ . '/File/fattura-der.xml.p7m');
        $this->assertSame("\x30", $der[0]);
        $this->expectException(\UnexpectedValueException::class);
        $estrazione->estrai(substr($der, 0, 40));
    }

    public function testEstrazioneBerCostruitaAdLunghezzaIndefinita(): void
    {
        // ContentInfo con SignedData ed eContent in due OCTET STRING, codifica BER indefinita
        $ottetti = "\x24\x80" . "\x04\x03" . '<a>' . "\x04\x04" . '</a>' . "\x00\x00";
        $encap = "\x30\x80" . "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x07\x01" . "\xA0\x80" . $ottetti . "\x00\x00" . "\x00\x00";
        $signedData = "\x30\x80" . "\x02\x01\x01" . "\x31\x00" . $encap . "\x00\x00";
        $der = "\x30\x80" . "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x07\x02" . "\xA0\x80" . $signedData . "\x00\x00" . "\x00\x00";
        $this->assertSame('<a></a>', (new EstrazioneP7m())->estrai($der));
    }

    public function testImportazioneEProposta(): void
    {
        $nazione = new \Anagrafica\Application\Entities\Nazione($this->dataMapper);
        $this->rispondiConti(['registro_iva' => [$this->registri['A']], 'esercizio_contabile' => [$this->esercizio], 'codice_iva' => array_values($this->codiciIva)]);
        $esito = (new ImportazioneFatture($this->dataMapper))->importa($this->azienda, 'IT04123451009_1.xml', self::xml());
        $this->assertSame([], $esito->errori);
        $this->assertCount(2, $esito->fatture);
        $this->assertSame(['Delta Edilizia S.r.l.'], $esito->nuoveControparti);
        $fattura = $esito->fatture[0];
        $this->assertSame(DirezioneFattura::ricevuta, $fattura->direzione);
        $this->assertSame(StatoFatturaElettronica::daRegistrare, $fattura->stato);
        $this->assertTrue($fattura->controparte->fornitore);
        $this->assertSame('Via Napoli 3', $fattura->controparte->indirizzo);
        $this->assertSame(TipoDocumentoFattura::notaCredito, $esito->fatture[1]->tipoDocumento);
        $this->assertNull($esito->fatture[1]->avvisi, 'senza totale del documento nessuna differenza da segnalare');
        $this->assertSame($fattura->controparte, $esito->fatture[1]->controparte, 'una sola controparte per il lotto');

        $fattura->controparte->contoFornitore = $this->conti['13.01.0001'];
        $proposta = new PropostaDaFattura($this->dataMapper);
        [$registrazione, $righeIva] = $proposta->proponi($fattura);
        $this->assertSame(CausaleContabile::fatturaAcquisto, $registrazione->causale);
        $this->assertSame('45/2026', $registrazione->numeroDocumento);
        $this->assertSame($this->registri['A'], $registrazione->registroIva);
        $this->assertSame(1320.0, $registrazione->totaleDocumento);
        $this->assertSame(['22', 'RC22'], array_map(fn($riga) => $riga->codiceIva->codice, $righeIva));
        $this->assertSame(22.0, $righeIva[1]->imposta, 'integrazione proposta al 22%');
        $this->assertCount(1, $proposta->avvisi);

        $nota = $esito->fatture[1];
        [$registrazione, $righeIva] = $proposta->proponi($nota);
        $this->assertSame(CausaleContabile::notaCreditoRicevuta, $registrazione->causale);
        $this->assertSame(-10.0, $righeIva[0]->imponibile);
        $this->assertSame(-2.2, $righeIva[0]->imposta);
    }

    public function testImportazioneScartaLeFattureDiAltri(): void
    {
        $this->rispondiConti();
        $esito = (new ImportazioneFatture($this->dataMapper))->importa($this->azienda, 'altra.xml', self::xml('04123451009', '02812341002'));
        $this->assertCount(0, $esito->fatture);
        $this->assertStringContainsString('non riguarda l\'azienda', $esito->errori[0]);
        $esito = (new ImportazioneFatture($this->dataMapper))->importa($this->azienda, 'rotto.xml.p7m', 'abc');
        $this->assertCount(1, $esito->errori);
    }

    public function testImportazioneDaArchivioZipConDoppioni(): void
    {
        $this->testAdapter->impostaSelectCallback(fn(string $cmd): TestResultSet => str_contains($cmd, 'COUNT(') ? TestResultSet::conteggio(str_contains($cmd, 'fattura_elettronica') ? 1 : 0) : new TestResultSet([]));
        $file = tempnam(sys_get_temp_dir(), 'zip');
        $archivio = new \ZipArchive();
        $archivio->open($file, \ZipArchive::OVERWRITE);
        $archivio->addFromString('IT04123451009_1.xml', self::xml());
        $archivio->addFromString('IT04123451009_1_metaDato.xml', '<x/>');
        $archivio->close();
        $esito = (new ImportazioneFatture($this->dataMapper))->importa($this->azienda, 'fatture.zip', file_get_contents($file));
        unlink($file);
        $this->assertSame([], $esito->errori);
        $this->assertCount(2, $esito->doppie, 'le due fatture del lotto risultano già importate');
    }

    public function testFatturaEmessaConNaturaEScissione(): void
    {
        $this->rispondiConti(['registro_iva' => [$this->registri['V']], 'esercizio_contabile' => [$this->esercizio], 'codice_iva' => array_values($this->codiciIva)]);
        $fattura = new FatturaElettronica($this->dataMapper);
        $fattura->studio = $this->studioPredefinito;
        $fattura->soggetto = $this->azienda;
        $fattura->direzione = DirezioneFattura::emessa;
        $fattura->tipoDocumento = TipoDocumentoFattura::fattura;
        $fattura->numero = '3';
        $fattura->data = new SismaDate('2026-02-20');
        $fattura->denominazioneControparte = 'Comune';
        $fattura->contenuto = self::xml('01234567897', '80012345678', 'TD01',
                '<DatiRiepilogo><AliquotaIVA>22.00</AliquotaIVA><ImponibileImporto>500.00</ImponibileImporto><Imposta>110.00</Imposta><EsigibilitaIVA>S</EsigibilitaIVA></DatiRiepilogo>'
                . '<DatiRiepilogo><AliquotaIVA>0.00</AliquotaIVA><Natura>N4</Natura><ImponibileImporto>50.00</ImponibileImporto><Imposta>0.00</Imposta></DatiRiepilogo>');
        [$registrazione, $righeIva] = (new PropostaDaFattura($this->dataMapper))->proponi($fattura);
        $this->assertSame(CausaleContabile::fatturaVendita, $registrazione->causale);
        $this->assertSame(EsigibilitaIva::scissionePagamenti, $registrazione->esigibilita);
        $this->assertSame('2026-02-20', $registrazione->dataCompetenzaIva->format('Y-m-d'));
        $this->assertSame(['22', 'N4'], array_map(fn($riga) => $riga->codiceIva->codice, $righeIva));
        $this->assertSame(660.0, $registrazione->totaleDocumento);
    }
}
