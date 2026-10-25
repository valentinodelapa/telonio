<?php

namespace Deleghe\Tests;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Deleghe\Application\Controllers\CodiceTributoController;
use Deleghe\Application\Controllers\ContribuenteController;
use Deleghe\Application\Controllers\CreditoController;
use Deleghe\Application\Controllers\DelegaController;
use Deleghe\Application\Controllers\IntermediarioController;
use Deleghe\Application\Controllers\InvioTelematicoController;
use Deleghe\Application\Controllers\RavvedimentoController;
use Deleghe\Application\Controllers\SanzioneCivileController;
use Deleghe\Application\Controllers\TabelleF24Controller;
use Deleghe\Application\Controllers\VersamentoController;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Entities\CodiceTabellaF24;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Intermediario;
use Deleghe\Application\Entities\InvioTelematico;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\ObbligoCampo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Console\Commands\ModelliF24Command;
use Deleghe\Console\Commands\TabelleF24Command;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;

class ControllersAndCommandsTest extends SuiteTestCase
{

    private object $cattura;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->autenticaUtente($this->utenteCorrente);
        $this->cattura = $this->catturaRenderERouter();
    }

    private function accodaUtente(): void
    {
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
    }

    private function creaSoggettoTest(int $id = 1): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = $id;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->cognome = 'Rossi';
        $soggetto->nome = 'Mario';
        $soggetto->nominativo = 'Rossi Mario';
        $soggetto->sesso = Sesso::from('M');
        $soggetto->dataNascita = new SismaDate('1980-01-01');
        $comune = new Comune($this->dataMapper);
        $comune->id = 10;
        $comune->codiceCatastale = 'H501';
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $soggetto->comuneNascita = $comune;
        return $soggetto;
    }

    public function testCodiceTributoAndTabelleF24Controllers(): void
    {
        $ct = new CodiceTributo($this->dataMapper);
        $ct->id = 10;
        $ct->sezione = SezioneCodiceTributo::erario;
        $ct->codice = '4001';
        $ct->descrizione = 'IRPEF saldo';
        $ct->inUso = true;

        $this->accodaUtente();
        $this->testAdapter->accodaSelect(
                TestResultSet::conteggio(1),
                new TestResultSet([$ct]),
                new TestResultSet([$ct]),
        );
        $controller = new CodiceTributoController($this->dataMapper);
        $controller->index('4001', 'ER', false, 1);
        $this->assertSame('codiceTributo/index', $this->cattura->vista);

        $controller->cerca(SezioneCodiceTributo::erario, '40');
        $this->assertCount(1, $this->cattura->json);
        $this->assertSame(10, $this->cattura->json[0]['id']);

        // TabelleF24Controller
        $voce = new CodiceTabellaF24($this->dataMapper);
        $voce->id = 1;
        $voce->tabella = TabellaF24::regioni;
        $voce->codice = '08';
        $voce->descrizione = 'Lazio';
        $voce->inUso = true;

        $causale = new CausaleContributo($this->dataMapper);
        $causale->id = 2;
        $causale->ente = 'INPS';
        $causale->causale = 'DM10';
        $causale->descrizione = 'Dipendenti';
        $causale->inUso = true;

        $this->accodaUtente();
        $this->testAdapter->accodaSelect(
                new TestResultSet([$voce]),
                new TestResultSet([$causale]),
        );
        $tabController = new TabelleF24Controller($this->dataMapper);
        $tabController->voci(TabellaF24::regioni, 'la');
        $this->assertSame('08', $this->cattura->json[0]['id']);

        $tabController->causali('INPS', 'D');
        $this->assertSame('DM10', $this->cattura->json[0]['id']);
    }

    public function testContribuenteAndCreditoControllers(): void
    {
        $soggetto = $this->creaSoggettoTest();

        $this->accodaUtente();
        $this->testAdapter->accodaSelect(
                TestResultSet::conteggio(1),
                new TestResultSet([$soggetto]),
                new TestResultSet([]), // getDaVersare
                TestResultSet::conteggio(1), // bozze
                TestResultSet::conteggio(0), // confermate
                new TestResultSet([]), // show -> getDaVersare
                new TestResultSet([]), // show -> crediti
                new TestResultSet([]), // show -> deleghe
        );
        $contribController = new ContribuenteController($this->dataMapper);
        $contribController->index();
        $this->assertSame('contribuente/index', $this->cattura->vista);

        $contribController->show($soggetto, 'versamenti');
        $this->assertSame('contribuente/show', $this->cattura->vista);

        // CreditoController
        $this->accodaUtente();
        $credController = new CreditoController($this->dataMapper);
        // Sezione ELIDE non ammette crediti
        $credController->create($this->creaRequest(), $soggetto, SezioneF24::elide);
        $this->assertStringContainsString('contribuente/show/soggetto/1/vista/crediti', $this->cattura->redirectUrl);

        // GET create erario
        $credController->create($this->creaRequest(), $soggetto, SezioneF24::erario);
        $this->assertSame('credito/form', $this->cattura->vista);

        // delete credito non utilizzato
        $credito = new Credito($this->dataMapper);
        $credito->id = 5;
        $credito->studio = $this->studioPredefinito;
        $credito->soggetto = $soggetto;
        $credito->sezione = SezioneF24::erario;
        $this->testAdapter->impostaSelectCallback(fn() => TestResultSet::conteggio(0));
        $credController->delete($this->creaRequest([], [], [], 'POST', true), $credito);
        $this->assertStringContainsString('contribuente/show/soggetto/1/vista/crediti', $this->cattura->redirectUrl);
        $this->testAdapter->impostaSelectCallback(null);
    }

    public function testDelegaAndVersamentoControllers(): void
    {
        $soggetto = $this->creaSoggettoTest();
        $delega = new Delega($this->dataMapper);
        $delega->id = 10;
        $delega->studio = $this->studioPredefinito;
        $delega->soggetto = $soggetto;
        $delega->modello = ModelloF24::ordinario;
        $delega->scadenza = new SismaDate('2026-06-16');
        $delega->stato = StatoDelega::bozza;

        $this->accodaUtente();
        $delegaController = new DelegaController($this->dataMapper);
        $delegaController->index();
        $this->assertSame('contribuente/index', $this->cattura->redirectUrl);

        // show delega
        $this->testAdapter->accodaSelect(
                new TestResultSet([]), // getPerSezione
                new TestResultSet([]), // verifica -> getPerSezione
                new TestResultSet([]), // regioni
                new TestResultSet([]), // ravvedimenti
                new TestResultSet([]), // sanzioniCivili
                new TestResultSet([]), // daVersare
                new TestResultSet([]), // creditiDisponibili
        );
        $delegaController->show($delega);
        $this->assertSame('delega/show', $this->cattura->vista);

        // stampa delega
        $this->testAdapter->accodaSelect(
                new TestResultSet([]), // verifica -> getPerSezione
                new TestResultSet([]), // anteprima -> getPerSezione
                new TestResultSet([]), // getDomicilio
        );
        $delegaController->stampa($delega, false);
        $this->assertSame('delega/stampa', $this->cattura->vista);

        // delete delega in bozza
        $this->testAdapter->impostaSelectCallback(function (string $q) {
            return str_contains($q, 'COUNT') ? TestResultSet::conteggio(0) : new TestResultSet([]);
        });
        $delegaController->delete($this->creaRequest([], [], [], 'POST', true), $delega);
        $this->assertSame('contribuente/show/soggetto/1', $this->cattura->redirectUrl);
        $this->testAdapter->impostaSelectCallback(null);
        $delega->id = 10;

        // VersamentoController
        $this->accodaUtente();
        $versController = new VersamentoController($this->dataMapper);
        $versController->create($this->creaRequest(), $soggetto, SezioneF24::erario);
        $this->assertSame('versamento/form', $this->cattura->vista);

        $versController->aggiungi($this->creaRequest(), $delega, SezioneF24::elide);
        $this->assertSame('delega/show/delega/10', $this->cattura->redirectUrl);

        $versamento = new Versamento($this->dataMapper);
        $versamento->id = 30;
        $versamento->studio = $this->studioPredefinito;
        $versamento->soggetto = $soggetto;
        $versamento->delega = $delega;
        $versamento->scadenza = $delega->scadenza;
        $versamento->sezione = SezioneF24::erario;
        $versamento->importoDebito = 100.0;
        $versamento->importoCredito = 0.0;
        $versamento->detrazioneImu = null;

        $versController->rimuovi($this->creaRequest([], [], [], 'POST', true), $versamento);
        $this->assertNull($versamento->delega);
        $this->assertSame('delega/show/delega/10', $this->cattura->redirectUrl);
    }

    public function testIntermediarioInvioRavvedimentoAndSanzioneControllers(): void
    {
        $soggetto = $this->creaSoggettoTest();
        $delega = new Delega($this->dataMapper);
        $delega->id = 10;
        $delega->studio = $this->studioPredefinito;
        $delega->soggetto = $soggetto;
        $delega->modello = ModelloF24::ordinario;
        $delega->scadenza = new SismaDate('2026-06-16');
        $delega->stato = StatoDelega::bozza;

        // IntermediarioController
        $this->accodaUtente();
        $this->testAdapter->accodaSelect(
                new TestResultSet([]), // index
                new TestResultSet([]), // create -> getPredefinito
        );
        $intController = new IntermediarioController($this->dataMapper);
        $intController->index();
        $this->assertSame('intermediario/index', $this->cattura->vista);
        $intController->create($this->creaRequest());
        $this->assertSame('intermediario/form', $this->cattura->vista);

        // InvioTelematicoController
        $this->accodaUtente();
        $invio = new InvioTelematico($this->dataMapper);
        $invio->id = 5;
        $invio->studio = $this->studioPredefinito;
        $invio->dataCreazione = new SismaDateTime('2026-06-15 10:00:00');
        $invio->nomeFile = 'F24_test.txt';
        $invio->contenuto = 'A...Z';

        $this->testAdapter->accodaSelect(
                new TestResultSet([$invio]), // index
                new TestResultSet([]), // create senza intermediari
                new TestResultSet([]), // show -> getByInvioTelematico
        );
        $invioController = new InvioTelematicoController($this->dataMapper);
        $invioController->index();
        $this->assertSame('invioTelematico/index', $this->cattura->vista);

        $invioController->create($this->creaRequest());
        $this->assertSame('intermediario/create', $this->cattura->redirectUrl);

        $invioController->show($invio);
        $this->assertSame('invioTelematico/show', $this->cattura->vista);

        // RavvedimentoController & SanzioneCivileController
        $this->accodaUtente();
        $this->testAdapter->accodaSelect(new TestResultSet([])); // descriviRegole
        $ravvController = new RavvedimentoController($this->dataMapper);
        $ravvController->create($this->creaRequest(), $delega, SezioneF24::erario);
        $this->assertSame('ravvedimento/form', $this->cattura->vista);

        $this->accodaUtente();
        $sanzController = new SanzioneCivileController($this->dataMapper);
        $sanzController->create($this->creaRequest(), $delega, SezioneF24::inps);
        $this->assertSame('sanzioneCivile/form', $this->cattura->vista);
    }

    public function testConsoleCommands(): void
    {
        $modelliCmd = new ModelliF24Command();
        $this->assertTrue($modelliCmd->checkCompatibility('modelli-f24'));
        $this->assertFalse($modelliCmd->checkCompatibility('altro'));

        $tabelleCmd = new TabelleF24Command();
        $this->assertTrue($tabelleCmd->checkCompatibility('tabelle-f24'));
        $this->assertFalse($tabelleCmd->checkCompatibility('altro'));
    }
}
