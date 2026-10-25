<?php

namespace Imu\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Imu\Application\Entities\AccontoVersatoImu;
use Imu\Application\Entities\AgevolazioneImu;
use Imu\Application\Entities\AliquotaApplicata;
use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Entities\CalcoloImu;
use Imu\Application\Entities\CaratteristicheImu;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\ProspettoAliquote;
use Imu\Application\Entities\RigoCalcoloImu;
use Imu\Application\Enumerations\FattispecieImu;
use Imu\Application\Enumerations\ModelloDichiarazione;
use Imu\Application\Services\AliquoteComune;
use Imu\Application\Services\CalcoloImposta;
use Imu\Application\Services\ElaborazioneImu;
use Imu\Application\Services\PropostaDichiarazione;
use Imu\Application\Services\QuadroImmobili;
use Imu\Application\Services\RiepilogoScrivania;
use Imu\Application\Services\RisultatoCalcoloImu;
use SismaFramework\Orm\CustomTypes\SismaDate;

class CalcoloImpostaAndElaborazioneTest extends SuiteTestCase
{

    private function creaComune(string $codice = 'H501', string $denominazione = 'Roma'): Comune
    {
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->codiceCatastale = $codice;
        $comune->denominazione = $denominazione;
        $comune->siglaProvincia = 'RM';
        return $comune;
    }

    private function creaContribuente(): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 1;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->cognome = 'ROSSI';
        $soggetto->nome = 'MARIO';
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        return $soggetto;
    }

    private function creaImmobile(int $id, Comune $comune, TipoCatasto $tipo = TipoCatasto::fabbricati, string $particella = '100'): Immobile
    {
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = $id;
        $immobile->studio = $this->studioPredefinito;
        $immobile->comune = $comune;
        $immobile->tipoCatasto = $tipo;
        $immobile->foglio = '10';
        $immobile->particella = $particella;
        $immobile->subalterno = '1';
        $immobile->indirizzo = 'VIA ROMA';
        $immobile->numeroCivico = '10';
        return $immobile;
    }

    public function testRisultatoCalcoloImuGetTributiAndTotali(): void
    {
        $comune = $this->creaComune();
        $immobile1 = $this->creaImmobile(1, $comune);
        $immobile2 = $this->creaImmobile(2, $comune);

        $r1 = new RigoCalcoloImu($this->dataMapper);
        $r1->immobile = $immobile1;
        $r1->codiceComune = 'H501';
        $r1->fattispecie = FattispecieImu::abitazionePrincipale;
        $r1->impostaAnnua = 500.0;
        $r1->impostaAcconto = 250.0;
        $r1->detrazioneAnnua = 200.0;
        $r1->detrazioneAcconto = 100.0;

        $r2 = new RigoCalcoloImu($this->dataMapper);
        $r2->immobile = $immobile2;
        $r2->codiceComune = 'H501';
        $r2->fattispecie = FattispecieImu::gruppoD;
        $r2->impostaAnnua = 1060.0;
        $r2->impostaAcconto = 530.0;
        $r2->quotaStatoAnnua = 760.0;
        $r2->quotaStatoAcconto = 380.0;

        $rEsente = new RigoCalcoloImu($this->dataMapper);
        $rEsente->immobile = $immobile1;
        $rEsente->codiceComune = 'H501';
        $rEsente->fattispecie = null;
        $rEsente->esenzione = 'Esente';

        $risultato = new RisultatoCalcoloImu();
        $risultato->righi = [$r1, $r2, $rEsente];
        $risultato->avviso('Test avviso');
        $risultato->avviso('Test avviso'); // non duplicato
        $this->assertCount(1, $risultato->avvisi);

        $tributi = $risultato->getTributi();
        $this->assertArrayHasKey('H501|3912', $tributi);
        $this->assertArrayHasKey('H501|3925', $tributi);
        $this->assertArrayHasKey('H501|3930', $tributi);
        $this->assertSame(300.0, $tributi['H501|3912']['annuo']);
        $this->assertSame(150.0, $tributi['H501|3912']['acconto']);
        $this->assertSame(760.0, $tributi['H501|3925']['annuo']);
        $this->assertSame(300.0, $tributi['H501|3930']['annuo']);
        $this->assertSame(['H501' => 1360.0], $risultato->getTotaliComune());
    }

    public function testAliquoteComunePrioritaERipiego(): void
    {
        $aliquote = new AliquoteComune($this->studioPredefinito, $this->dataMapper);

        $prospettoPubblicato = new ProspettoAliquote($this->dataMapper);
        $prospettoPubblicato->id = 10;
        $prospettoPubblicato->codiceCatastale = 'H501';
        $prospettoPubblicato->denominazioneComune = 'Roma';
        $prospettoPubblicato->anno = 2025;
        $prospettoPubblicato->aliquotaAltriFabbricati = 1.06;
        $prospettoPubblicato->detrazioneAbitazionePrincipale = 250.0;
        $prospettoPubblicato->importoMinimo = 15.0;
        $prospettoPubblicato->assimilazioneAnziani = true;

        $differenziata = new AliquotaDifferenziata($this->dataMapper);
        $differenziata->id = 20;
        $differenziata->prospettoAliquote = $prospettoPubblicato;
        $differenziata->fattispecie = FattispecieImu::altriFabbricati;
        $differenziata->aliquota = 0.76;
        $differenziata->descrizione = 'Canone concordato L. 431/1998';

        // Studio vuoto, pubblicato presente + differenziate
        $this->testAdapter->accodaSelect(
            new TestResultSet([]), // getDelloStudioTutti
            new TestResultSet([$prospettoPubblicato]), // getPubblicatiTutti
            new TestResultSet([$differenziata]) // getDifferenziate
        );

        $esito = $aliquote->getAliquota('H501', 2025, FattispecieImu::altriFabbricati, null, null, CategoriaCatastale::a2, UtilizzoImmobile::locatoCanoneConcordato, null, false, false);
        // Riconosce automaticamente la differenziata per canone concordato
        $this->assertSame(0.76, $esito['aliquota']);
        $this->assertTrue($esito['proposta']);
        $this->assertSame(250.0, $aliquote->getDetrazione('H501', 2025));
        $this->assertSame(15.0, $aliquote->getImportoMinimo('H501', 2025));
        $this->assertTrue($aliquote->isAssimilazioneAnziani('H501', 2025));

        // Scelta esplicita dello studio
        $scelta = new AliquotaApplicata($this->dataMapper);
        $scelta->aliquota = 0.90;
        $scelta->motivo = 'Regolamento comunale';
        $esitoScelta = $aliquote->getAliquota('H501', 2025, FattispecieImu::altriFabbricati, $scelta);
        $this->assertSame(0.90, $esitoScelta['aliquota']);
        $this->assertFalse($esitoScelta['proposta']);
        $this->assertStringContainsString('Regolamento comunale', $esitoScelta['origine']);

        // Anno >= 2025 senza prospetto => aliquota base di legge
        $this->testAdapter->accodaSelect(new TestResultSet([]), new TestResultSet([]));
        $esitoBase = $aliquote->getAliquota('F205', 2025, FattispecieImu::altriFabbricati, null);
        $this->assertSame(0.86, $esitoBase['aliquota']);
    }

    public function testCalcoloImpostaAbPrincipalePertinenzeEAltriFabbricati(): void
    {
        $this->assertTrue(CalcoloImposta::isSoggettoPassivo(TitoloPossesso::proprieta));
        $this->assertFalse(CalcoloImposta::isSoggettoPassivo(TitoloPossesso::nudaProprieta));
        $this->assertSame(55, CalcoloImposta::getMoltiplicatore(CategoriaCatastale::c1));
        $this->assertSame(80, CalcoloImposta::getMoltiplicatore(CategoriaCatastale::a10));
        $this->assertSame(65, CalcoloImposta::getMoltiplicatore(CategoriaCatastale::d1));
        $this->assertSame(140, CalcoloImposta::getMoltiplicatore(CategoriaCatastale::b1));
        $this->assertSame(160, CalcoloImposta::getMoltiplicatore(CategoriaCatastale::a2));

        $comune = $this->creaComune();
        $soggetto = $this->creaContribuente();
        $immobileA1 = $this->creaImmobile(1, $comune, TipoCatasto::fabbricati, '100');
        $immobileC6 = $this->creaImmobile(2, $comune, TipoCatasto::fabbricati, '101');

        $p1 = new Possesso($this->dataMapper);
        $p1->id = 1;
        $p1->soggetto = $soggetto;
        $p1->immobile = $immobileA1;
        $p1->titolo = TitoloPossesso::proprieta;
        $p1->quota = 100.0;
        $p1->utilizzo = UtilizzoImmobile::abitazionePrincipale;
        $p1->validoDal = new SismaDate('2020-01-01');

        $p2 = new Possesso($this->dataMapper);
        $p2->id = 2;
        $p2->soggetto = $soggetto;
        $p2->immobile = $immobileC6;
        $p2->titolo = TitoloPossesso::proprieta;
        $p2->quota = 100.0;
        $p2->utilizzo = UtilizzoImmobile::pertinenzaAbitazionePrincipale;
        $p2->validoDal = new SismaDate('2020-01-01');

        $dc1 = new DatiCatastali($this->dataMapper);
        $dc1->id = 1;
        $dc1->immobile = $immobileA1;
        $dc1->categoria = CategoriaCatastale::a1;
        $dc1->rendita = 1000.0;
        $dc1->validoDal = new SismaDate('2020-01-01');

        $dc2 = new DatiCatastali($this->dataMapper);
        $dc2->id = 2;
        $dc2->immobile = $immobileC6;
        $dc2->categoria = CategoriaCatastale::c6;
        $dc2->rendita = 200.0;
        $dc2->validoDal = new SismaDate('2020-01-01');

        $this->testAdapter->impostaSelectCallback(function (string $query) use ($p1, $p2, $dc1, $dc2): TestResultSet {
            if (str_contains($query, 'possesso') && str_contains($query, 'immobile')) {
                return new TestResultSet([$p1]);
            }
            if (str_contains($query, 'possesso')) {
                return new TestResultSet([$p1, $p2]);
            }
            if (str_contains($query, 'dati_catastali')) {
                return new TestResultSet([$dc1]);
            }
            return new TestResultSet([]);
        });

        $risultato = (new CalcoloImposta($this->dataMapper))->calcola($soggetto, 2025);
        $this->assertCount(2, $risultato->righi);
        $rigoAb = $risultato->righi[0];
        $this->assertSame(FattispecieImu::abitazionePrincipale, $rigoAb->fattispecie);
        // Base = 1000 * 1.05 * 160 = 168000; aliquota base AP = 0.5% => impostaAnnua = 840.0; detrazione = 200.0
        $this->assertSame(168000.0, $rigoAb->baseImponibile);
        $this->assertSame(840.0, $rigoAb->impostaAnnua);
        $this->assertSame(200.0, $rigoAb->detrazioneAnnua);
    }

    public function testElaborazioneImuAndElimina(): void
    {
        $this->assertSame('2025-06-16', ElaborazioneImu::getScadenzaAcconto(2025)->format('Y-m-d'));
        $this->assertSame('2025-12-16', ElaborazioneImu::getScadenzaSaldo(2025)->format('Y-m-d'));
        $this->assertSame('2026-06-16', ElaborazioneImu::getScadenzaConguaglioEnc(2025)->format('Y-m-d'));

        $comune = $this->creaComune();
        $soggetto = $this->creaContribuente();
        $immobile = $this->creaImmobile(1, $comune);

        $possesso = new Possesso($this->dataMapper);
        $possesso->id = 1;
        $possesso->soggetto = $soggetto;
        $possesso->immobile = $immobile;
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->utilizzo = UtilizzoImmobile::aDisposizione;
        $possesso->validoDal = new SismaDate('2020-01-01');

        $dc = new DatiCatastali($this->dataMapper);
        $dc->id = 1;
        $dc->immobile = $immobile;
        $dc->categoria = CategoriaCatastale::a2;
        $dc->rendita = 1000.0;
        $dc->validoDal = new SismaDate('2020-01-01');

        $codice3918 = new CodiceTributo($this->dataMapper);
        $codice3918->id = 1;
        $codice3918->sezione = SezioneCodiceTributo::tributiLocali;
        $codice3918->codice = '3918';
        $codice3918->descrizione = 'IMU altri fabbricati';
        $codice3918->tipoTributo = 'IMU';

        $this->testAdapter->impostaSelectCallback(function (string $query) use ($possesso, $dc, $codice3918, $comune): TestResultSet {
            if (str_contains($query, 'COUNT')) {
                return TestResultSet::conteggio(0);
            }
            if (str_contains($query, 'FROM `possesso`')) {
                return new TestResultSet([$possesso]);
            }
            if (str_contains($query, 'FROM `dati_catastali`')) {
                return new TestResultSet([$dc]);
            }
            if (str_contains($query, 'FROM `codice_tributo`')) {
                return new TestResultSet([$codice3918]);
            }
            if (str_contains($query, 'FROM `comune`')) {
                return new TestResultSet([$comune]);
            }
            return new TestResultSet([]);
        });

        $elaborazione = new ElaborazioneImu($this->dataMapper);
        $calcolo = $elaborazione->elabora($soggetto, 2025, false);
        // 168000 * 0.86% = 1444.80
        $this->assertSame(1444.8, $calcolo->impostaAnnua);
        $this->assertFalse($calcolo->unicaSoluzione);

        $differenze = $elaborazione->elimina($calcolo);
        $this->assertSame([], $differenze);
    }

    public function testPropostaDichiarazioneQuadroImmobiliERiepilogoScrivania(): void
    {
        $comune = $this->creaComune();
        $soggetto = $this->creaContribuente();
        $immobile = $this->creaImmobile(1, $comune);

        $possesso = new Possesso($this->dataMapper);
        $possesso->id = 1;
        $possesso->soggetto = $soggetto;
        $possesso->immobile = $immobile;
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->utilizzo = UtilizzoImmobile::aDisposizione;
        $possesso->validoDal = new SismaDate('2025-03-01');

        $caratteristiche = new CaratteristicheImu($this->dataMapper);
        $caratteristiche->id = 1;
        $caratteristiche->immobile = $immobile;
        $caratteristiche->areaFabbricabile = true;
        $caratteristiche->valoreVenale = 120000.0;
        $caratteristiche->validoDal = new SismaDate('2025-01-01');

        $this->testAdapter->impostaSelectCallback(function (string $query) use ($possesso, $caratteristiche): TestResultSet {
            if (str_contains($query, 'COUNT')) {
                return TestResultSet::conteggio(4);
            }
            if (str_contains($query, 'possesso')) {
                return new TestResultSet([$possesso]);
            }
            if (str_contains($query, 'caratteristiche_imu')) {
                return new TestResultSet([$caratteristiche]);
            }
            return new TestResultSet([]);
        });

        $proposta = new PropostaDichiarazione($this->dataMapper);
        $dichiarazioni = $proposta->proponi($soggetto, 2025);
        $this->assertCount(1, $dichiarazioni);
        $this->assertSame('H501', $dichiarazioni[0]->codiceComune);
        $this->assertSame(ModelloDichiarazione::ordinario, $dichiarazioni[0]->modello);

        // Determina quadro C ENC
        $dichEnc = new DichiarazioneImu($this->dataMapper);
        $dichEnc->soggetto = $soggetto;
        $dichEnc->anno = 2025;
        $dichEnc->codiceComune = 'H501';
        $dichEnc->modello = ModelloDichiarazione::entiNonCommerciali;
        $proposta->determinaQuadroC($dichEnc);
        $this->assertSame(0.0, $dichEnc->impostaDovuta);

        // QuadroImmobili
        $quadro = (new QuadroImmobili($this->dataMapper))->getImmobili($soggetto, 2025);
        $this->assertArrayHasKey(1, $quadro);
        $this->assertCount(1, $quadro[1]['possessi']);
        $this->assertCount(1, $quadro[1]['caratteristiche']);

        // RiepilogoScrivania
        $indicatori = (new RiepilogoScrivania($this->dataMapper))->getIndicatori($this->studioPredefinito);
        $this->assertCount(2, $indicatori);
        $this->assertSame(4, reset($indicatori));
    }
}
