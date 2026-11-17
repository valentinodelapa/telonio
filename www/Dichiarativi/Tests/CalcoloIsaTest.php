<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\Isa\CalcoloIsa;
use Dichiarativi\Application\Services\Isa\PrecalcolateIsa;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Telematico\GeneratoreIsa;
use Telonio\Tests\SuiteTestCase;

/**
 * Modello ISA: record d'ingresso al software di calcolo (specifiche
 * tecniche ISA 2026), importazione delle variabili precalcolate (schema
 * dell'allegato 2.1 al provvedimento del 13 aprile 2026), lettura dei
 * messaggi, del punteggio e dell'esito del software.
 */
class CalcoloIsaTest extends SuiteTestCase
{

    /**
     * @return array{Dichiarazione, QuadriDichiarazione, QuadriDichiarazione}
     */
    private function modello(): array
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 100;
        $soggetto->studio = $this->creaStudio(1);
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'FERRI LUCIA';
        $soggetto->cognome = 'Ferri';
        $soggetto->nome = 'Lucia';
        $soggetto->codiceFiscale = 'FRRLCU78E52H501I';
        $redditi = new Dichiarazione($this->dataMapper);
        $redditi->studio = $soggetto->studio;
        $redditi->soggetto = $soggetto;
        $redditi->modello = ModelloDichiarativo::redditiPf;
        $redditi->anno = 2025;
        $isa = new Dichiarazione($this->dataMapper);
        $isa->studio = $soggetto->studio;
        $isa->soggetto = $soggetto;
        $isa->modello = ModelloDichiarativo::isa;
        $isa->anno = 2025;
        $isa->isa = 'EM01A';
        $isa->dichiarazioneRedditi = $redditi;
        $catalogo = fn(ModelloDichiarativo $modello, array $campi) => array_map(function (string $formato) use ($modello) {
            $campo = new CampoModello($this->dataMapper);
            $campo->modello = $modello;
            $campo->formato = FormatoCampo::from($formato);
            return $campo;
        }, $campi);
        $catalogoIsa = $catalogo(ModelloDichiarativo::isa, ['Y0100101' => 'CA', 'F0100101' => 'VAL11', 'F0102001' => 'VALS11', 'C0100801' => 'PC3', 'IDF00100' => 'CB', 'IDF00101' => 'NUC18', 'IFU00101' => 'CBN3']);
        foreach ($catalogoIsa as $codice => $campo) {
            $campo->codice = $codice;
            $campo->quadro = substr($codice, 0, 1);
        }
        $catalogoRedditi = $catalogo(ModelloDichiarativo::redditiPf, ['RG001001' => 'AN']);
        $catalogoRedditi['RG001001']->codice = 'RG001001';
        $catalogoRedditi['RG001001']->quadro = 'RG';
        $quadri = new QuadriDichiarazione($isa, $this->dataMapper, $catalogoIsa);
        $quadriRedditi = new QuadriDichiarazione($redditi, $this->dataMapper, $catalogoRedditi);
        $quadriRedditi->imposta('RG001001', '47.11.02');
        return [$isa, $quadri, $quadriRedditi];
    }

    public function testRecordDIngresso(): void
    {
        [$isa, $quadri, $redditi] = $this->modello();
        foreach (['Y0100101' => '471102', 'F0100101' => 182000, 'F0102001' => -500, 'C0100801' => 100, 'IFU00101' => 3] as $codice => $valore) {
            $quadri->imposta($codice, $valore);
        }
        $isa->annotazioni = 'Attività ridotta per lavori nel negozio';
        $record = explode("\r\n", rtrim((new GeneratoreIsa($this->dataMapper))->genera($isa, $quadri, $redditi), "\r\n"));
        $this->assertSame(['P', 'Q', 'R'], array_map(fn(string $riga) => $riga[0], $record));
        foreach ($record as $riga) {
            $this->assertSame(1898, strlen($riga));
            $this->assertSame('A', substr($riga, -1));
            // testa: codice fiscale, progressivo modulo, codice attività, anno del modello, progressivo, modello Redditi quadro RG (3), ISA
            $this->assertSame('FRRLCU78E52H501I00000001', substr($riga, 1, 24));
            $this->assertSame('47110220260103EM01A', substr($riga, 89, 19));
        }
        $this->assertSame(str_pad('FERRI', 30) . str_pad('LUCIA', 30), substr($record[0], 108, 60));
        $this->assertSame('TELONIO', trim(substr($record[0], 228, 16)));
        // coppie in ordine di codice, nei formati ISA di 18 caratteri
        $this->assertSame('C0100801' . str_pad('100', 18, ' ', STR_PAD_LEFT) . 'F0100101' . str_pad('00000182000', 18, ' ', STR_PAD_LEFT)
                . 'F0102001' . str_pad('-00000000500', 18, ' ', STR_PAD_LEFT) . 'IFU00101' . str_pad('003', 18, ' ', STR_PAD_LEFT) . 'Y0100101' . str_pad('471102', 18),
                substr($record[1], 108, 5 * 26));
        $this->assertSame("ATTIVITA' RIDOTTA PER LAVORI NEL NEGOZIO", rtrim(substr($record[2], 108, 1768)));
    }

    public function testModelloCpbDellaRevoca(): void
    {
        [$isa, $quadri, $redditi] = $this->modello();
        $catalogo = [];
        foreach (['P0000001' => 'CM', 'P0000002' => 'CA', 'P0000003' => 'NU1'] as $codice => $formato) {
            $campo = new CampoModello($this->dataMapper);
            $campo->modello = ModelloDichiarativo::isa;
            $campo->codice = $codice;
            $campo->quadro = 'P';
            $campo->formato = FormatoCampo::from($formato);
            $catalogo[$codice] = $campo;
        }
        $quadri = new QuadriDichiarazione($isa, $this->dataMapper, array_merge($quadri->getCatalogo(), $catalogo));
        foreach (['Y0100101' => '471102', 'F0100101' => 182000] as $codice => $valore) {
            $quadri->imposta($codice, $valore);
        }
        $isa->annotazioni = 'Nota che non va nella revoca';
        // campi chiave del quadro P: codice ISA, codice attività, tipologia di reddito (impresa con il quadro F)
        $chiavi = \Dichiarativi\Application\Services\Isa\ConcordatoIsa::getChiavi($isa, $quadri);
        $this->assertSame(['P0000001' => 'EM01A', 'P0000002' => '471102', 'P0000003' => 1], $chiavi);
        // modello CPB della revoca con invio autonomo: record P e un record Q con i soli campi chiave, senza note
        $record = explode("\r\n", rtrim((new GeneratoreIsa($this->dataMapper))->genera($isa, $quadri, $redditi, $chiavi), "\r\n"));
        $this->assertSame(['P', 'Q'], array_map(fn(string $riga) => $riga[0], $record));
        $this->assertSame('P0000001' . str_pad('EM01A', 18) . 'P0000002' . str_pad('471102', 18) . 'P0000003' . str_pad('1', 18, ' ', STR_PAD_LEFT),
                rtrim(substr($record[1], 108, 1768)));
    }

    public function testImportazioneDellePrecalcolate(): void
    {
        [$isa, $quadri] = $this->modello();
        $quadri->imposta('IFU00101', 3, 1, false);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Fornitura xmlns="urn:xml.isa.sogei.it"><Riepilogo><CodiceFornitura>1</CodiceFornitura><TotaleDocRichiesti>1</TotaleDocRichiesti></Riepilogo>'
                . '<EsitoRichiesta><CodiceFiscaleRichiesta>FRRLCU78E52H501I</CodiceFiscaleRichiesta><IsaPrecompilato><ISAPrecompilato><Intestazione><Identificativo>X</Identificativo><Anno>2025</Anno></Intestazione>'
                . '<Isa><CodiceFiscale>FRRLCU78E52H501I</CodiceFiscale><CodiceIsa>DM04U</CodiceIsa><TipoReddito>1</TipoReddito><Elementi><Elemento><Nome>IDF00101</Nome><Valore>9,9</Valore></Elemento></Elementi></Isa>'
                . '<Isa><CodiceFiscale>FRRLCU78E52H501I</CodiceFiscale><CodiceIsa>EM01A</CodiceIsa><TipoReddito>1</TipoReddito><Elementi>'
                . '<Elemento><Nome>IDF00101</Nome><Valore>1,0523</Valore></Elemento><Elemento><Nome>IDF00100</Nome><Valore>0</Valore></Elemento><Elemento><Nome>XXX00001</Nome><Valore>5</Valore></Elemento>'
                . '</Elementi></Isa></ISAPrecompilato></IsaPrecompilato><Stato>0</Stato></EsitoRichiesta></Fornitura>';
        $this->assertSame(1, (new PrecalcolateIsa($this->dataMapper))->importa($isa, $xml, $quadri, false));
        // l'ISA della dichiarazione, non gli altri del file; tipologia "specifico" se non fornita
        $this->assertSame('1.0523', $quadri->get('IDF00101'));
        $this->assertNull($quadri->get('IDF00100'));
        $this->assertSame('1', $quadri->get('IFU00101'));
        $isa->isa = 'EG40U';
        $this->expectException(\DomainException::class);
        (new PrecalcolateIsa($this->dataMapper))->importa($isa, $xml, $quadri, false);
    }

    public function testErroriESegnalazioniDelSoftware(): void
    {
        [$isa, $quadri] = $this->modello();
        $isa->reportIsa = "(***C)\r\nRecord tipo Q\r\nCF X - Isa EM01A - Attivita' 471102 - Errore 8419\r\nB04 deve essere compilato\n";
        $controlli = new \Dichiarativi\Application\Services\Isa\ControlliIsa($this->dataMapper);
        // modello non elaborato: il "***C" è un errore
        $this->assertSame(['Il tuo ISA, errore 8419: B04 deve essere compilato'], $controlli->getErrori($isa, $quadri));
        $this->assertSame([], $controlli->getSegnalazioni($quadri));
        // modello elaborato: segnalazione da confermare, con quella delle precalcolate non importate
        $isa->calcoloIsa = 'P';
        $quadri->imposta('IFU00101', 3, 1, false);
        $segnalazioni = $controlli->getSegnalazioni($quadri);
        $this->assertCount(2, $segnalazioni);
        $this->assertSame('Il tuo ISA, errore 8419: B04 deve essere compilato', $segnalazioni[0]);
        $this->assertStringContainsString('precalcolate', $segnalazioni[1]);
        $this->assertNotContains('Il tuo ISA, errore 8419: B04 deve essere compilato', $controlli->getErrori($isa, $quadri));
    }

    public function testConcordatoPreventivoBiennale(): void
    {
        [$isa] = $this->modello();
        $catalogo = [];
        foreach (['P0000001' => 'CM', 'P0000002' => 'CA', 'P0000003' => 'NU1', 'Y0100101' => 'CA', 'F0100101' => 'VAL11', 'P0100101' => 'CB', 'P0100201' => 'CB', 'P0100401' => 'VALS11', 'P0100601' => 'VALC18', 'P0100701' => 'VALC18', 'P0100801' => 'VALC18', 'P0100901' => 'VALC18', 'P0101001' => 'CB'] as $codice => $formato) {
            $campo = new CampoModello($this->dataMapper);
            $campo->modello = ModelloDichiarativo::isa;
            $campo->codice = $codice;
            $campo->quadro = substr($codice, 0, 1);
            $campo->formato = FormatoCampo::from($formato);
            $catalogo[$codice] = $campo;
        }
        $quadri = new QuadriDichiarazione($isa, $this->dataMapper, $catalogo);
        $quadri->imposta('Y0100101', '471102');
        // senza calcolo nessuna proposta e niente adesione
        $this->assertNull(\Dichiarativi\Application\Services\Isa\ConcordatoIsa::getProposta($isa));
        $quadri->imposta('P0100101', 1);
        $quadri->imposta('P0100201', 1);
        // requisiti dichiarati senza l'accettazione: quadro P non ammesso
        $this->assertTrue(\Dichiarativi\Application\Services\Isa\ConcordatoIsa::isSenzaAccettazione($quadri));
        // proposta restituita dal software nei campi P06 e P07
        $isa->calcoloIsa = 'Q' . str_repeat(' ', 107) . 'P0100601' . str_pad('10284,00000000000', 18, ' ', STR_PAD_LEFT) . 'P0100701' . str_pad('21083,00000000000', 18, ' ', STR_PAD_LEFT) . 'P0100801' . str_pad('0', 18, ' ', STR_PAD_LEFT);
        $this->assertSame(['P0100601' => '10284', 'P0100701' => '21083', 'P0100801' => '0'], \Dichiarativi\Application\Services\Isa\ConcordatoIsa::getProposta($isa));
        $concordato = new \Dichiarativi\Application\Services\Isa\ConcordatoIsa($this->dataMapper);
        $concordato->aderisci($isa, $quadri, false);
        $this->assertSame('10284', $quadri->get('P0100601'));
        $this->assertSame('21083', $quadri->get('P0100701'));
        $this->assertNull($quadri->get('P0100801'));
        $this->assertTrue(\Dichiarativi\Application\Services\Isa\ConcordatoIsa::isAccettata($quadri));
        // campi chiave del quadro P: codice ISA, codice attività, tipologia di reddito (impresa)
        $this->assertSame(['EM01A', '471102', '1'], [$quadri->get('P0000001'), $quadri->get('P0000002'), $quadri->get('P0000003')]);
        $this->assertFalse(\Dichiarativi\Application\Services\Isa\ConcordatoIsa::isSenzaAccettazione($quadri));
        // revoca: il quadro P si svuota
        $concordato->rinuncia($isa, $quadri, false);
        $this->assertSame([], array_values(array_filter(array_keys($quadri->getValori()[1] ?? []), fn(string $codice) => str_starts_with($codice, 'P'))));
    }

    public function testAccettazioneNeiRecordRestituiti(): void
    {
        $testa = 'Q' . str_pad('FRRLCU78E52H501I', 16) . '00000001' . str_repeat(' ', 64) . '47110220260103EM01A';
        $record = fn(string $tipo, array $coppie) => str_pad($tipo . substr($testa, 1) . implode('', $coppie), 1876) . str_repeat(' ', 21) . 'A';
        // primo record Q pieno: 66 campi Y, i due del quadro P; poi i risultati
        $coppie = [];
        for ($i = 1; $i <= 66; $i++) {
            $coppie[] = sprintf('Y01%05d', $i) . str_pad('1', 18, ' ', STR_PAD_LEFT);
        }
        $coppie[] = 'P0100601' . str_pad('10284,00000000000', 18, ' ', STR_PAD_LEFT);
        $coppie[] = 'P0100701' . str_pad('21083,00000000000', 18, ' ', STR_PAD_LEFT);
        $elaborato = implode("\r\n", [$record('P', []), $record('Q', $coppie), $record('Q', ['IIISAAFF' . str_pad('09,81', 18, ' ', STR_PAD_LEFT)])]) . "\r\n";
        $righe = array_values(array_filter(explode("\r\n", CalcoloIsa::aggiungiAccettazione($elaborato))));
        $this->assertSame(['P', 'Q', 'Q'], array_map(fn(string $riga) => $riga[0], $righe));
        foreach ($righe as $riga) {
            $this->assertSame(1898, strlen($riga));
        }
        // P10 dopo P07: il primo record resta di 68 coppie, l'ultima coppia Y passa nel secondo con P10 e il punteggio
        $secondo = substr($righe[2], 108, 3 * 26);
        $this->assertSame('P0100701', substr($righe[1], 108 + 67 * 26, 8));
        $this->assertSame('P0101001' . str_pad('1', 18, ' ', STR_PAD_LEFT), substr($secondo, 0, 26));
        $this->assertSame('IIISAAFF', substr($secondo, 26, 8));
        // già presente: invariato
        $this->assertSame(CalcoloIsa::aggiungiAccettazione($elaborato), CalcoloIsa::aggiungiAccettazione(CalcoloIsa::aggiungiAccettazione($elaborato)));
    }

    public function testMessaggiPunteggioEdEsito(): void
    {
        $isa = new Dichiarazione($this->dataMapper);
        $isa->reportIsa = "CF FRRLCU78E52H501I - Isa EM01A - Attivita' 471102\r\nIsa non elaborato.\n\n(***C)\r\nRecord tipo Q\r\nCF FRRLCU78E52H501I - Isa EM01A - Attivita' 471102 - Errore 8419\r\nB04 deve essere compilato\n\n(*)\r\nCF FRRLCU78E52H501I - Isa EM01A - Attivita' 471102 - Errore 9001\r\nSegnalazione di prova\n";
        $this->assertSame([['tipo' => '***C', 'codice' => '8419', 'messaggio' => 'B04 deve essere compilato'], ['tipo' => '*', 'codice' => '9001', 'messaggio' => 'Segnalazione di prova']], CalcoloIsa::getMessaggi($isa));
        $this->assertNull(CalcoloIsa::getPunteggio($isa));
        $isa->calcoloIsa = 'Q' . str_repeat(' ', 107) . 'IIISAAFF' . str_pad('09,81', 18, ' ', STR_PAD_LEFT);
        $this->assertSame('9,81', CalcoloIsa::getPunteggio($isa));
        $isa->esitoIsa = '<?xml version="1.0" encoding="UTF-8"?><esito><quadroIndicatoreSintetico><ISA><codice>IIISAAFF</codice><punteggioString>9,81</punteggioString></ISA>'
                . '<sezioneIndicatoriElementariAffidabilita><listaIndicatoriAffidabilita><codice>IIE00101</codice><descrizione>Ricavi per addetto</descrizione><punteggioString>10,00</punteggioString></listaIndicatoriAffidabilita></sezioneIndicatoriElementariAffidabilita>'
                . '<sezioneIndicatoriElementariAnomalia><elencoGruppiIndicatori><nomeGruppo>Gestione caratteristica</nomeGruppo><listaIndicatori><codice>IIN00301</codice><descrizione>Costo del venduto negativo</descrizione><punteggioString>1,00</punteggioString></listaIndicatori></elencoGruppiIndicatori></sezioneIndicatoriElementariAnomalia>'
                . '</quadroIndicatoreSintetico><quadroProbabilitaAssegnazioneMoB><probabilitaAssegnazioneMobBean><progressivo>MoB 3</progressivo><descrizione>Minimarket</descrizione><valoreFormattato>0,10</valoreFormattato></probabilitaAssegnazioneMobBean>'
                . '<probabilitaAssegnazioneMobBean><progressivo>MoB 1</progressivo><descrizione>Negozi al dettaglio alimentare</descrizione><valoreFormattato>0,90</valoreFormattato></probabilitaAssegnazioneMobBean></quadroProbabilitaAssegnazioneMoB></esito>';
        $esito = CalcoloIsa::getEsito($isa);
        $this->assertSame('9,81', $esito['punteggio']);
        $this->assertSame([['codice' => 'IIE00101', 'descrizione' => 'Ricavi per addetto', 'punteggio' => '10,00']], $esito['affidabilita']);
        $this->assertSame('Costo del venduto negativo', $esito['anomalie'][0]['descrizione']);
        // modello di business più probabile per primo
        $this->assertSame('MoB 1 - Negozi al dettaglio alimentare', $esito['modelliBusiness'][0]['descrizione']);
    }
}
