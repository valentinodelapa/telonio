<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\Irap\AliquoteIrap;
use Dichiarativi\Application\Services\Irap\PropostaIrap;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\TabelleRiferimento\LettoreSpecificheIrap;
use Dichiarativi\Application\Services\TabelleRiferimento\SchemaXsd;
use Dichiarativi\Application\Services\TabelleRiferimento\SpecificheDichiarativi;
use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;
use Dichiarativi\Application\Services\Telematico\GeneratoreIrap;
use Dichiarativi\Application\Services\Telematico\ScritturaXml;

/**
 * Modello IRAP 2026: campi dalle specifiche con il percorso XML (schema
 * XSD ufficiale tra le sorgenti), scrittura del file nell'ordine dello
 * schema, valore della produzione, deduzioni e acconti.
 */
class IrapTest extends DichiarativiTestCase
{

    private function getSchema(): SchemaXsd
    {
        return new SchemaXsd(TabelleDichiarativi::CARTELLA . 'xsd-IRA-2026');
    }

    public function testCampiDalleSpecificheEDalloSchema(): void
    {
        $testo = "DatiContabili\n"
                . "   QuadroIC                                               QuadroIC_Type\n"
                . "        RicaviVendite                   IC001001     DatoNP_Type\n"
                . "        VariazioniRimanenzeProdotti     IC002001     DatoNU_Type\n"
                // codice sulla riga dopo il tag (nome lungo) e tag sulla riga prima del codice
                . "             Eccedenza                                         controlli\n"
                . "                                          IR023001     DatoNP_Type\n"
                . "               CodiceAltraVariazioneAumento    IC051001     DatoN2_Type\n"
                . "               ImportoAltraVariazioneAumento   IC051002     DatoNP_Type\n"
                . "               CodiceAltraVariazioneAumento    IC051003     DatoN2_Type\n";
        $lettore = new LettoreSpecificheIrap();
        $campi = $lettore->leggi($testo, $this->getSchema(), SpecificheDichiarativi::NAMESPACE_IRAP);
        $this->assertSame('DatiContabili/QuadroIC/PrimoModulo/ImpreseIndustriali/ComponentiPositivi/RicaviVendite', $campi['IC001001']['percorso']);
        $this->assertSame('NP', $campi['IC001001']['formato']);
        $this->assertSame('NU', $campi['IC002001']['formato']);
        $this->assertSame('DatiContabili/QuadroIR/PrimoModulo/VersamentoImposta/Eccedenza', $campi['IR023001']['percorso']);
        // righe ripetute: un codice per occorrenza
        $this->assertStringEndsWith('AltreVariazioniAumento[1]/CodiceAltraVariazioneAumento', $campi['IC051001']['percorso']);
        $this->assertStringEndsWith('AltreVariazioniAumento[2]/CodiceAltraVariazioneAumento', $campi['IC051003']['percorso']);
        $this->assertSame('Ricavi vendite (componenti positivi)', $campi['IC001001']['descrizione']);
    }

    public function testFileValidoPerLoSchema(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = ModelloDichiarativo::irap;
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper, $this->getCatalogo(ModelloDichiarativo::irap, 2026));
        foreach (['IC000001' => 1, 'IC001001' => 35000, 'IC006001' => 35000, 'IC007001' => 1000, 'IC014001' => 1000, 'IC051001' => 99, 'IC051002' => 500, 'IC051019' => 500,
            'IC052001' => 500, 'IC064001' => 34500, 'IC076001' => 26500, 'IR001001' => 10, 'IR001002' => 26500, 'IR001005' => 26500, 'IR001007' => '3.9', 'IR001008' => 1034,
            'IR001011' => 1034, 'IR021001' => 1034, 'IR026001' => 1034] as $codice => $valore) {
            $quadri->imposta($codice, $valore);
        }
        // dati contabili scritti con lo schema: radice di prova Fornitura con il solo frontespizio minimo
        $metodo = new \ReflectionMethod(GeneratoreIrap::class, 'getDatiContabili');
        $dati = $metodo->invoke(new GeneratoreIrap($this->dataMapper), $quadri, 2026);
        $this->assertSame('35000', $dati['QuadroIC']['PrimoModulo']['ImpreseIndustriali']['ComponentiPositivi']['RicaviVendite']);
        $this->assertSame('3,9', $dati['QuadroIR']['PrimoModulo']['RipartizioneBaseImp']['RipartizioneRegionale'][0]['Aliquota']);
        $schema = $this->getSchema();
        $documento = new \DOMDocument('1.0', 'UTF-8');
        $tm = GeneratoreIrap::NAMESPACE_TELEMATICO;
        $messaggio = $documento->appendChild($documento->createElementNS($tm, 'tm:Messaggio'));
        $intestazione = $messaggio->appendChild($documento->createElementNS($tm, 'tm:Intestazione'));
        $intestazione->appendChild($documento->createElementNS($tm, 'tm:CodiceFiscaleFornitore', '01234560785'));
        $contenuto = $messaggio->appendChild($documento->createElementNS($tm, 'tm:Contenuto'));
        $contenuto->setAttribute('CodiceFornitura', 'IRA26');
        (new ScritturaXml($schema, GeneratoreIrap::PREFISSI))->scrivi($contenuto, $schema->getElementoGlobale(SpecificheDichiarativi::NAMESPACE_IRAP, 'Fornitura'), [
            'Intestazione' => ['TipoFornitore' => '1'],
            'Dichiarazione' => [['@identificativo' => '00001', 'Frontespizio' => [
                        'SoggettoFiscale' => ['CodiceFiscale' => '01234560785'], 'CodiceRegione' => '10', 'Regione' => 'LOMBARDIA',
                        'SoggettiDiversiDaPF' => ['Denominazione' => 'ALFA S.R.L.', 'InizioPeriodoImposta' => '01012025', 'FinePeriodoImposta' => '31122025', 'Stato' => '1', 'NaturaGiuridica' => '2', 'Situazione' => '6'],
                        'DichiarazioneRedditi' => '2',
                        'DatiSottoscrittore' => ['CodiceFiscale' => 'RSSMRA80A01H501U', 'CodiceCarica' => '1', 'DatiAnagrafici' => ['Cognome' => 'ROSSI', 'Nome' => 'MARIO', 'Sesso' => 'M', 'DataNascita' => '01011980', 'ComuneNascita' => 'ROMA', 'ProvinciaNascita' => 'RM']],
                        'FirmaDichiarazione' => ['CasellaIC' => '1', 'CasellaIR' => '1', 'FirmaDichiarante' => '1'],
                    ], 'DatiContabili' => $dati]],
        ]);
        libxml_use_internal_errors(true);
        $valido = $documento->schemaValidate(TabelleDichiarativi::CARTELLA . 'xsd-IRA-2026/ira/2026/fornituraIra_2026_v1.xsd');
        $errori = array_map(fn(\LibXMLError $errore) => trim($errore->message), libxml_get_errors());
        libxml_clear_errors();
        $this->assertTrue($valido, implode("\n", $errori));
        // un valore senza posto nello schema blocca la scrittura
        $this->expectException(\InvalidArgumentException::class);
        (new ScritturaXml($schema, GeneratoreIrap::PREFISSI))->scrivi(new \DOMDocument(), $schema->getElementoGlobale(SpecificheDichiarativi::NAMESPACE_IRAP, 'Fornitura'), ['Inesistente' => '1']);
    }

    public function testFormatiDeiValori(): void
    {
        $this->assertNull(GeneratoreIrap::formatta(FormatoCampo::positivo, '0'));
        $this->assertSame('-1200', GeneratoreIrap::formatta(FormatoCampo::numerico, '-1200'));
        $this->assertSame('4,82', GeneratoreIrap::formatta(FormatoCampo::percentuale, '4.82'));
        $this->assertSame('1', GeneratoreIrap::formatta(FormatoCampo::casella, '1'));
        $this->assertSame('31122025', GeneratoreIrap::formatta(FormatoCampo::data, '2025-12-31'));
    }

    public function testAliquoteRegionali(): void
    {
        $aliquote = new AliquoteIrap();
        $this->assertTrue($aliquote->isDisponibile(2026));
        $this->assertFalse($aliquote->isDisponibile(2020));
        // codice 1: aliquota ordinaria (Lazio 4,82, Lombardia 3,9, Veneto 4,08 generalizzata dal 2025); regione con o senza zero iniziale
        $this->assertSame(4.82, $aliquote->getAliquota(2026, '08', '1'));
        $this->assertSame(3.9, $aliquote->getAliquota(2026, '10', '001'));
        $this->assertSame(4.08, $aliquote->getAliquota(2026, '21', '1'));
        $this->assertSame(2.68, $aliquote->getAliquota(2026, '3', '10'));
        $this->assertSame('Aliquota ordinaria', $aliquote->getDescrizione(2026, '8', '1'));
        $this->assertNull($aliquote->getAliquota(2026, '08', '99'));
    }

    public function testCategorieECodiciDellaRegione(): void
    {
        // categoria dell'art. 16, comma 1-bis, dal codice ATECO
        $this->assertSame(AliquoteIrap::FINANZIARI, AliquoteIrap::getCategoria('64.19.10'));
        $this->assertSame(AliquoteIrap::FINANZIARI, AliquoteIrap::getCategoria('66.12.00'));
        $this->assertSame(AliquoteIrap::ASSICURAZIONI, AliquoteIrap::getCategoria('65.12.00'));
        $this->assertNull(AliquoteIrap::getCategoria('69.20.01'));
        $this->assertNull(AliquoteIrap::getCategoria(null));
        $aliquote = new AliquoteIrap();
        // Lazio: banche codice 2 (5,57), assicurazioni codice 3 (6,82); elenco in ordine di codice
        $this->assertSame(['2'], array_map('strval', array_keys($aliquote->getCandidati(2026, '08', AliquoteIrap::FINANZIARI))));
        $this->assertSame(['3'], array_map('strval', array_keys($aliquote->getCandidati(2026, '08', AliquoteIrap::ASSICURAZIONI))));
        $this->assertSame('1', (string) array_key_first($aliquote->getElenco(2026, '8')));
    }

    public function testVariazioniDelQuadroIcDaiConti(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = ModelloDichiarativo::irap;
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper, $this->getCatalogo(ModelloDichiarativo::irap, 2026));
        // coppia di IC51 già compilata a mano con il codice 3: la variazione con il codice 99 va nella coppia successiva
        $quadri->imposta('IC051001', 3);
        $quadri->imposta('IC051002', 100);
        $metodo = new \ReflectionMethod(PropostaIrap::class, 'proponiVariazioniConti');
        $metodo->invoke(new PropostaIrap($this->dataMapper), $quadri, ['IC43' => 12000.4, 'IC46' => 800.0, 'IC51:99' => 250.0, 'IC57:04' => 1500.0]);
        $this->assertSame(12000, $quadri->getImporto('IC043001'));
        $this->assertSame(800, $quadri->getImporto('IC046001'));
        $this->assertSame(99, $quadri->getImporto('IC051003'));
        $this->assertSame(250, $quadri->getImporto('IC051004'));
        $this->assertSame(4, $quadri->getImporto('IC057001'));
        $this->assertSame(1500, $quadri->getImporto('IC057002'));
        $this->assertTrue(\Contabilita\Application\Services\RighiIrap::isValido('IC51:99'));
        $this->assertFalse(\Contabilita\Application\Services\RighiIrap::isValido('IC52'));
    }

    public function testDeduzioniEAcconti(): void
    {
        // ulteriore deduzione (art. 11, c. 4-bis): società di capitali e società di persone (importi aumentati)
        $this->assertSame(8000, PropostaIrap::getUlterioreDeduzione(23370, false));
        $this->assertSame(13000, PropostaIrap::getUlterioreDeduzione(23370, true));
        $this->assertSame(6000, PropostaIrap::getUlterioreDeduzione(180800, false));
        $this->assertSame(3250, PropostaIrap::getUlterioreDeduzione(180950, true));
        $this->assertSame(0, PropostaIrap::getUlterioreDeduzione(181000, false));
        // acconti: soglie di 20,66 (società di capitali) e 51,65 euro (società di persone)
        $this->assertSame([300.0, 299.0], PropostaIrap::getAcconti(599.0, false, true));
        $this->assertSame([0.0, 0.0], PropostaIrap::getAcconti(50.0, true, false));
        $this->assertSame([0.0, 50.0], PropostaIrap::getAcconti(50.0, false, false));
    }
}
