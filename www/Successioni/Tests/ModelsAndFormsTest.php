<?php

namespace Successioni\Tests;

use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Entities\AllegatoSuccessione;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\ComuneCatastaleTavolare;
use Successioni\Application\Entities\ContenutoAllegato;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\EstremoVoltura;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\RipartizionePassivita;
use Successioni\Application\Entities\UfficioRegistrazione;
use Successioni\Application\Entities\UfficioTrascrizione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Application\Enumerations\TipoTitolo;
use Successioni\Application\Forms\BeneficiarioSuccessioneForm;
use Successioni\Application\Forms\BeneSuccessioneForm;
use Successioni\Application\Forms\DichiarazioneSuccessioneForm;
use Successioni\Application\Forms\PagamentoSuccessioneForm;
use Successioni\Application\Forms\PassivitaSuccessioneForm;
use Successioni\Application\Models\AllegatoSuccessioneModel;
use Successioni\Application\Models\BeneficiarioSuccessioneModel;
use Successioni\Application\Models\BeneSuccessioneModel;
use Successioni\Application\Models\ComuneCatastaleTavolareModel;
use Successioni\Application\Models\ContenutoAllegatoModel;
use Successioni\Application\Models\DevoluzioneSuccessioneModel;
use Successioni\Application\Models\DichiarazioneSuccessioneModel;
use Successioni\Application\Models\EstremoVolturaModel;
use Successioni\Application\Models\PassivitaSuccessioneModel;
use Successioni\Application\Models\RipartizionePassivitaModel;
use Successioni\Application\Models\UfficioRegistrazioneModel;
use Successioni\Application\Models\UfficioTrascrizioneModel;

class ModelsAndFormsTest extends SuiteTestCase
{

    public function testModels(): void
    {
        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        $defunto->dataDecesso = new SismaDate('2025-01-10');

        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->id = 1;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->defunto = $defunto;

        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->id = 1;
        $beneficiario->ordine = 2;

        $bene = new BeneSuccessione($this->dataMapper);
        $bene->id = 1;
        $bene->quadro = QuadroBene::altriBeni;
        $bene->ordine = 3;

        $passivita = new PassivitaSuccessione($this->dataMapper);
        $passivita->id = 1;
        $passivita->ordine = 4;

        $ufficioReg = new UfficioRegistrazione($this->dataMapper);
        $ufficioReg->id = 1;
        $ufficioReg->codice = 'TG2';
        $ufficioReg->denominazione = 'ROMA 2';

        $ufficioTrasc = new UfficioTrascrizione($this->dataMapper);
        $ufficioTrasc->id = 1;
        $ufficioTrasc->codice = 'RM10';
        $ufficioTrasc->denominazione = 'ROMA 1';

        $comuneTav = new ComuneCatastaleTavolare($this->dataMapper);
        $comuneTav->id = 1;
        $comuneTav->codice = '001';
        $comuneTav->denominazione = 'ALA';

        $this->adapter->impostaSelectCallback(function (string $query) use ($dichiarazione, $beneficiario, $bene, $passivita, $ufficioReg, $ufficioTrasc, $comuneTav): TestResultSet {
            if (str_contains($query, 'COUNT')) {
                return TestResultSet::conteggio(2);
            }
            if (str_contains($query, 'FROM `dichiarazione_successione`')) {
                return new TestResultSet([$dichiarazione]);
            }
            if (str_contains($query, 'FROM `beneficiario_successione`')) {
                return new TestResultSet([$beneficiario]);
            }
            if (str_contains($query, 'FROM `bene_successione`')) {
                return new TestResultSet([$bene]);
            }
            if (str_contains($query, 'FROM `passivita_successione`')) {
                return new TestResultSet([$passivita]);
            }
            if (str_contains($query, 'FROM `ufficio_registrazione`')) {
                return new TestResultSet([$ufficioReg]);
            }
            if (str_contains($query, 'FROM `ufficio_trascrizione`')) {
                return new TestResultSet([$ufficioTrasc]);
            }
            if (str_contains($query, 'FROM `comune_catastale_tavolare`')) {
                return new TestResultSet([$comuneTav]);
            }
            return new TestResultSet();
        });

        $dichModel = new DichiarazioneSuccessioneModel($this->dataMapper);
        $this->assertCount(1, $dichModel->getElenco($defunto));
        $this->assertSame(2, $dichModel->contaPerStudio($this->studioTest));

        $benModel = new BeneficiarioSuccessioneModel($this->dataMapper);
        $this->assertCount(1, $benModel->getElenco($dichiarazione));
        $this->assertSame(3, $benModel->getProssimoOrdine($dichiarazione));

        $beneModel = new BeneSuccessioneModel($this->dataMapper);
        $this->assertCount(1, $beneModel->getElenco($dichiarazione));
        $perQuadro = $beneModel->getPerQuadro($dichiarazione);
        $this->assertCount(1, $perQuadro[QuadroBene::altriBeni->value]);
        $this->assertSame(4, $beneModel->getProssimoOrdine($dichiarazione, QuadroBene::altriBeni));

        $devModel = new DevoluzioneSuccessioneModel($this->dataMapper);
        $this->assertCount(0, $devModel->getElenco($bene));
        $this->assertSame(2, $devModel->contaPerBeneficiario($beneficiario));

        $estremoModel = new EstremoVolturaModel($this->dataMapper);
        $this->assertCount(0, $estremoModel->getElenco($bene));

        $passModel = new PassivitaSuccessioneModel($this->dataMapper);
        $this->assertCount(1, $passModel->getElenco($dichiarazione));
        $this->assertSame(5, $passModel->getProssimoOrdine($dichiarazione));

        $ripModel = new RipartizionePassivitaModel($this->dataMapper);
        $this->assertCount(0, $ripModel->getElenco($passivita));
        $this->assertSame(2, $ripModel->contaPerBeneficiario($beneficiario));

        $allModel = new AllegatoSuccessioneModel($this->dataMapper);
        $this->assertCount(0, $allModel->getElenco($dichiarazione));

        $contModel = new ContenutoAllegatoModel($this->dataMapper);
        $this->assertCount(0, $contModel->getEntityCollection());

        $uffRegModel = new UfficioRegistrazioneModel($this->dataMapper);
        $this->assertCount(1, $uffRegModel->getEntityCollection('ROMA'));
        $this->assertSame('TG2', $uffRegModel->getByCodiceUfficio('TG2')?->codice);

        $uffTrascModel = new UfficioTrascrizioneModel($this->dataMapper);
        $this->assertCount(1, $uffTrascModel->getEntityCollection('ROMA'));

        $comTavModel = new ComuneCatastaleTavolareModel($this->dataMapper);
        $this->assertNull($comTavModel->trovaPerNome(''));
        $this->assertSame('001', $comTavModel->trovaPerNome(' ala ')?->codice);
    }

    public function testDichiarazioneAndPagamentoForms(): void
    {
        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        $defunto->dataDecesso = new SismaDate('2025-01-10');
        $presentatore = $this->creaSoggettoFisico(11, $this->studioTest, 'RSSGPP80A01H501K', 'ROSSI', 'GIUSEPPE');

        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->id = 1;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->defunto = $defunto;

        $this->adapter->impostaSelectCallback(function (string $query, array $bindValues) use ($defunto, $presentatore): TestResultSet {
            if (str_contains($query, 'FROM `soggetto`')) {
                return in_array(10, $bindValues, true) ? new TestResultSet([$defunto]) : new TestResultSet([$presentatore]);
            }
            return new TestResultSet();
        });

        // Form valido
        $formValido = new DichiarazioneSuccessioneForm($dichiarazione);
        $formValido->handleRequest($this->creaRequest([
            'tipoDichiarazione' => TipoDichiarazioneSuccessione::prima->value,
            'devoluzioneLegge' => '1',
            'devoluzioneTestamento' => '0',
            'testamentoEstero' => '0',
            'leggeEstera' => '0',
            'eventiEccezionali' => '0',
            'beneficioInventario' => '0',
            'statoDecesso' => 'FRANCIA',
            'statoCivile' => StatoCivileDefunto::vedovo->value,
            'separazioneColpa' => '0',
            'presentatore' => '11',
            'codiceCarica' => CaricaDichiarante::erede->value,
            'telefono' => '06 123456',
            'email' => '  test@example.it  ',
            'senzaVolture' => '0',
            'copiaConforme' => '0',
            'primaCasaTrasferimentoPrima' => '0',
            'primaCasaNessunaAltraComune' => '1',
            'testamentoSerie' => '1t',
        ]));
        $this->assertTrue($formValido->isValid());
        $risolta = $formValido->resolveEntity();
        $this->assertSame('1T', $risolta->testamentoSerie);
        $this->assertSame('test@example.it', $risolta->email);

        // Form non valido (sostitutiva senza estremi, nessuna devoluzione, dichiarante = defunto)
        $formErr = new DichiarazioneSuccessioneForm($dichiarazione);
        $formErr->handleRequest($this->creaRequest([
            'tipoDichiarazione' => TipoDichiarazioneSuccessione::sostitutivaConTrascrizione->value,
            'devoluzioneLegge' => '0',
            'devoluzioneTestamento' => '0',
            'testamentoEstero' => '0',
            'leggeEstera' => '0',
            'eventiEccezionali' => '0',
            'beneficioInventario' => '0',
            'separazioneColpa' => '0',
            'presentatore' => '10',
            'senzaVolture' => '0',
            'copiaConforme' => '0',
            'primaCasaTrasferimentoPrima' => '0',
            'primaCasaNessunaAltraComune' => '1',
            'telefono' => 'abc',
            'testamentoSerie' => '9Z',
        ]));
        $this->assertFalse($formErr->isValid());

        // PagamentoSuccessioneForm valido
        $pagForm = new PagamentoSuccessioneForm($dichiarazione);
        $pagForm->handleRequest($this->creaRequest([
            'tempisticaPagamento' => TempisticaPagamento::nonContestuale->value,
            'numeroRate' => '4',
            'acconto' => '2.000',
        ]));
        $this->assertTrue($pagForm->isValid());

        // PagamentoSuccessioneForm non valido (rate > 12 e senza acconto)
        $pagFormErr = new PagamentoSuccessioneForm($dichiarazione);
        $pagFormErr->handleRequest($this->creaRequest([
            'numeroRate' => '15',
            'ipotecariaVersata' => '-10',
        ]));
        $this->assertFalse($pagFormErr->isValid());
    }

    public function testBeneficiarioBeneAndPassivitaForms(): void
    {
        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        $defunto->dataDecesso = new SismaDate('2025-01-10');
        $erede = $this->creaSoggettoFisico(11, $this->studioTest, 'RSSGPP80A01H501K', 'ROSSI', 'GIUSEPPE');

        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->id = 1;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->defunto = $defunto;

        $this->adapter->impostaSelectCallback(function (string $query, array $bindValues) use ($defunto, $erede): TestResultSet {
            if (str_contains($query, 'FROM `soggetto`')) {
                return in_array(10, $bindValues, true) ? new TestResultSet([$defunto]) : new TestResultSet([$erede]);
            }
            return new TestResultSet();
        });

        // BeneficiarioSuccessioneForm valido
        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->dichiarazioneSuccessione = $dichiarazione;
        $benForm = new BeneficiarioSuccessioneForm($beneficiario);
        $benForm->handleRequest($this->creaRequest([
            'soggetto' => '11',
            'tipoBeneficiario' => TipoBeneficiario::erede->value,
            'gradoParentela' => GradoParentela::figlio->value,
            'disabile' => '0',
            'rinuncia' => '0',
        ]));
        $this->assertTrue($benForm->isValid());

        // BeneficiarioSuccessioneForm non valido (defunto stesso, disabile senza certificatore, rinuncia senza estremi)
        $benFormErr = new BeneficiarioSuccessioneForm($beneficiario);
        $benFormErr->handleRequest($this->creaRequest([
            'soggetto' => '10',
            'tipoBeneficiario' => TipoBeneficiario::coniugeRinunciatario->value,
            'gradoParentela' => GradoParentela::figlio->value,
            'disabile' => '1',
            'rinuncia' => '1',
        ]));
        $this->assertFalse($benFormErr->isValid());

        // BeneSuccessioneForm valido (quadro ER)
        $bene = new BeneSuccessione($this->dataMapper);
        $bene->dichiarazioneSuccessione = $dichiarazione;
        $bene->quadro = QuadroBene::altriBeni;
        $beneForm = new BeneSuccessioneForm($bene);
        $beneForm->handleRequest($this->creaRequest([
            'codiceDiritto' => CodiceDiritto::proprieta->value,
            'quotaNumeratore' => '1',
            'quotaDenominatore' => '1',
            'valore' => '10.000',
            'beneEstero' => '0',
            'descrizione' => 'Conto corrente',
            'tipoCespite' => TipoCespite::crediti->value,
        ]));
        $this->assertTrue($beneForm->isValid());

        // BeneSuccessioneForm non valido (quadro EO titolo esente senza valoreEsente e quota > 1)
        $beneTitolo = new BeneSuccessione($this->dataMapper);
        $beneTitolo->dichiarazioneSuccessione = $dichiarazione;
        $beneTitolo->quadro = QuadroBene::titoli;
        $beneFormErr = new BeneSuccessioneForm($beneTitolo);
        $beneFormErr->handleRequest($this->creaRequest([
            'codiceDiritto' => CodiceDiritto::usufrutto->value,
            'quotaNumeratore' => '2',
            'quotaDenominatore' => '1',
            'valore' => '5000',
            'impostaEstero' => '100',
            'beneEstero' => '0',
            'descrizione' => 'BTP',
            'tipoTitolo' => TipoTitolo::esente->value,
            'codiceFiscaleEmittente' => 'INVALIDO',
        ]));
        $this->assertFalse($beneFormErr->isValid());

        // PassivitaSuccessioneForm valido
        $passivita = new PassivitaSuccessione($this->dataMapper);
        $passivita->dichiarazioneSuccessione = $dichiarazione;
        $passForm = new PassivitaSuccessioneForm($passivita);
        $passForm->handleRequest($this->creaRequest([
            'tipoPassivita' => TipoPassivita::speseFunerarie->value,
            'descrizione' => 'Spese funebri',
            'estremiDocumento' => 'Fatt. 1',
            'dataDocumento' => '2025-01-15',
            'valore' => '1.000',
        ]));
        $this->assertTrue($passForm->isValid());

        // PassivitaSuccessioneForm non valido (spese funerarie > 1033)
        $passFormErr = new PassivitaSuccessioneForm($passivita);
        $passFormErr->handleRequest($this->creaRequest([
            'tipoPassivita' => TipoPassivita::speseFunerarie->value,
            'descrizione' => 'Spese funebri',
            'estremiDocumento' => 'Fatt. 1',
            'dataDocumento' => '2025-01-15',
            'valore' => '2.000',
        ]));
        $this->assertFalse($passFormErr->isValid());
    }
}
