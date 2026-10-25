<?php

namespace Imu\Tests;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Imu\Application\Controllers\AccontoVersatoImuController;
use Imu\Application\Controllers\AgevolazioneImuController;
use Imu\Application\Controllers\AliquotaApplicataController;
use Imu\Application\Controllers\CalcoloImuController;
use Imu\Application\Controllers\CaratteristicheImuController;
use Imu\Application\Controllers\ContribuenteImuController;
use Imu\Application\Controllers\CostoContabileImuController;
use Imu\Application\Controllers\DichiarazioneImuController;
use Imu\Application\Controllers\ProspettoAliquoteController;
use Imu\Application\Controllers\RigoDichiarazioneImuController;
use Imu\Application\Controllers\UtilizzoMistoEncController;
use Imu\Application\Entities\AccontoVersatoImu;
use Imu\Application\Entities\AgevolazioneImu;
use Imu\Application\Entities\AliquotaApplicata;
use Imu\Application\Entities\CalcoloImu;
use Imu\Application\Entities\CaratteristicheImu;
use Imu\Application\Entities\CostoContabileImu;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\ProspettoAliquote;
use Imu\Application\Entities\RigoDichiarazioneImu;
use Imu\Application\Entities\UtilizzoMistoEnc;
use Imu\Application\Enumerations\StatoDichiarazione;
use Imu\Console\Commands\AliquoteImuCommand;
use Imu\Console\Commands\TabelleImuCommand;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ControllersAndCommandsTest extends SuiteTestCase
{

    private function creaSoggettoEDipendenze(): array
    {
        $this->autenticaUtente($this->utenteCorrente);
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->codiceCatastale = 'H501';
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';

        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 10;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';

        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 20;
        $immobile->studio = $this->studioPredefinito;
        $immobile->comune = $comune;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->foglio = '1';
        $immobile->particella = '100';

        return [$comune, $soggetto, $immobile];
    }

    public function testContribuenteAndCalcoloControllers(): void
    {
        $cattura = $this->catturaRenderERouter();
        [$comune, $soggetto, $immobile] = $this->creaSoggettoEDipendenze();

        $this->testAdapter->impostaSelectCallback(function (string $query) use ($soggetto): TestResultSet {
            if (str_contains($query, 'FROM `utente`')) {
                return new TestResultSet([$this->utenteCorrente]);
            }
            if (str_contains($query, 'COUNT')) {
                return TestResultSet::conteggio(1);
            }
            if (str_contains($query, 'FROM `soggetto`')) {
                return new TestResultSet([$soggetto]);
            }
            return new TestResultSet([]);
        });

        $controller = new ContribuenteImuController($this->dataMapper);
        $controller->index('', false, 1, 2025);
        $this->assertSame('contribuenteImu/index', $cattura->vista);
        $this->assertCount(1, $cattura->vars['contribuenti']);

        $controller->show($soggetto, 2025);
        $this->assertSame('contribuenteImu/show', $cattura->vista);
        $this->assertSame(2025, $cattura->vars['anno']);

        // CalcoloImuController: elabora, stampa, delete
        $calcoloCtrl = new CalcoloImuController($this->dataMapper);
        $calcoloCtrl->elabora($this->creaRequest(['unicaSoluzione' => '1']), $soggetto, 2025);
        $this->assertStringContainsString('contribuente-imu/show/soggetto/10/anno/2025', (string) $cattura->redirectUrl);

        $calcolo = new CalcoloImu($this->dataMapper);
        $calcolo->id = 5;
        $calcolo->studio = $this->studioPredefinito;
        $calcolo->soggetto = $soggetto;
        $calcolo->anno = 2025;

        $calcoloCtrl->stampa($calcolo);
        $this->assertSame('calcoloImu/stampa', $cattura->vista);

        $this->testAdapter->impostaSelectCallback(function (string $query): TestResultSet {
            if (str_contains($query, 'FROM `utente`')) {
                return new TestResultSet([$this->utenteCorrente]);
            }
            if (str_contains($query, 'COUNT')) {
                return TestResultSet::conteggio(0);
            }
            return new TestResultSet([]);
        });

        $calcoloCtrl->delete($this->creaRequest(), $calcolo);
        $this->assertStringContainsString('contribuente-imu/show/soggetto/10/anno/2025', (string) $cattura->redirectUrl);
    }

    public function testAccontoAgevolazioneCaratteristicheAliquotaCostoUtilizzoControllers(): void
    {
        $cattura = $this->catturaRenderERouter();
        [$comune, $soggetto, $immobile] = $this->creaSoggettoEDipendenze();

        $this->testAdapter->impostaSelectCallback(function (string $query): TestResultSet {
            if (str_contains($query, 'FROM `utente`')) {
                return new TestResultSet([$this->utenteCorrente]);
            }
            if (str_contains($query, 'COUNT')) {
                return TestResultSet::conteggio(0);
            }
            return new TestResultSet([]);
        });

        // AccontoVersatoImuController
        $accontoCtrl = new AccontoVersatoImuController($this->dataMapper);
        $accontoCtrl->create($this->creaRequest([], [], [], 'GET'), $soggetto, 2025);
        $this->assertSame('accontoVersatoImu/form', $cattura->vista);

        $acconto = new AccontoVersatoImu($this->dataMapper);
        $acconto->id = 1;
        $acconto->soggetto = $soggetto;
        $acconto->anno = 2025;
        $accontoCtrl->delete($this->creaRequest(), $acconto);
        $this->assertStringContainsString('contribuente-imu/show/soggetto/10/anno/2025', (string) $cattura->redirectUrl);

        // AgevolazioneImuController
        $agevCtrl = new AgevolazioneImuController($this->dataMapper);
        $agevCtrl->create($this->creaRequest([], [], [], 'GET'), $soggetto, $immobile, 2025);
        $this->assertSame('agevolazioneImu/form', $cattura->vista);

        // CaratteristicheImuController
        $carattCtrl = new CaratteristicheImuController($this->dataMapper);
        $carattCtrl->create($this->creaRequest([], [], [], 'GET'), $immobile, $soggetto, 2025);
        $this->assertSame('caratteristicheImu/form', $cattura->vista);

        // AliquotaApplicataController
        $aliqCtrl = new AliquotaApplicataController($this->dataMapper);
        $aliqCtrl->create($this->creaRequest([], [], [], 'GET'), $soggetto, $immobile, 2025);
        $this->assertSame('aliquotaApplicata/form', $cattura->vista);

        // CostoContabileImuController
        $costoCtrl = new CostoContabileImuController($this->dataMapper);
        $costoCtrl->index($this->creaRequest([], [], [], 'GET'), $immobile, $soggetto, 2025);
        $this->assertSame('costoContabileImu/index', $cattura->vista);

        // UtilizzoMistoEncController
        $utilizzoCtrl = new UtilizzoMistoEncController($this->dataMapper);
        $utilizzoCtrl->create($this->creaRequest([], [], [], 'GET'), $soggetto, $immobile, 2025);
        $this->assertSame('utilizzoMistoEnc/form', $cattura->vista);
    }

    public function testDichiarazioneRigoAndProspettoAliquoteControllers(): void
    {
        $cattura = $this->catturaRenderERouter();
        [$comune, $soggetto, $immobile] = $this->creaSoggettoEDipendenze();

        $this->testAdapter->impostaSelectCallback(function (string $query) use ($comune): TestResultSet {
            if (str_contains($query, 'FROM `utente`')) {
                return new TestResultSet([$this->utenteCorrente]);
            }
            if (str_contains($query, 'COUNT')) {
                return TestResultSet::conteggio(0);
            }
            if (str_contains($query, 'FROM `comune`')) {
                return new TestResultSet([$comune]);
            }
            return new TestResultSet([]);
        });

        $dich = new DichiarazioneImu($this->dataMapper);
        $dich->id = 15;
        $dich->studio = $this->studioPredefinito;
        $dich->soggetto = $soggetto;
        $dich->anno = 2024;
        $dich->codiceComune = 'H501';
        $dich->denominazioneComune = 'Roma';

        $dichCtrl = new DichiarazioneImuController($this->dataMapper);
        $dichCtrl->proponi($this->creaRequest(), $soggetto, 2024);
        $this->assertStringContainsString('contribuente-imu/show/soggetto/10/anno/2024', (string) $cattura->redirectUrl);

        $dichCtrl->show($dich);
        $this->assertSame('dichiarazioneImu/show', $cattura->vista);

        $dichCtrl->stampa($dich);
        $this->assertSame('dichiarazioneImu/stampa', $cattura->vista);

        $dichCtrl->sostitutiva($this->creaRequest(), $dich);
        $this->assertStringContainsString('dichiarazione-imu/show/dichiarazione/', (string) $cattura->redirectUrl);

        // RigoDichiarazioneImuController
        $rigoCtrl = new RigoDichiarazioneImuController($this->dataMapper);
        $rigoCtrl->create($this->creaRequest([], [], [], 'GET'), $dich);
        $this->assertSame('rigoDichiarazioneImu/form', $cattura->vista);

        // ProspettoAliquoteController
        $prospCtrl = new ProspettoAliquoteController($this->dataMapper);
        $prospCtrl->index(2025);
        $this->assertSame('prospettoAliquote/index', $cattura->vista);

        $prospettoPub = new ProspettoAliquote($this->dataMapper);
        $prospettoPub->id = 30;
        $prospettoPub->codiceCatastale = 'H501';
        $prospettoPub->denominazioneComune = 'Roma';
        $prospettoPub->anno = 2025;
        $prospCtrl->show($prospettoPub);
        $this->assertSame('prospettoAliquote/show', $cattura->vista);

        // Un prospetto pubblicato (studio = null) non può essere modificato dallo studio
        $this->expectException(AccessDeniedException::class);
        $prospCtrl->update($this->creaRequest([], [], [], 'GET'), $prospettoPub);
    }

    public function testAliquoteImuAndTabelleImuCommands(): void
    {
        $cmdAliquote = new AliquoteImuCommand();
        $this->assertTrue($cmdAliquote->checkCompatibility('aliquote-imu'));
        $this->assertFalse($cmdAliquote->checkCompatibility('altro'));

        // Anno < 2025 restituisce false con messaggio di errore catturato
        $cmdAliquote->setOptions(['anno' => '2024', 'comuni' => 'H501']);
        ob_start();
        $esito = $cmdAliquote->run();
        $output = ob_get_clean();
        $this->assertFalse($esito);
        $this->assertStringContainsString('2025', (string) $output);

        $cmdTabelle = new TabelleImuCommand();
        $this->assertTrue($cmdTabelle->checkCompatibility('tabelle-imu'));
        $this->assertFalse($cmdTabelle->checkCompatibility('aliquote-imu'));
    }
}
