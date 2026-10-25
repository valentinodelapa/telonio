<?php

namespace Imu\Tests;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Deleghe\Application\Entities\Intermediario;
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
use Imu\Application\Enumerations\CaratteristicaDichiarata;
use Imu\Application\Enumerations\CodiceCarica;
use Imu\Application\Enumerations\ModelloDichiarazione;
use Imu\Application\Enumerations\MotivoEsenzioneTerreni;
use Imu\Application\Enumerations\TipologiaDichiarazione;
use Imu\Application\Forms\AccontoVersatoImuForm;
use Imu\Application\Forms\AgevolazioneImuForm;
use Imu\Application\Forms\AliquotaApplicataForm;
use Imu\Application\Forms\CaratteristicheImuForm;
use Imu\Application\Forms\CostoContabileImuForm;
use Imu\Application\Forms\DichiarazioneImuForm;
use Imu\Application\Forms\ProspettoAliquoteForm;
use Imu\Application\Forms\RigoDichiarazioneImuForm;
use Imu\Application\Forms\UtilizzoMistoEncForm;
use Imu\Application\Models\AccontoVersatoImuModel;
use Imu\Application\Models\AgevolazioneImuModel;
use Imu\Application\Models\AliquotaApplicataModel;
use Imu\Application\Models\AliquotaDifferenziataModel;
use Imu\Application\Models\CalcoloImuModel;
use Imu\Application\Models\CaratteristicheImuModel;
use Imu\Application\Models\CoefficienteFabbricatiDModel;
use Imu\Application\Models\ComuneEsenzioneTerreniModel;
use Imu\Application\Models\CostoContabileImuModel;
use Imu\Application\Models\DichiarazioneImuModel;
use Imu\Application\Models\ProspettoAliquoteModel;
use Imu\Application\Models\RigoCalcoloImuModel;
use Imu\Application\Models\RigoDichiarazioneImuModel;
use Imu\Application\Models\UtilizzoMistoEncModel;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ModelsAndFormsTest extends SuiteTestCase
{

    public function testAllImuModels(): void
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 1;
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 10;

        $acconto = new AccontoVersatoImu($this->dataMapper);
        $acconto->codiceComune = 'H501';
        $acconto->codiceTributo = '3918';
        $acconto->importo = 100.0;
        $this->testAdapter->accodaSelect(new TestResultSet([$acconto]));
        $mappaAcconti = (new AccontoVersatoImuModel($this->dataMapper))->getPerAnno($soggetto, 2025);
        $this->assertArrayHasKey('H501|3918', $mappaAcconti);

        $agevolazione = new AgevolazioneImu($this->dataMapper);
        $agevolazione->validoDal = new SismaDate('2024-01-01');
        $this->testAdapter->accodaSelect(new TestResultSet([$agevolazione]));
        $this->assertSame($agevolazione, (new AgevolazioneImuModel($this->dataMapper))->getVigenteAl($soggetto, $immobile, new SismaDate('2025-01-01')));

        $applicata = new AliquotaApplicata($this->dataMapper);
        $applicata->immobile = $immobile;
        $applicata->aliquota = 0.86;
        $this->testAdapter->accodaSelect(new TestResultSet([$applicata]), new TestResultSet([$applicata]));
        $aaModel = new AliquotaApplicataModel($this->dataMapper);
        $this->assertSame($applicata, $aaModel->getPerAnno($soggetto, $immobile, 2025));
        $this->assertArrayHasKey(10, $aaModel->getPerImmobile($soggetto, 2025));

        $prospetto = new ProspettoAliquote($this->dataMapper);
        $prospetto->id = 5;
        $prospetto->codiceCatastale = 'H501';
        $prospetto->anno = 2025;
        $diff = new AliquotaDifferenziata($this->dataMapper);
        $diff->prospettoAliquote = $prospetto;
        $this->testAdapter->accodaSelect(new TestResultSet([$diff]));
        $this->assertCount(1, (new AliquotaDifferenziataModel($this->dataMapper))->getElenco($prospetto));

        $calcolo = new CalcoloImu($this->dataMapper);
        $calcolo->id = 3;
        $this->testAdapter->accodaSelect(new TestResultSet([$calcolo]), new TestResultSet([$calcolo]), TestResultSet::conteggio(7));
        $calcoloModel = new CalcoloImuModel($this->dataMapper);
        $this->assertSame($calcolo, $calcoloModel->getPerAnno($soggetto, 2025));
        $this->assertCount(1, $calcoloModel->getElenco($soggetto));
        $this->assertSame(7, $calcoloModel->contaPerAnno($this->studioPredefinito, 2025));

        $caratt = new CaratteristicheImu($this->dataMapper);
        $caratt->validoDal = new SismaDate('2024-01-01');
        $this->testAdapter->accodaSelect(new TestResultSet([$caratt]));
        $this->assertSame($caratt, (new CaratteristicheImuModel($this->dataMapper))->getVigenteAl($immobile, new SismaDate('2025-01-01')));

        $coeff = new CoefficienteFabbricatiD($this->dataMapper);
        $coeff->annoImposta = 2025;
        $coeff->annoCosto = 2020;
        $coeff->coefficiente = 1.25;
        $this->testAdapter->accodaSelect(new TestResultSet([$coeff]));
        $this->assertSame([2020 => 1.25], (new CoefficienteFabbricatiDModel($this->dataMapper))->getPerAnno(2025));

        $esenzione = new ComuneEsenzioneTerreni($this->dataMapper);
        $esenzione->codiceCatastale = 'A001';
        $esenzione->motivo = MotivoEsenzioneTerreni::montano;
        $this->testAdapter->accodaSelect(new TestResultSet([$esenzione]));
        $this->assertCount(1, (new ComuneEsenzioneTerreniModel($this->dataMapper))->getPerComune('A001'));

        $costo = new CostoContabileImu($this->dataMapper);
        $costo->anno = 2022;
        $this->testAdapter->accodaSelect(new TestResultSet([$costo]));
        $this->assertCount(1, (new CostoContabileImuModel($this->dataMapper))->getElenco($immobile));

        $dich = new DichiarazioneImu($this->dataMapper);
        $dich->id = 8;
        $this->testAdapter->accodaSelect(new TestResultSet([$dich]), new TestResultSet([$dich]));
        $dichModel = new DichiarazioneImuModel($this->dataMapper);
        $this->assertCount(1, $dichModel->getElenco($soggetto, 2024));
        $this->assertSame($dich, $dichModel->getPerComune($soggetto, 2024, 'H501'));

        $this->testAdapter->accodaSelect(
            new TestResultSet([]), // getDelloStudio
            new TestResultSet([$prospetto]), // getPubblicato
            new TestResultSet([$prospetto]), // getStoricoComune
            new TestResultSet([$prospetto]) // getPubblicatiAnno
        );
        $paModel = new ProspettoAliquoteModel($this->dataMapper);
        $this->assertSame($prospetto, $paModel->getPerStudio($this->studioPredefinito, 'H501', 2025));
        $this->assertCount(1, $paModel->getStoricoComune($this->studioPredefinito, 'H501'));
        $this->assertCount(1, $paModel->getPubblicatiAnno(2025));

        $rigoC = new RigoCalcoloImu($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$rigoC]));
        $this->assertCount(1, (new RigoCalcoloImuModel($this->dataMapper))->getRighi($calcolo));

        $rigoD = new RigoDichiarazioneImu($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$rigoD]));
        $this->assertCount(1, (new RigoDichiarazioneImuModel($this->dataMapper))->getRighi($dich));

        $utilizzo = new UtilizzoMistoEnc($this->dataMapper);
        $utilizzo->immobile = $immobile;
        $this->testAdapter->accodaSelect(new TestResultSet([$utilizzo]), new TestResultSet([$utilizzo]));
        $uModel = new UtilizzoMistoEncModel($this->dataMapper);
        $this->assertSame($utilizzo, $uModel->getPerAnno($soggetto, $immobile, 2025));
        $this->assertArrayHasKey(10, $uModel->getPerImmobile($soggetto, 2025));
    }

    public function testAllImuFormsValidation(): void
    {
        // 1. AccontoVersatoImuForm
        $acconto = new AccontoVersatoImu($this->dataMapper);
        $formAcconto = new AccontoVersatoImuForm($acconto);
        $formAcconto->handleRequest($this->creaRequest(['codiceComune' => ' h501 ', 'codiceTributo' => '3918', 'importo' => '150,50']));
        $this->assertTrue($formAcconto->isValid());

        $formAccontoErr = new AccontoVersatoImuForm(new AccontoVersatoImu($this->dataMapper));
        $formAccontoErr->handleRequest($this->creaRequest(['codiceComune' => 'XXXX', 'codiceTributo' => '9999', 'importo' => '-10']));
        $this->assertFalse($formAccontoErr->isValid());

        // 2. AgevolazioneImuForm
        $immobileTerreno = new Immobile($this->dataMapper);
        $immobileTerreno->tipoCatasto = TipoCatasto::terreni;
        $agev = new AgevolazioneImu($this->dataMapper);
        $agev->immobile = $immobileTerreno;
        $formAgevErr = new AgevolazioneImuForm($agev);
        $formAgevErr->handleRequest($this->creaRequest(['validoDal' => '2025-01-01', 'comodatoParenti' => '1', 'conduzioneAgricola' => '0', 'pensionatoEstero' => '0']));
        $this->assertFalse($formAgevErr->isValid());

        $immobileFab = new Immobile($this->dataMapper);
        $immobileFab->tipoCatasto = TipoCatasto::fabbricati;
        $agevFab = new AgevolazioneImu($this->dataMapper);
        $agevFab->immobile = $immobileFab;
        $formAgevOk = new AgevolazioneImuForm($agevFab);
        $formAgevOk->handleRequest($this->creaRequest(['validoDal' => '2025-01-01', 'comodatoParenti' => '1', 'conduzioneAgricola' => '0', 'pensionatoEstero' => '0']));
        $this->assertTrue($formAgevOk->isValid());

        // 3. AliquotaApplicataForm
        $formAliquota = new AliquotaApplicataForm(new AliquotaApplicata($this->dataMapper));
        $formAliquota->handleRequest($this->creaRequest(['aliquota' => '0,96%', 'motivo' => 'Delibera']));
        $this->assertTrue($formAliquota->isValid());

        $formAliquotaErr = new AliquotaApplicataForm(new AliquotaApplicata($this->dataMapper));
        $formAliquotaErr->handleRequest($this->creaRequest(['aliquota' => '1,50']));
        $this->assertFalse($formAliquotaErr->isValid());

        // 4. CaratteristicheImuForm
        $formCaratt = new CaratteristicheImuForm(new CaratteristicheImu($this->dataMapper));
        $formCaratt->handleRequest($this->creaRequest([
            'validoDal' => '2025-01-01', 'storicoArtistico' => '0', 'inagibile' => '0', 'ruraleStrumentale' => '0',
            'beneMerce' => '0', 'areaFabbricabile' => '1', 'valoreVenale' => '100.000,00', 'valoreContabile' => '0',
        ]));
        $this->assertTrue($formCaratt->isValid());

        $formCarattErr = new CaratteristicheImuForm(new CaratteristicheImu($this->dataMapper));
        $formCarattErr->handleRequest($this->creaRequest([
            'validoDal' => '2025-01-01', 'storicoArtistico' => '0', 'inagibile' => '0', 'ruraleStrumentale' => '0',
            'beneMerce' => '0', 'areaFabbricabile' => '1', 'valoreContabile' => '0',
        ]));
        $this->assertFalse($formCarattErr->isValid());

        // 5. CostoContabileImuForm
        $formCosto = new CostoContabileImuForm(new CostoContabileImu($this->dataMapper));
        $formCosto->handleRequest($this->creaRequest(['anno' => '2022', 'importo' => '50.000,00', 'descrizione' => 'Acquisto']));
        $this->assertTrue($formCosto->isValid());

        $formCostoErr = new CostoContabileImuForm(new CostoContabileImu($this->dataMapper));
        $formCostoErr->handleRequest($this->creaRequest(['anno' => '1850', 'importo' => '50000']));
        $this->assertFalse($formCostoErr->isValid());

        // 6. DichiarazioneImuForm (ENC)
        $dichEnc = new DichiarazioneImu($this->dataMapper);
        $dichEnc->modello = ModelloDichiarazione::entiNonCommerciali;
        $formDich = new DichiarazioneImuForm($dichEnc);
        $formDich->handleRequest($this->creaRequest([
            'tipologia' => TipologiaDichiarazione::nuova->value,
            'impostaDovuta' => '1000', 'rateVersate' => '1200', 'creditoRimborso' => '150', 'creditoCompensazione' => '50',
        ]));
        $this->assertTrue($formDich->isValid());

        $formDichErr = new DichiarazioneImuForm($dichEnc);
        $formDichErr->handleRequest($this->creaRequest([
            'tipologia' => TipologiaDichiarazione::nuova->value,
            'impostaDovuta' => '1000', 'rateVersate' => '1200', 'creditoRimborso' => '300',
        ]));
        $this->assertFalse($formDichErr->isValid());

        // 7. ProspettoAliquoteForm
        $prospetto = new ProspettoAliquote($this->dataMapper);
        $prospetto->anno = 2025;
        $formProspetto = new ProspettoAliquoteForm($prospetto);
        $formProspetto->handleRequest($this->creaRequest([
            'aliquotaAltriFabbricati' => '1,06%', 'assimilazioneAnziani' => '1',
            'detrazioneAbitazionePrincipale' => '200', 'importoMinimo' => '12',
        ]));
        $this->assertTrue($formProspetto->isValid());

        // 8. RigoDichiarazioneImuForm
        $dichOrd = new DichiarazioneImu($this->dataMapper);
        $dichOrd->modello = ModelloDichiarazione::ordinario;
        $rigoD = new RigoDichiarazioneImu($this->dataMapper);
        $rigoD->dichiarazioneImu = $dichOrd;
        $immobileFab->id = 10;
        $this->testAdapter->accodaSelect(new TestResultSet([$immobileFab]));
        $formRigo = new RigoDichiarazioneImuForm($rigoD);
        $formRigo->handleRequest($this->creaRequest([
            'immobile' => '10', 'quadro' => 'A', 'caratteristica' => CaratteristicaDichiarata::fabbricatoRendita->value,
            'valore' => '100.000', 'percentualePossesso' => '100', 'riduzione' => '0', 'esenzione' => '0',
            'acquisto' => '1', 'cessione' => '0', 'altro' => '0', 'dataVariazione' => '2024-05-01',
        ]));
        $this->assertTrue($formRigo->isValid());

        // 9. UtilizzoMistoEncForm
        $formUtilizzo = new UtilizzoMistoEncForm(new UtilizzoMistoEnc($this->dataMapper));
        $formUtilizzo->handleRequest($this->creaRequest([
            'attivita' => ['DI', 'CU'], 'esente' => '0', 'didatticaSuperficie' => '35,5%',
        ]));
        $this->assertTrue($formUtilizzo->isValid());

        $formUtilizzoErr = new UtilizzoMistoEncForm(new UtilizzoMistoEnc($this->dataMapper));
        $formUtilizzoErr->handleRequest($this->creaRequest(['attivita' => [], 'esente' => '1']));
        $this->assertFalse($formUtilizzoErr->isValid());
    }
}
