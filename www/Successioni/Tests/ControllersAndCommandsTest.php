<?php

namespace Successioni\Tests;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoCatasto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use SismaFramework\Core\Enumerations\ResponseType;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Controllers\AllegatoSuccessioneController;
use Successioni\Application\Controllers\BeneficiarioSuccessioneController;
use Successioni\Application\Controllers\BeneSuccessioneController;
use Successioni\Application\Controllers\DefuntoController;
use Successioni\Application\Controllers\DichiarazioneSuccessioneController;
use Successioni\Application\Controllers\PassivitaSuccessioneController;
use Successioni\Application\Controllers\TabelleSuccessioniController;
use Successioni\Application\Entities\AllegatoSuccessione;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\ContenutoAllegato;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\UfficioRegistrazione;
use Successioni\Application\Entities\UfficioTrascrizione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoAllegato;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Console\Commands\SpecificheSuccessioniCommand;

class ControllersAndCommandsTest extends SuiteTestCase
{

    private function preparaContesto(): array
    {
        $this->catturaRenderERouter();
        $this->autenticaUtente($this->utenteCorrente);

        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'ROMA';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';

        $nazione = new Nazione($this->dataMapper);
        $nazione->id = 1;
        $nazione->codiceIso = 'IT';
        $nazione->denominazione = 'ITALIA';

        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        $defunto->sesso = Sesso::maschio;
        $defunto->dataNascita = new SismaDate('1950-01-01');
        $defunto->comuneNascita = $comune;
        $defunto->dataDecesso = new SismaDate('2025-03-10');

        $erede = $this->creaSoggettoFisico(11, $this->studioTest, 'RSSGPP80A01H501K', 'ROSSI', 'GIUSEPPE');
        $erede->sesso = Sesso::maschio;
        $erede->dataNascita = new SismaDate('1980-01-01');
        $erede->comuneNascita = $comune;

        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->id = 1;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->defunto = $defunto;
        $dichiarazione->comuneDecesso = $comune;
        $dichiarazione->statoCivile = StatoCivileDefunto::vedovo;
        $dichiarazione->presentatore = $erede;
        $dichiarazione->codiceCarica = CaricaDichiarante::erede;
        $dichiarazione->email = 'giuseppe@example.it';
        $dichiarazione->tempisticaPagamento = TempisticaPagamento::nonContestuale;
        $dichiarazione->dataDichiarazione = new SismaDate('2025-06-01');

        $conto = new ContoCorrente($this->dataMapper);
        $conto->id = 1;
        $conto->soggetto = $erede;
        $conto->iban = 'IT60X0542811101000000123456';
        $dichiarazione->contoAddebito = $conto;

        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->id = 1;
        $beneficiario->dichiarazioneSuccessione = $dichiarazione;
        $beneficiario->soggetto = $erede;
        $beneficiario->tipoBeneficiario = TipoBeneficiario::erede;
        $beneficiario->gradoParentela = GradoParentela::figlio;

        $bene = new BeneSuccessione($this->dataMapper);
        $bene->id = 1;
        $bene->dichiarazioneSuccessione = $dichiarazione;
        $bene->quadro = QuadroBene::altriBeni;
        $bene->tipoCespite = TipoCespite::crediti;
        $bene->descrizione = 'CONTO CORRENTE';
        $bene->codiceDiritto = CodiceDiritto::proprieta;
        $bene->valore = 10000;

        $dev = new DevoluzioneSuccessione($this->dataMapper);
        $dev->id = 1;
        $dev->beneSuccessione = $bene;
        $dev->beneficiarioSuccessione = $beneficiario;
        $dev->quotaNumeratore = 1;
        $dev->quotaDenominatore = 1;
        $dev->codiceDiritto = CodiceDiritto::proprieta;
        $dev->valoreQuota = 10000.0;

        $contenutoAll = new ContenutoAllegato($this->dataMapper);
        $contenutoAll->id = 1;
        $contenutoAll->contenuto = base64_encode('%PDF-1.4 <pdfaid:part>1</pdfaid:part><pdfaid:conformance>B</pdfaid:conformance>');

        $allegato = new AllegatoSuccessione($this->dataMapper);
        $allegato->id = 1;
        $allegato->dichiarazioneSuccessione = $dichiarazione;
        $allegato->tipoAllegato = TipoAllegato::alberoGenealogico;
        $allegato->nomeFile = 'albero.pdf';
        $allegato->tipoMime = 'application/pdf';
        $allegato->dimensione = 80;
        $allegato->contenutoAllegato = $contenutoAll;

        $utente = $this->utenteCorrente;
        $this->adapter->impostaSelectCallback(function (string $query, array $bindValues) use ($utente, $defunto, $erede, $dichiarazione, $beneficiario, $bene, $dev, $allegato): TestResultSet {
            if (str_contains($query, 'COUNT')) {
                return TestResultSet::conteggio(0);
            }
            if (str_contains($query, 'FROM `utente`')) {
                return new TestResultSet([$utente]);
            }
            if (str_contains($query, 'FROM `soggetto`')) {
                return in_array(10, $bindValues, true) ? new TestResultSet([$defunto]) : new TestResultSet([$erede]);
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
            if (str_contains($query, 'FROM `devoluzione_successione`')) {
                return new TestResultSet([$dev]);
            }
            if (str_contains($query, 'FROM `allegato_successione`')) {
                return new TestResultSet([$allegato]);
            }
            return new TestResultSet();
        });

        return [$defunto, $erede, $dichiarazione, $beneficiario, $bene, $allegato];
    }

    public function testDefuntoAndDichiarazioneControllers(): void
    {
        [$defunto, $erede, $dichiarazione] = $this->preparaContesto();

        $defuntoCtrl = new DefuntoController($this->dataMapper);
        $this->assertNotNull($defuntoCtrl->index());
        $this->assertNotNull($defuntoCtrl->show($defunto));

        $dichCtrl = new DichiarazioneSuccessioneController($this->dataMapper);
        $this->assertNotNull($dichCtrl->create($this->creaRequest(), $defunto));
        $this->assertNotNull($dichCtrl->show($dichiarazione, 'generale'));
        $this->assertNotNull($dichCtrl->show($dichiarazione, 'volture'));
        $this->assertNotNull($dichCtrl->update($this->creaRequest([], [], [], 'GET'), $dichiarazione));
        $this->assertNotNull($dichCtrl->pagamento($this->creaRequest([], [], [], 'GET'), $dichiarazione));
        $this->assertNotNull($dichCtrl->importaImmobili($this->creaRequest(), $dichiarazione));
        $this->assertNotNull($dichCtrl->proponiDevoluzioni($this->creaRequest(), $dichiarazione));
        $this->assertNotNull($dichCtrl->versamenti($this->creaRequest(), $dichiarazione));
        $this->assertNotNull($dichCtrl->stampa($dichiarazione));
        $this->assertNotNull($dichCtrl->scarica($dichiarazione));

        // Stato presentata -> sostitutiva -> volture -> riporta in bozza -> delete
        $this->assertNotNull($dichCtrl->stato($this->creaRequest(['dataPresentazione' => '2025-05-10', 'protocollo' => '12345']), $dichiarazione, StatoDichiarazioneSuccessione::presentata));
        $this->assertSame(StatoDichiarazioneSuccessione::presentata, $dichiarazione->stato);
        $this->assertNotNull($dichCtrl->sostitutiva($this->creaRequest(), $dichiarazione));
        $this->assertNotNull($dichCtrl->volture($this->creaRequest(), $dichiarazione));
        $this->assertNotNull($dichCtrl->stato($this->creaRequest(), $dichiarazione, StatoDichiarazioneSuccessione::bozza));
        $this->assertNotNull($dichCtrl->delete($this->creaRequest(), $dichiarazione));
    }

    public function testBeneficiarioBenePassivitaAllegatoAndTabelleControllers(): void
    {
        [$defunto, $erede, $dichiarazione, $beneficiario, $bene, $allegato] = $this->preparaContesto();

        $benCtrl = new BeneficiarioSuccessioneController($this->dataMapper);
        $this->assertNotNull($benCtrl->create($this->creaRequest([], [], [], 'GET'), $dichiarazione));
        $this->assertNotNull($benCtrl->update($this->creaRequest([], [], [], 'GET'), $beneficiario));
        $this->assertNotNull($benCtrl->delete($this->creaRequest(), $beneficiario));

        $beneCtrl = new BeneSuccessioneController($this->dataMapper);
        $this->assertNotNull($beneCtrl->create($this->creaRequest([], [], [], 'GET'), $dichiarazione, QuadroBene::altriBeni));
        $this->assertNotNull($beneCtrl->update($this->creaRequest([], [], [], 'GET'), $bene));
        $this->assertNotNull($beneCtrl->devoluzioni($this->creaRequest([], [], [], 'GET'), $bene));
        $this->assertNotNull($beneCtrl->devoluzioni($this->creaRequest([
            'submitted' => '1',
            'righe' => [
                ['beneficiario' => '1', 'numeratore' => '1', 'denominatore' => '1', 'diritto' => CodiceDiritto::proprieta->value, 'valore' => '10000', 'agevolazione' => '', 'riduzione' => ''],
            ],
        ]), $bene));
        // Estremi su quadro ER reindirizza con avviso; su quadro EC mostra form
        $this->assertNotNull($beneCtrl->estremi($this->creaRequest([], [], [], 'GET'), $bene));
        $bene->quadro = QuadroBene::fabbricati;
        $this->assertNotNull($beneCtrl->estremi($this->creaRequest([], [], [], 'GET'), $bene));
        $this->assertNotNull($beneCtrl->delete($this->creaRequest(), $bene));

        $passivita = new PassivitaSuccessione($this->dataMapper);
        $passivita->id = 1;
        $passivita->dichiarazioneSuccessione = $dichiarazione;
        $passivita->tipoPassivita = TipoPassivita::speseFunerarie;
        $passivita->descrizione = 'Spese funebri';
        $passivita->estremiDocumento = 'Fatt. 1';
        $passivita->dataDocumento = new SismaDate('2025-03-12');
        $passivita->valore = 500;

        $passCtrl = new PassivitaSuccessioneController($this->dataMapper);
        $this->assertNotNull($passCtrl->create($this->creaRequest([], [], [], 'GET'), $dichiarazione));
        $this->assertNotNull($passCtrl->update($this->creaRequest([], [], [], 'GET'), $passivita));
        $this->assertNotNull($passCtrl->create($this->creaRequest([
            'tipoPassivita' => TipoPassivita::speseFunerarie->value,
            'descrizione' => 'Spese funebri',
            'estremiDocumento' => 'Fatt. 1',
            'dataDocumento' => '2025-03-12',
            'valore' => '500',
            'quote' => ['1' => '1/1'],
        ]), $dichiarazione));
        $this->assertNotNull($passCtrl->delete($this->creaRequest(), $passivita));

        $allCtrl = new AllegatoSuccessioneController($this->dataMapper);
        $this->assertNotNull($allCtrl->create($this->creaRequest(), $dichiarazione));
        $this->assertNotNull($allCtrl->scarica($allegato));
        $this->assertNotNull($allCtrl->delete($this->creaRequest(), $allegato));

        $ufficio = new UfficioRegistrazione($this->dataMapper);
        $ufficio->id = 1;
        $ufficio->codice = 'TG2';
        $ufficio->denominazione = 'ROMA 2';
        $utente = $this->utenteCorrente;
        $this->adapter->impostaSelectCallback(function (string $query) use ($utente, $ufficio): TestResultSet {
            if (str_contains($query, 'FROM `utente`')) {
                return new TestResultSet([$utente]);
            }
            if (str_contains($query, 'FROM `ufficio_registrazione`')) {
                return new TestResultSet([$ufficio]);
            }
            return new TestResultSet();
        });
        $tabCtrl = new TabelleSuccessioniController($this->dataMapper);
        $this->assertNotNull($tabCtrl->uffici('RO'));
    }

    public function testSpecificheSuccessioniCommand(): void
    {
        $command = new SpecificheSuccessioniCommand();
        $this->assertTrue($command->checkCompatibility('specifiche-successioni'));
        $this->assertFalse($command->checkCompatibility('altro'));

        $command->setOptions(['solo-verifica' => '1']);
        ob_start();
        $esito = $command->run();
        $output = ob_get_clean();
        $this->assertTrue($esito);
        $this->assertStringContainsString('Codici degli enum allineati alle specifiche', (string) $output);
    }
}
