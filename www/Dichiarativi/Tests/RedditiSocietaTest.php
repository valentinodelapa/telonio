<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Enumerations\CanoneRai;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\ControlliRedditi;
use Dichiarativi\Application\Services\Redditi\CreditiImpostaRu;
use Dichiarativi\Application\Services\Redditi\OperativitaSocieta;
use Dichiarativi\Application\Services\Redditi\PropostaRedditiSocieta;
use Dichiarativi\Application\Services\Telematico\GeneratoreRedditi;
use Dichiarativi\Application\Services\Telematico\TracciatoRedditiSocieta;

/**
 * Modelli Redditi SC e SP 2026: IRES e acconti (istruzioni del quadro RN e
 * istruzioni generali, par. 4.2), frontespizio del file telematico.
 */
class RedditiSocietaTest extends DichiarativiTestCase
{

    private function creaRedditiSc(): QuadriDichiarazione
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = ModelloDichiarativo::redditiSc;
        $dichiarazione->naturaGiuridica = 2;
        return new QuadriDichiarazione($dichiarazione, $this->dataMapper, $this->getCatalogo(ModelloDichiarativo::redditiSc, 2026));
    }

    public function testAccontiIres(): void
    {
        // non dovuti fino a 20,66 euro; 40 per cento (50 per i soggetti ISA) alla prima rata, unica rata se la prima non supera 103 euro
        $this->assertSame([0.0, 0.0], PropostaRedditiSocieta::getAccontiIres(20.66, false));
        $this->assertSame([400.0, 600.0], PropostaRedditiSocieta::getAccontiIres(1000.0, false));
        $this->assertSame([2805.0, 2804.0], PropostaRedditiSocieta::getAccontiIres(5609.0, true));
        $this->assertSame([0.0, 200.0], PropostaRedditiSocieta::getAccontiIres(200.0, false));
    }

    public function testIresConLePerditePregresse(): void
    {
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RF063001', 100000);
        // perdite pregresse: 50.000 in misura limitata (80 per cento del reddito) e 10.000 in misura piena
        $quadri->imposta('RN004001', 50000);
        $quadri->imposta('RN004002', 10000);
        $metodo = new \ReflectionMethod(PropostaRedditiSocieta::class, 'proponiIres');
        $metodo->invoke(new PropostaRedditiSocieta($this->dataMapper), $quadri, $quadri->getDichiarazione(), false);
        $this->assertSame(100000, $quadri->getImporto('RN001003'));
        $this->assertSame(60000, $quadri->getImporto('RN004004'));
        $this->assertSame(40000, $quadri->getImporto('RN006011'));
        $this->assertSame(9600, $quadri->getImporto('RN008002'));
        $this->assertSame(9600, $quadri->getImporto('RN017001'));
        $this->assertSame(9600, $quadri->getImporto('RX001001'));
        // perdita: rigo RN2 e perdita residua RN5, nessuna imposta
        $quadri->imposta('RF063001', -30000);
        $metodo->invoke(new PropostaRedditiSocieta($this->dataMapper), $quadri, $quadri->getDichiarazione(), false);
        $this->assertSame(30000, $quadri->getImporto('RN002001'));
        $this->assertSame(30000, $quadri->getImporto('RN005003'));
        $this->assertNull($quadri->get('RN004004'));
        $this->assertNull($quadri->get('RX001001'));
    }

    public function testRiportoDellePerdite(): void
    {
        // residui dell'anno prima (dichiarazione o archivio): 60.000 in misura limitata e 5.000 in misura piena
        $proposta = new PropostaRedditiSocieta($this->dataMapper);
        (new \ReflectionProperty(PropostaRedditiSocieta::class, 'residui'))->setValue($proposta, ['limitata' => 60000, 'piena' => 5000, 'fonte' => 'prova']);
        $metodo = new \ReflectionMethod(PropostaRedditiSocieta::class, 'proponiIres');
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RF063001', 50000);
        $metodo->invoke($proposta, $quadri, $quadri->getDichiarazione(), false);
        // limitata fino all'80 per cento del reddito (40.000), piena sul resto (5.000)
        $this->assertSame(40000, $quadri->getImporto('RN004001'));
        $this->assertSame(5000, $quadri->getImporto('RN004002'));
        $this->assertSame(45000, $quadri->getImporto('RN004004'));
        $this->assertSame(5000, $quadri->getImporto('RN006011'));
        // residui per l'anno dopo nel prospetto RS44-RS45, colonna 6
        $this->assertSame(20000, $quadri->getImporto('RS044006'));
        $this->assertNull($quadri->get('RS045006'));
        // perdita dell'anno: nessun utilizzo, si somma ai residui (misura limitata dopo i primi tre periodi d'imposta)
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RF063001', -8000);
        $metodo->invoke($proposta, $quadri, $quadri->getDichiarazione(), false);
        $this->assertNull($quadri->get('RN004001'));
        $this->assertSame(8000, $quadri->getImporto('RS044002'));
        $this->assertSame(68000, $quadri->getImporto('RS044006'));
        $this->assertSame(5000, $quadri->getImporto('RS045006'));
        // primi tre periodi d'imposta: la perdita dell'anno è in misura piena
        (new \ReflectionProperty(PropostaRedditiSocieta::class, 'primiTreAnni'))->setValue($proposta, true);
        $metodo->invoke($proposta, $quadri, $quadri->getDichiarazione(), false);
        $this->assertNull($quadri->get('RS044002'));
        $this->assertSame(8000, $quadri->getImporto('RS045002'));
        $this->assertSame(13000, $quadri->getImporto('RS045006'));
    }

    public function testCreditiDImpostaDelQuadroRu(): void
    {
        // tabella ufficiale dei crediti con i codici tributo F24 dalle istruzioni
        $crediti = new CreditiImpostaRu($this->dataMapper);
        $tabella = $crediti->getTabella(2026);
        $this->assertSame(['6936', '7077'], $tabella['2L']['tributi']);
        $this->assertSame([], $tabella['99']['tributi']);
        $this->assertSame('T6', $crediti->getCreditiPerTributo(2026)['7072']);
        // modulo compilato a mano: residuo RU12 = residuo precedente + spettante - utilizzi in F24 e in dichiarazione
        $quadri = $this->creaRedditiSc();
        $quadri->usaModulo(2);
        $quadri->imposta('RU001001', '2L');
        $quadri->imposta('RU002001', 10000);
        $quadri->imposta('RU005003', 2000);
        $quadri->imposta('RU006001', 3000);
        $quadri->imposta('RU007005', 500);
        $crediti->proponi($quadri, $quadri->getDichiarazione());
        $this->assertSame(8500, $quadri->getImporto('RU012002', 2));
        $this->assertNull($quadri->get('RU001001', 1));
    }

    public function testConsolidatoETrasparenza(): void
    {
        $consolidante = new \Anagrafica\Application\Entities\Soggetto($this->dataMapper);
        $consolidante->codiceFiscale = '09222330301';
        $opzione = new \Anagrafica\Application\Entities\OpzioneTassazione($this->dataMapper);
        $opzione->soggetto = $this->azienda;
        $opzione->tipoOpzione = \Anagrafica\Application\Enumerations\TipoOpzioneTassazione::consolidatoNazionale;
        $opzione->consolidante = $consolidante;
        $opzione->annoInizio = 2025;
        $opzione->annoFine = 2027;
        $this->assertTrue($opzione->isVigenteNel(2026));
        $this->assertFalse($opzione->isVigenteNel(2028));
        // consolidato: reddito complessivo netto trasferito alla consolidante (GN6), con le perdite anteriori (GN4) limitate all'80 per cento
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RF063001', 50000);
        $quadri->imposta('GN004001', 100000);
        (new \ReflectionMethod(PropostaRedditiSocieta::class, 'proponiConsolidato'))->invoke(new PropostaRedditiSocieta($this->dataMapper), $quadri, $opzione);
        $this->assertSame('09222330301', $quadri->get('GN000002'));
        $this->assertSame(40000, $quadri->getImporto('GN004004'));
        $this->assertSame(10000, $quadri->getImporto('GN006002'));
        // trasparenza (art. 116): reddito imponibile da imputare ai soci (TN4), tipo 2
        $opzione->tipoOpzione = \Anagrafica\Application\Enumerations\TipoOpzioneTassazione::trasparenzaRistretta;
        $opzione->consolidante = null;
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RF063001', 23370);
        (new \ReflectionMethod(PropostaRedditiSocieta::class, 'proponiTrasparenza'))->invoke(new PropostaRedditiSocieta($this->dataMapper), $quadri, $opzione);
        $this->assertSame(2, $quadri->getImporto('TN000001'));
        $this->assertSame(23370, $quadri->getImporto('TN004002'));
        // reddito imponibile (col. 6) al netto di ACE e altre deduzioni (col. 4 e 5)
        $this->assertSame(23370, $quadri->getImporto('TN004006'));
        $quadri->imposta('TN004004', 370);
        (new \ReflectionMethod(PropostaRedditiSocieta::class, 'proponiTrasparenza'))->invoke(new PropostaRedditiSocieta($this->dataMapper), $quadri, $opzione);
        $this->assertSame(23000, $quadri->getImporto('TN004006'));
        $quadri->ripristina('TN004004');
        // perdita da attribuire ai soci
        $quadri->imposta('RF063001', -5000);
        (new \ReflectionMethod(PropostaRedditiSocieta::class, 'proponiTrasparenza'))->invoke(new PropostaRedditiSocieta($this->dataMapper), $quadri, $opzione);
        $this->assertSame(5000, $quadri->getImporto('TN002004'));
        $this->assertNull($quadri->get('TN004002'));
    }

    public function testImpostaDelConsolidato(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = ModelloDichiarativo::consolidato;
        $dichiarazione->naturaGiuridica = 3;
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper, $this->getCatalogo(ModelloDichiarativo::consolidato, 2026));
        // reddito complessivo globale di 60.000 euro, perdite del consolidato in misura limitata per 50.000 (80 per cento: 48.000), ritenute delle società 400
        $quadri->imposta('CN003001', 50000);
        $metodo = new \ReflectionMethod(\Dichiarativi\Application\Services\Redditi\PropostaConsolidato::class, 'proponiImposta');
        $metodo->invoke(new \Dichiarativi\Application\Services\Redditi\PropostaConsolidato($this->dataMapper), $quadri, $dichiarazione, 60000, 400);
        $this->assertSame(48000, $quadri->getImporto('CN003003'));
        $this->assertSame(12000, $quadri->getImporto('CN004004'));
        $this->assertSame(2880, $quadri->getImporto('CN005006'));
        $this->assertSame(400, $quadri->getImporto('CN011001'));
        $this->assertSame(2480, $quadri->getImporto('CN013001'));
        $this->assertSame(2480, $quadri->getImporto('CN020002'));
        // frontespizio del CNM: casella del consolidato nazionale, firma del dichiarante nella posizione 1038, casella del quadro CN
        $quadri->imposta('NF012002', 60000);
        $record = (new \ReflectionMethod(GeneratoreRedditi::class, 'recordB'))->invoke(new GeneratoreRedditi($this->dataMapper), $dichiarazione, $quadri);
        $this->assertSame(1900, strlen($record));
        $this->assertSame('1', $record[100]);
        $this->assertSame('1', $record[1037]);
        $this->assertSame('1', $record[TracciatoRedditiSocieta::CASELLE_QUADRI_CNM['CN'] - 1]);
        $this->assertSame('1', $record[TracciatoRedditiSocieta::CASELLE_QUADRI_CNM['NF'] - 1]);
    }

    public function testModelloDiDichiarazioneDegliIsa(): void
    {
        // testa dei record ISA: 1-3 Redditi PF, 4-6 Redditi SP (quadro RE, RF, RG), 7 Redditi SC
        $generatore = \Dichiarativi\Application\Services\Telematico\GeneratoreIsa::class;
        $this->assertSame(3, $generatore::getModelloDichiarazione(ModelloDichiarativo::redditiPf, 'RG'));
        $this->assertSame(6, $generatore::getModelloDichiarazione(ModelloDichiarativo::redditiSp, 'RG'));
        $this->assertSame(4, $generatore::getModelloDichiarazione(ModelloDichiarativo::redditiSp, 'RE'));
        $this->assertSame(7, $generatore::getModelloDichiarazione(ModelloDichiarativo::redditiSc, 'RF'));
    }

    public function testRedditiSpDaImputare(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = ModelloDichiarativo::redditiSp;
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper, $this->getCatalogo(ModelloDichiarativo::redditiSp, 2026));
        // contabilità semplificata: reddito del rigo RG34 nel rigo RN2, nessun reddito in contabilità ordinaria
        $quadri->imposta('RG034001', 18000);
        $metodo = new \ReflectionMethod(PropostaRedditiSocieta::class, 'proponiImputazione');
        $metodo->invoke(new PropostaRedditiSocieta($this->dataMapper), $quadri, $this->azienda, 2025);
        $this->assertSame(18000, $quadri->getImporto('RN002001'));
        $this->assertNull($quadri->get('RN001001'));
        // il frontespizio di Redditi SP ha l'impegno alla trasmissione in posizioni diverse da Redditi SC
        $this->assertSame([1674, 16, 'AN'], TracciatoRedditiSocieta::RECORD_B_SP['codiceFiscaleIncaricato']);
        $this->assertSame([1675, 16, 'AN'], TracciatoRedditiSocieta::RECORD_B_SC['codiceFiscaleIncaricato']);
    }

    public function testFrontespizioRedditiSc(): void
    {
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RF004001', 1000);
        $dichiarazione = $quadri->getDichiarazione();
        $metodo = new \ReflectionMethod(GeneratoreRedditi::class, 'recordB');
        $record = $metodo->invoke(new GeneratoreRedditi($this->dataMapper), $dichiarazione, $quadri);
        $this->assertSame(1900, strlen($record));
        $this->assertSame('B' . str_pad((string) $this->azienda->codiceFiscale, 16), substr($record, 0, 17));
        $this->assertSame(str_pad(mb_strtoupper($this->azienda->nominativo), 60), substr($record, 150, 60));
        // periodo d'imposta solare senza esercizio contabile, stato 1, natura giuridica 2 (S.r.l.), situazione 6
        $this->assertSame('0101202531122025' . '1' . '02' . '6', substr($record, 708, 20));
        // casella del quadro RF compilato
        $this->assertSame('1', $record[TracciatoRedditiSocieta::CASELLE_QUADRI_SC['RF'] - 1]);
        $this->assertSame('0', $record[TracciatoRedditiSocieta::CASELLE_QUADRI_SC['RN'] - 1]);
        // casella "Canone RAI" (campo 69): zero se non scelta, il codice altrimenti
        $this->assertSame('0', $record[740]);
        $dichiarazione->canoneRai = CanoneRai::nessunApparecchio;
        $this->assertSame('3', $metodo->invoke(new GeneratoreRedditi($this->dataMapper), $dichiarazione, $quadri)[740]);
        // comunicazione del concordato con invio autonomo: niente caselle dei quadri
        $autonoma = $metodo->invoke(new GeneratoreRedditi($this->dataMapper), $dichiarazione, $quadri, false, false, false, GeneratoreRedditi::CPB_REVOCA);
        $this->assertSame('2', $autonoma[92]);
        $this->assertSame('0', $autonoma[TracciatoRedditiSocieta::CASELLE_QUADRI_SC['RF'] - 1]);
        $this->assertSame('0', $autonoma[740]);
    }

    public function testOperativitaDelleSocieta(): void
    {
        // valore dei beni ragguagliato ai giorni di possesso dell'anno
        $cespite = new \Contabilita\Application\Entities\Cespite($this->dataMapper);
        $cespite->dataAcquisto = new \SismaFramework\Orm\CustomTypes\SismaDate('2025-07-02');
        $this->assertSame(0.0, OperativitaSocieta::getQuotaPossesso($cespite, 2024));
        $this->assertEqualsWithDelta(183 / 365, OperativitaSocieta::getQuotaPossesso($cespite, 2025), 0.0001);
        $cespite->dataDismissione = new \SismaFramework\Orm\CustomTypes\SismaDate('2026-01-31');
        $this->assertEqualsWithDelta(31 / 365, OperativitaSocieta::getQuotaPossesso($cespite, 2026), 0.0001);
        // nessun bene nei tre esercizi: casella "Casi particolari" 1, il resto del prospetto non si compila
        $quadri = $this->creaRedditiSc();
        (new OperativitaSocieta($this->dataMapper))->proponi($quadri, $quadri->getDichiarazione());
        $this->assertSame(1, $quadri->getImporto('RS116008'));
        $this->assertNull($quadri->get('RS123002'));
        // società non operativa (nessun ricavo, altre immobilizzazioni inserite a mano): ricavi presunti al 15%, reddito minimo al 12% del valore dell'esercizio
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RS121001', 100000);
        $quadri->imposta('RS121004', 50000);
        (new OperativitaSocieta($this->dataMapper))->proponi($quadri, $quadri->getDichiarazione());
        $this->assertNull($quadri->get('RS116008'));
        $this->assertSame(15000, $quadri->getImporto('RS123002'));
        $this->assertSame(6000, $quadri->getImporto('RS123005'));
        $this->assertSame(6000, $quadri->getImporto('RS125001'));
        // con una causa di esclusione indicata a mano il prospetto non si propone
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RS116001', 11);
        (new OperativitaSocieta($this->dataMapper))->proponi($quadri, $quadri->getDichiarazione());
        $this->assertNull($quadri->get('RS116008'));
    }

    public function testFrontespizioDelleSocietaPerLaStampa(): void
    {
        $quadri = $this->creaRedditiSc();
        $quadri->imposta('RF004001', 1000);
        $quadri->imposta('GN000002', '09222330301');
        $dichiarazione = $quadri->getDichiarazione();
        $dichiarazione->canoneRai = CanoneRai::nessunApparecchio;
        $valori = (new \Dichiarativi\Application\Services\Stampa\DatiFrontespizioStampa($this->dataMapper))->getValori($dichiarazione, $quadri);
        // periodo d'imposta solare senza esercizio contabile, stato 1 e situazione 6, natura giuridica, canone RAI, quadri e consolidato
        $this->assertSame(['01', '01', '2025', '31', '12', '2025'], [$valori['#periodoDalGiorno'], $valori['#periodoDalMese'], $valori['#periodoDalAnno'],
            $valori['#periodoAlGiorno'], $valori['#periodoAlMese'], $valori['#periodoAlAnno']]);
        $this->assertSame(['1', '6', '02', '3'], [$valori['#stato'], $valori['#situazione'], $valori['#naturaGiuridica'], $valori['#canoneRai']]);
        $this->assertSame('X', $valori['#quadroRF']);
        $this->assertSame('X', $valori['#consolidato']);
        $this->assertArrayNotHasKey('#trasparenza', $valori);
        // modello CNM: casella del consolidato nazionale, senza stato e situazione
        $dichiarazione->modello = ModelloDichiarativo::consolidato;
        $valori = (new \Dichiarativi\Application\Services\Stampa\DatiFrontespizioStampa($this->dataMapper))->getValori($dichiarazione, $this->creaRedditiSc());
        $this->assertSame('X', $valori['#consolidato']);
        $this->assertArrayNotHasKey('#stato', $valori);
        $this->assertArrayNotHasKey('#situazione', $valori);
    }

    public function testControlliDelFrontespizioDelleSocieta(): void
    {
        $quadri = $this->creaRedditiSc();
        $dichiarazione = $quadri->getDichiarazione();
        $controlli = new ControlliRedditi($this->dataMapper);
        $errori = implode(' | ', $controlli->getErrori($dichiarazione, $quadri));
        $this->assertStringContainsString('Canone RAI', $errori);
        // codice 2: serve il prospetto del canone (rigo RS111 di SC)
        $dichiarazione->canoneRai = CanoneRai::televisione;
        $this->assertStringContainsString('rigo RS111', implode(' | ', $controlli->getErrori($dichiarazione, $quadri)));
        $quadri->imposta('RS111002', '123456789');
        $dichiarazione->canoneRai = CanoneRai::nessunApparecchio;
        $this->assertSame([], $controlli->getErrori($dichiarazione, $quadri));
    }
}
