<?php

namespace Imu\Tests;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Telonio\Tests\SuiteTestCase;
use Imu\Application\Entities\AccontoVersatoImu;
use Imu\Application\Entities\AgevolazioneImu;
use Imu\Application\Entities\AliquotaApplicata;
use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Entities\CalcoloImu;
use Imu\Application\Entities\CaratteristicheImu;
use Imu\Application\Entities\CoefficienteFabbricatiD;
use Imu\Application\Entities\ComuneEsenzioneTerreni;
use Imu\Application\Entities\CostoContabileImu;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\ProspettoAliquote;
use Imu\Application\Entities\RigoCalcoloImu;
use Imu\Application\Entities\RigoDichiarazioneImu;
use Imu\Application\Entities\UtilizzoMistoEnc;
use Imu\Application\Enumerations\AssimilazioneAbitazione;
use Imu\Application\Enumerations\AttivitaEnc;
use Imu\Application\Enumerations\CaratteristicaDichiarata;
use Imu\Application\Enumerations\CodiceCarica;
use Imu\Application\Enumerations\EsenzioneImu;
use Imu\Application\Enumerations\FattispecieImu;
use Imu\Application\Enumerations\ModelloDichiarazione;
use Imu\Application\Enumerations\MotivoEsenzioneTerreni;
use Imu\Application\Enumerations\StatoDichiarazione;
use Imu\Application\Enumerations\TipologiaDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;

class EntitiesAndEnumerationsTest extends SuiteTestCase
{

    public function testEnumerationsLabelsAndMethods(): void
    {
        foreach (AssimilazioneAbitazione::cases() as $caso) {
            $this->assertNotEmpty($caso->getLabel());
            $this->assertStringContainsString('comma 741, lett. c)', $caso->getRiferimento());
            $this->assertSame($caso === AssimilazioneAbitazione::anzianiDisabili, $caso->richiedeComune());
        }

        foreach (AttivitaEnc::cases() as $caso) {
            $this->assertNotEmpty($caso->getLabel());
        }
        $this->assertSame([AttivitaEnc::didattiche, AttivitaEnc::culturali], AttivitaEnc::daCodici('DI, CU, INVALIDO'));
        $this->assertSame([], AttivitaEnc::daCodici(''));

        foreach (CaratteristicaDichiarata::cases() as $caso) {
            $this->assertStringStartsWith($caso->value . ' - ', $caso->getLabel());
            $atteso = in_array($caso, [CaratteristicaDichiarata::terreno, CaratteristicaDichiarata::areaFabbricabile, CaratteristicaDichiarata::fabbricatoRendita, CaratteristicaDichiarata::fabbricatoContabile], true);
            $this->assertSame($atteso, $caso->isAmmessaEnc());
        }

        foreach (CodiceCarica::cases() as $caso) {
            $this->assertStringStartsWith($caso->value . ' - ', $caso->getLabel());
        }

        foreach (EsenzioneImu::cases() as $caso) {
            $this->assertNotEmpty($caso->getLabel());
            $this->assertNotEmpty($caso->getRiferimento());
        }

        foreach (FattispecieImu::cases() as $caso) {
            $this->assertNotEmpty($caso->getLabel());
            $this->assertGreaterThan(0.0, $caso->getAliquotaBase());
            $this->assertMatchesRegularExpression('/^39\d{2}$/', $caso->getCodiceTributo());
            $this->assertSame($caso === FattispecieImu::gruppoD ? '3925' : null, $caso->getCodiceTributoStato());
            $this->assertStringStartsWith('aliquota', $caso->getProprietaProspetto());
            $this->assertNotEmpty($caso->getVoceProspetto());
        }
        $this->assertCount(7, FattispecieImu::perAnno(2021));
        $this->assertCount(6, FattispecieImu::perAnno(2022));
        $this->assertNotContains(FattispecieImu::beniMerce, FattispecieImu::perAnno(2025));

        $this->assertSame('TAT00', ModelloDichiarazione::ordinario->getCodiceFornitura());
        $this->assertSame('TAS00', ModelloDichiarazione::entiNonCommerciali->getCodiceFornitura());
        $this->assertNotEmpty(ModelloDichiarazione::ordinario->getLabel());
        $this->assertNotEmpty(ModelloDichiarazione::entiNonCommerciali->getLabel());

        foreach (MotivoEsenzioneTerreni::cases() as $caso) {
            $this->assertNotEmpty($caso->getLabel());
            $this->assertStringContainsString('comma 758', $caso->getRiferimento());
        }

        $this->assertSame('Bozza', StatoDichiarazione::bozza->getLabel());
        $this->assertSame('text-bg-secondary', StatoDichiarazione::bozza->getClasseBadge());
        $this->assertSame('Presentata', StatoDichiarazione::presentata->getLabel());
        $this->assertSame('text-bg-success', StatoDichiarazione::presentata->getClasseBadge());

        $this->assertSame('Nuova', TipologiaDichiarazione::nuova->getLabel());
        $this->assertSame('Sostitutiva', TipologiaDichiarazione::sostitutiva->getLabel());
    }

    public function testAccontoAgevolazioneCaratteristicheAndAliquoteEntities(): void
    {
        $acconto = new AccontoVersatoImu($this->dataMapper);
        $acconto->codiceComune = 'H501';
        $acconto->codiceTributo = '3918';
        $acconto->importo = 450.0;
        $this->assertSame('H501|3918', $acconto->getChiave());

        $agevolazione = new AgevolazioneImu($this->dataMapper);
        $agevolazione->validoDal = new SismaDate('2024-01-01');
        $agevolazione->comodatoParenti = true;
        $agevolazione->assimilazione = AssimilazioneAbitazione::anzianiDisabili;
        $agevolazione->pensionatoEstero = true;
        $this->assertSame(['Comodato a parenti', 'Assimilata ad abitazione principale', 'Pensionato estero'], $agevolazione->getEtichette());
        $this->assertTrue($agevolazione->isVigenteAl(new SismaDate('2024-06-15')));

        $caratteristiche = new CaratteristicheImu($this->dataMapper);
        $caratteristiche->storicoArtistico = true;
        $caratteristiche->inagibile = true;
        $caratteristiche->areaFabbricabile = true;
        $caratteristiche->esenzione = EsenzioneImu::occupatoAbusivamente;
        $this->assertSame(['Storico/artistico', 'Inagibile', 'Area fabbricabile', 'Esente'], $caratteristiche->getEtichette());

        $prospetto = new ProspettoAliquote($this->dataMapper);
        $prospetto->denominazioneComune = 'Roma';
        $prospetto->codiceCatastale = 'H501';
        $prospetto->anno = 2025;
        $prospetto->idProspetto = 12345;
        $prospetto->aliquotaAltriFabbricati = 1.06;
        $this->assertTrue($prospetto->isPubblicato());
        $this->assertSame(1.06, $prospetto->getAliquota(FattispecieImu::altriFabbricati));
        $this->assertNull($prospetto->getAliquota(FattispecieImu::terreniAgricoli));
        $this->assertSame('Roma 2025', $prospetto->getEtichetta());
        $this->assertSame('prospetto n. 12345 del Dipartimento delle Finanze', $prospetto->getOrigine());

        $prospetto->exComune = 'Ostia';
        $prospetto->studio = $this->studioPredefinito;
        $this->assertFalse($prospetto->isPubblicato());
        $this->assertSame('Roma (ex Ostia) 2025', $prospetto->getEtichetta());
        $this->assertSame('aliquote inserite dallo studio', $prospetto->getOrigine());

        $differenziata = new AliquotaDifferenziata($this->dataMapper);
        $differenziata->prospettoAliquote = $prospetto;
        $differenziata->aliquota = 0.76;
        $differenziata->descrizione = "Abitazione locata\na canone concordato";
        $this->assertSame('0,76% - Abitazione locata a canone concordato', $differenziata->getEtichetta());
        $this->assertSame('0,76% - Abitazio…', $differenziata->getEtichetta(9));

        $applicata = new AliquotaApplicata($this->dataMapper);
        $applicata->aliquota = 0.95;
        $this->assertSame(0.95, $applicata->getValore());
        $applicata->aliquotaDifferenziata = $differenziata;
        $this->assertSame(0.76, $applicata->getValore());

        $coeff = new CoefficienteFabbricatiD($this->dataMapper);
        $coeff->annoImposta = 2025;
        $coeff->annoCosto = 2020;
        $coeff->coefficiente = 1.15;
        $coeff->fonte = 'DM 2025';
        $this->assertSame(1.15, $coeff->coefficiente);

        $comuneEsente = new ComuneEsenzioneTerreni($this->dataMapper);
        $comuneEsente->codiceCatastale = 'A001';
        $comuneEsente->motivo = MotivoEsenzioneTerreni::montano;
        $comuneEsente->denominazione = 'ABANO';
        $comuneEsente->fonte = 'Circolare 9/1993';
        $this->assertFalse($comuneEsente->parziale);

        $costo = new CostoContabileImu($this->dataMapper);
        $costo->anno = 2021;
        $costo->importo = 100000.0;
        $this->assertSame(100000.0, $costo->importo);
    }

    public function testCalcoloDichiarazioneRighiAndUtilizzoMistoEncEntities(): void
    {
        $calcolo = new CalcoloImu($this->dataMapper);
        $this->assertSame('imu-2025', CalcoloImu::getRiferimento(2025));
        $this->assertSame([], $calcolo->getAvvisi());
        $this->assertSame([], $calcolo->getDifferenze());
        $calcolo->avvisi = "Primo avviso\n\nSecondo avviso";
        $calcolo->differenze = "Diff 1";
        $this->assertSame(['Primo avviso', 'Secondo avviso'], $calcolo->getAvvisi());
        $this->assertSame(['Diff 1'], $calcolo->getDifferenze());

        $rigoCalcolo = new RigoCalcoloImu($this->dataMapper);
        $rigoCalcolo->baseImponibile = 100000.0;
        $rigoCalcolo->quota = 50.0;
        $rigoCalcolo->riduzioneBase = 0.5;
        $this->assertFalse($rigoCalcolo->isImponibile());
        $rigoCalcolo->fattispecie = FattispecieImu::altriFabbricati;
        $this->assertTrue($rigoCalcolo->isImponibile());
        $this->assertSame(25000.0, $rigoCalcolo->getBaseImponibileQuota());

        $dichiarazione = new DichiarazioneImu($this->dataMapper);
        $dichiarazione->anno = 2024;
        $this->assertTrue($dichiarazione->isModificabile());
        $this->assertFalse($dichiarazione->isEnc());
        $this->assertSame('2025-06-30', $dichiarazione->getScadenza()->format('Y-m-d'));
        $dichiarazione->modello = ModelloDichiarazione::entiNonCommerciali;
        $dichiarazione->stato = StatoDichiarazione::presentata;
        $dichiarazione->impostaDovuta = 1000.0;
        $dichiarazione->eccedenzaPrecedente = 200.0;
        $dichiarazione->eccedenzaCompensata = 50.0;
        $dichiarazione->rateVersate = 600.0;
        $this->assertFalse($dichiarazione->isModificabile());
        $this->assertTrue($dichiarazione->isEnc());
        $this->assertSame(250.0, $dichiarazione->getSaldoQuadroC());

        $rigoDich = new RigoDichiarazioneImu($this->dataMapper);
        $rigoDich->acquisto = true;
        $rigoDich->altro = true;
        $rigoDich->altroDescrizione = 'Variazione area';
        $rigoDich->inizioTermine = 'I';
        $this->assertSame('Acquisto, Variazione area, Inizio agevolazione', $rigoDich->getVicenda());

        $utilizzo = new UtilizzoMistoEnc($this->dataMapper);
        $utilizzo->attivita = 'DI,CU';
        $this->assertTrue($utilizzo->isDidattica());
        $this->assertTrue($utilizzo->hasAltreAttivita());
        $this->assertTrue($utilizzo->hasAttivita(AttivitaEnc::culturali));

        $utilizzo->esente = true;
        $this->assertSame(0.0, $utilizzo->getPercentualeImponibilita('didattica'));
        $this->assertSame(['imponibile' => 0.0, 'esente' => 1000.0, 'parziale' => 0.0], $utilizzo->getValori(1000.0));
        $this->assertFalse($utilizzo->isInteramenteImponibile());

        $utilizzo->esente = false;
        $utilizzo->didatticaSuperficie = 20.0;
        $utilizzo->didatticaGiorni = 10.0;
        $utilizzo->didatticaCorrispettivoMedio = 2000.0;
        $utilizzo->didatticaCostoMedioStudente = 1500.0;
        $this->assertSame(30.0, $utilizzo->getPercentualeImponibilita('didattica'));
        $this->assertSame(75.0, $utilizzo->getRapportoCmsCm());
        $valori = $utilizzo->getValori(1000.0);
        // imponibile = 300, esente = 700, parziale = 700 * (1 - 0.75) = 175
        $this->assertSame(['imponibile' => 300.0, 'esente' => 700.0, 'parziale' => 175.0], $valori);
        $this->assertSame(0.475, $utilizzo->getCoefficienteImponibile());

        // Superamento 99,99% => interamente imponibile
        $utilizzo->didatticaSuperficie = 90.0;
        $utilizzo->didatticaGiorni = 20.0;
        $this->assertSame(99.99, $utilizzo->getPercentualeImponibilita('didattica'));
        $this->assertTrue($utilizzo->isInteramenteImponibile());
    }
}
