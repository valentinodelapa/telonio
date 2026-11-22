<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Enumerations\RegimeFiscale;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaRedditiEnc;
use Dichiarativi\Application\Services\Redditi\PropostaRedditiSocieta;
use Dichiarativi\Application\Services\Telematico\GeneratoreIsa;
use Dichiarativi\Application\Services\Telematico\GeneratoreRedditi;
use Dichiarativi\Application\Services\Telematico\TracciatoRedditiSocieta;

/**
 * Modello Redditi Enti non commerciali 2026: forfetari dell'art. 145 TUIR
 * e della legge 398/1991, totali del quadro RG, IRES del quadro RN
 * (istruzioni, par. 15.1), quadro RF e frontespizio del file "RNC26".
 */
class RedditiEncTest extends DichiarativiTestCase
{

    private function creaRedditiEnc(): QuadriDichiarazione
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = ModelloDichiarativo::redditiEnc;
        $dichiarazione->naturaGiuridica = 12;
        return new QuadriDichiarazione($dichiarazione, $this->dataMapper, $this->getCatalogo(ModelloDichiarativo::redditiEnc, 2026));
    }

    public function testModelloEnc(): void
    {
        $modello = ModelloDichiarativo::redditiEnc;
        $this->assertTrue($modello->isGestito());
        $this->assertTrue($modello->isRedditi());
        $this->assertTrue($modello->isRedditiSocieta());
        // codice della fornitura delle specifiche: "RNC26"
        $this->assertSame('RNC26', $modello->getCodiceFornitura(2025));
        $this->assertSame('2026-10-31', $modello->getScadenza(2025)->format('Y-m-d'));
        $this->assertContains('RG', $modello->getQuadri());
        // testa dei record ISA: 8-10 Redditi ENC (quadro RE, RF, RG)
        $this->assertSame(8, GeneratoreIsa::getModelloDichiarazione($modello, 'RE'));
        $this->assertSame(10, GeneratoreIsa::getModelloDichiarazione($modello, 'RG'));
    }

    public function testForfetariDellArticolo145(): void
    {
        // prestazioni di servizi: 85 per cento fino a 15.493,71 euro, 75 oltre (controlli del rigo RG21, colonna 2)
        $this->assertSame(8500.0, PropostaRedditiEnc::getCostiForfetari145(1, 10000.0));
        $this->assertSame(16549.37, PropostaRedditiEnc::getCostiForfetari145(1, 20000.0));
        // altre attività: 90 per cento fino a 25.822,84 euro, 85 oltre (23.240,56 + 85 per cento dell'eccedenza)
        $this->assertSame(18000.0, PropostaRedditiEnc::getCostiForfetari145(2, 20000.0));
        $this->assertSame(26791.15, PropostaRedditiEnc::getCostiForfetari145(2, 30000.0));
        $this->assertSame(2, PropostaRedditiEnc::getCodiceAttivita145('47.11.10'));
        $this->assertSame(1, PropostaRedditiEnc::getCodiceAttivita145('94.99.90'));
        $this->assertSame(1, PropostaRedditiEnc::getCodiceAttivita145(null));
    }

    public function testTotaliDelQuadroRg(): void
    {
        $quadri = $this->creaRedditiEnc();
        $totali = new \ReflectionMethod(PropostaRedditiEnc::class, 'proponiTotaliRg');
        // legge 398/1991: 10.000 euro di proventi commerciali, costi forfetari del 97 per cento
        $quadri->imposta('RG002004', 10000);
        $quadri->imposta('RG002007', 10000);
        $quadri->imposta('RG021001', 9700);
        $totali->invoke(new PropostaRedditiEnc($this->dataMapper), $quadri);
        $this->assertSame(9700, $quadri->getImporto('RG021003'));
        $this->assertSame(10000, $quadri->getImporto('RG012001'));
        $this->assertSame(9700, $quadri->getImporto('RG024001'));
        $this->assertSame(300, $quadri->getImporto('RG031001'));
        $this->assertSame(300, $quadri->getImporto('RG033001'));
        // perdita: i proventi esenti la riducono (RG31 = RG28 - RG29 + RG30), nessun reddito in RG33
        $quadri->imposta('RG021001', 13000);
        $quadri->imposta('RG030001', 500);
        $totali->invoke(new PropostaRedditiEnc($this->dataMapper), $quadri);
        $this->assertSame(-3000, $quadri->getImporto('RG028002'));
        $this->assertSame(-2500, $quadri->getImporto('RG031001'));
        $this->assertNull($quadri->get('RG033001'));
    }

    public function testIresDelQuadroRn(): void
    {
        $quadri = $this->creaRedditiEnc();
        $quadri->imposta('RF065001', 50000);
        $quadri->imposta('RB035010', 2000);
        $quadri->imposta('RS031001', 1000);
        $ires = new \ReflectionMethod(PropostaRedditiEnc::class, 'proponiIres');
        $ires->invoke(new PropostaRedditiEnc($this->dataMapper), $quadri, $quadri->getDichiarazione(), false);
        $this->assertSame(50000, $quadri->getImporto('RN006001'));
        $this->assertSame(2000, $quadri->getImporto('RN002001'));
        $this->assertSame(52000, $quadri->getImporto('RN013006'));
        $this->assertSame(1000, $quadri->getImporto('RN015003'));
        $this->assertSame(51000, $quadri->getImporto('RN016001'));
        // IRES al 24 per cento
        $this->assertSame(12240, $quadri->getImporto('RN017002'));
        $this->assertSame(12240, $quadri->getImporto('RN021001'));
        $this->assertSame(12240, $quadri->getImporto('RN028001'));
        $this->assertSame(12240, $quadri->getImporto('RN034003'));
        $this->assertSame(12240, $quadri->getImporto('RX001001'));
        // detrazioni (RN20, col. 6) fino a concorrenza dell'imposta e ritenute oltre l'imposta: credito RN35 e RX1 col. 2 e 5
        $quadri->imposta('RN020006', 240);
        $quadri->imposta('RN026001', 15000);
        $ires->invoke(new PropostaRedditiEnc($this->dataMapper), $quadri, $quadri->getDichiarazione(), false);
        $this->assertSame(12000, $quadri->getImporto('RN021001'));
        $this->assertSame(-3000, $quadri->getImporto('RN028001'));
        $this->assertNull($quadri->get('RN034003'));
        $this->assertSame(3000, $quadri->getImporto('RN035001'));
        $this->assertSame(3000, $quadri->getImporto('RX001002'));
        $this->assertSame(3000, $quadri->getImporto('RX001005'));
        // perdita dell'attività commerciale (RF65 vuoto): nessun reddito nel rigo RN6
        $quadri->imposta('RF065001', null);
        $ires->invoke(new PropostaRedditiEnc($this->dataMapper), $quadri, $quadri->getDichiarazione(), false);
        $this->assertNull($quadri->get('RN006001'));
    }

    public function testQuadroRfDellEnte(): void
    {
        $quadri = $this->creaRedditiEnc();
        $this->testAdapter->impostaSelectCallback(fn() => new \Telonio\Tests\TestResultSet([]));
        $dati = $this->creaDatiFiscali();
        $dati->regimeFiscale = RegimeFiscale::ordinario;
        // perdita da partecipazione (a mano) e proventi esenti: RF63 = RF60 + RF62, RF65 vuoto (solo importi positivi)
        $quadri->imposta('RF059002', 1000);
        $quadri->imposta('RF062001', 300);
        (new PropostaRedditiSocieta($this->dataMapper))->proponiRf($quadri, $dati, $this->azienda, 2025, 'ENC');
        $this->assertSame(-1000, $quadri->getImporto('RF060002'));
        $this->assertSame(-700, $quadri->getImporto('RF063001'));
        $this->assertNull($quadri->get('RF064003'));
        $this->assertNull($quadri->get('RF065001'));
        // reddito con perdite pregresse in misura piena (RF64, col. 2): totale col. 3 e reddito RF65
        $quadri->imposta('RF059002', null);
        $quadri->imposta('RF062001', null);
        $quadri->imposta('RF058002', 5000);
        $quadri->imposta('RF064002', 2000);
        (new PropostaRedditiSocieta($this->dataMapper))->proponiRf($quadri, $dati, $this->azienda, 2025, 'ENC');
        $this->assertSame(5000, $quadri->getImporto('RF063001'));
        $this->assertSame(2000, $quadri->getImporto('RF064003'));
        $this->assertSame(3000, $quadri->getImporto('RF065001'));
    }

    public function testFrontespizioRedditiEnc(): void
    {
        $quadri = $this->creaRedditiEnc();
        $quadri->imposta('RG002007', 1000);
        $quadri->imposta('RN016001', 1000);
        $metodo = new \ReflectionMethod(GeneratoreRedditi::class, 'recordB');
        $record = $metodo->invoke(new GeneratoreRedditi($this->dataMapper), $quadri->getDichiarazione(), $quadri);
        $this->assertSame(1900, strlen($record));
        // natura giuridica 12 (associazione), caselle dei quadri RG e RN, campi numerici del record B propri di ENC a zero
        $this->assertSame('1' . '12' . '6', substr($record, 724, 4));
        $this->assertSame('1', $record[TracciatoRedditiSocieta::CASELLE_QUADRI_ENC['RG'] - 1]);
        $this->assertSame('1', $record[TracciatoRedditiSocieta::CASELLE_QUADRI_ENC['RN'] - 1]);
        $this->assertSame('0', $record[TracciatoRedditiSocieta::CASELLE_QUADRI_ENC['RF'] - 1]);
        foreach (TracciatoRedditiSocieta::NUMERICI_B_ENC as $posizione => $lunghezza) {
            $this->assertDoesNotMatchRegularExpression('/\D/', substr($record, $posizione - 1, $lunghezza), 'posizione ' . $posizione);
        }
        $this->assertSame([1691, 1, 'NU'], TracciatoRedditiSocieta::RECORD_B_ENC['impegno']);
    }
}
