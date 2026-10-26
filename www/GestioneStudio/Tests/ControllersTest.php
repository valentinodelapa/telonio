<?php

namespace GestioneStudio\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Tests\TestResultSet;
use GestioneStudio\Application\Controllers\AdempimentoController;
use GestioneStudio\Application\Controllers\AssegnazioneAdempimentoController;
use GestioneStudio\Application\Controllers\ProrogaAdempimentoController;
use GestioneStudio\Application\Controllers\ScadenzarioController;
use GestioneStudio\Application\Controllers\TermineAdempimentoController;
use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Enumerations\StatoLavorazione;
use GestioneStudio\Application\Services\VoceScadenzario;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ControllersTest extends GestioneStudioTestCase
{

    /** @var array<string, array> righe restituite per tabella */
    private array $tabelle = [];
    private int $conteggio = 0;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->autenticaUtente($this->utenteCorrente);
        $this->tabelle = ['utente' => [$this->utenteCorrente]];
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio($this->conteggio);
            }
            if (preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1) {
                return new TestResultSet($this->tabelle[$tabella[1]] ?? []);
            }
            return new TestResultSet([]);
        });
    }

    private function creaScenario(): array
    {
        $soggetto = $this->creaSoggetto();
        $adempimento = $this->creaAdempimento(10, 'REDDITI_PF');
        $termine = $this->creaTermine($adempimento, 1, 'anno', 10, 31, 1);
        $assegnazione = $this->creaAssegnazione($soggetto, $adempimento, '2025-01-01');
        $this->tabelle += ['soggetto' => [$soggetto], 'adempimento' => [$adempimento], 'termine_adempimento' => [$termine], 'assegnazione_adempimento' => [$assegnazione]];
        return [$soggetto, $adempimento, $termine, $assegnazione];
    }

    public function testScadenzarioIndexClientiESoggetto(): void
    {
        $cattura = $this->catturaRenderERouter();
        [$soggetto] = $this->creaScenario();
        $controller = new ScadenzarioController($this->dataMapper);

        $controller->index('2026-01-01', '2026-12-31', 'tutte');
        $this->assertSame('scadenzario/index', $cattura->vista);
        $this->assertCount(1, $cattura->vars['voci']);
        $this->assertSame('Adempimento REDDITI_PF - anno 2025', $cattura->vars['voci'][0]->scadenza->descrizione);
        $this->assertSame('2026-11-02', $cattura->vars['voci'][0]->scadenza->data->format('Y-m-d'));
        $this->assertContains(\Telonio\Application\Enumerations\ModuloSuite::gestioneStudio, $cattura->vars['moduliFonte']);
        $this->assertStringStartsWith('/scadenzario/index?', VoceScadenzario::decodifica($cattura->vars['ritorno']));

        // periodo non valido: si torna al periodo predefinito
        $controller->index('2026-12-31', '2026-01-01');
        $this->assertSame((new SismaDate('-' . ScadenzarioController::GIORNI_PRECEDENTI . ' days'))->format('Y-m-d'), $cattura->vars['dal']->format('Y-m-d'));
        $controller->index('2020-01-01', '2026-01-01');
        $this->assertSame((new SismaDate('+' . ScadenzarioController::GIORNI_SUCCESSIVI . ' days'))->format('Y-m-d'), $cattura->vars['al']->format('Y-m-d'));

        $this->conteggio = 1;
        $controller->clienti('rossi');
        $this->assertSame('scadenzario/clienti', $cattura->vista);
        $this->assertSame(1, $cattura->vars['clienti'][0]['adempimenti']);

        $controller->soggetto($soggetto, 2026);
        $this->assertSame('scadenzario/soggetto', $cattura->vista);
        $this->assertCount(1, $cattura->vars['assegnazioni']);
        $this->assertCount(1, $cattura->vars['voci']);

        $estraneo = $this->creaSoggetto(99);
        $estraneo->studio = $this->creaStudio(2, 'Altro studio', '80012345676');
        $this->expectException(AccessDeniedException::class);
        $controller->soggetto($estraneo);
    }

    public function testLavorazioneEStatoDellaScadenza(): void
    {
        $cattura = $this->catturaRenderERouter();
        [$soggetto] = $this->creaScenario();
        $controller = new ScadenzarioController($this->dataMapper);
        $rif = VoceScadenzario::codifica('T1001/2025/S50');
        $ritorno = VoceScadenzario::codifica('/scadenzario/soggetto/soggetto/50');

        $controller->lavorazione($this->creaRequest([], [], [], 'GET'), 'GestioneStudio', $rif, '2026-11-02', 50, $ritorno);
        $this->assertSame('scadenzario/lavorazione', $cattura->vista);
        $this->assertSame('/scadenzario/soggetto/soggetto/50', $cattura->vars['urlAnnulla']);

        $controller->lavorazione($this->creaRequest(['stato' => 'L', 'responsabile' => '1', 'note' => 'Documenti richiesti']), 'GestioneStudio', $rif, '2026-11-02', 50, $ritorno);
        $this->assertSame('scadenzario/soggetto/soggetto/50', $cattura->redirectUrl);
        $this->assertTrue($this->eseguitaQuery('INSERT INTO `lavorazione_scadenza`'));

        $controller->stato($this->creaRequest(), 'GestioneStudio', $rif, '2026-11-02', StatoLavorazione::completata->value, 50, $ritorno);
        $this->assertSame('scadenzario/soggetto/soggetto/50', $cattura->redirectUrl);

        // scadenza non più presente (data cambiata): messaggio e ritorno
        $controller->stato($this->creaRequest(), 'GestioneStudio', $rif, '2026-10-31', 'C', 50, '');
        $this->assertSame('scadenzario/index', $cattura->redirectUrl);
        $controller->lavorazione($this->creaRequest([], [], [], 'GET'), 'Inesistente', $rif, '2026-11-02');
        $this->assertSame('scadenzario/index', $cattura->redirectUrl);

        // pagina di ritorno fuori dallo scadenzario
        $this->expectException(AccessDeniedException::class);
        $controller->stato($this->creaRequest(), 'GestioneStudio', $rif, '2026-11-02', 'C', 50, VoceScadenzario::codifica('https://esterno.example'));
    }

    private function eseguitaQuery(string $inizio): bool
    {
        foreach ($this->testAdapter->queryEseguite as $query) {
            if (str_starts_with(ltrim($query['cmd']), $inizio)) {
                return true;
            }
        }
        return false;
    }

    public function testCalendarioDegliAdempimenti(): void
    {
        $cattura = $this->catturaRenderERouter();
        [, $adempimento, $termine] = $this->creaScenario();
        $controller = new AdempimentoController($this->dataMapper);

        $controller->index(2026);
        $this->assertSame('adempimento/index', $cattura->vista);
        $this->assertSame(2026, $cattura->vars['anno']);

        $controller->show($adempimento, 2025);
        $this->assertSame('adempimento/show', $cattura->vista);
        $this->assertSame('2026-11-02', $cattura->vars['termini'][0]['effettivo']->data->format('Y-m-d'));
        $this->assertFalse($cattura->vars['modificabile']);

        $controller->create($this->creaRequest([], [], [], 'GET'));
        $this->assertSame('adempimento/form', $cattura->vista);

        // codice già usato da un adempimento del calendario
        $controller->create($this->creaRequest(['codice' => 'redditi_pf', 'denominazione' => 'Prova', 'categoria' => 'ALT', 'ambito' => 'TUT', 'slittamentoFestivi' => '1', 'attivo' => '1']));
        $this->assertSame('adempimento/form', $cattura->vista);
        $this->assertNotSame('', $cattura->vars['errors']->codiceCustomMessage);

        $this->tabelle['adempimento'] = [];
        $controller->create($this->creaRequest(['codice' => 'interno', 'denominazione' => 'Scadenza interna', 'categoria' => 'ALT', 'ambito' => 'TUT', 'slittamentoFestivi' => '1', 'attivo' => '1']));
        $this->assertStringStartsWith('adempimento/show/adempimento/', $cattura->redirectUrl);

        // gli adempimenti del calendario fiscale sono in sola lettura
        $this->expectException(AccessDeniedException::class);
        $controller->update($this->creaRequest([], [], [], 'GET'), $adempimento);
    }

    public function testAssegnazioneAiClientiETerminiDelloStudio(): void
    {
        $cattura = $this->catturaRenderERouter();
        [$soggetto, $adempimento] = $this->creaScenario();
        $this->tabelle['assegnazione_adempimento'] = [];
        $controller = new AdempimentoController($this->dataMapper);

        $controller->assegna($this->creaRequest([], [], [], 'GET'), $adempimento);
        $this->assertSame('adempimento/assegna', $cattura->vista);
        $this->assertTrue($cattura->vars['candidati'][0]['proposto']);

        $controller->assegna($this->creaRequest(['validoDal' => '2026-01-01', 'responsabile' => '1']), $adempimento);
        $this->assertSame('Seleziona almeno un cliente.', $cattura->vars['errore']);
        $controller->assegna($this->creaRequest(['validoDal' => '', 'soggetti' => ['50' => '1']]), $adempimento);
        $this->assertStringContainsString('data', $cattura->vars['errore']);

        $controller->assegna($this->creaRequest(['validoDal' => '2026-01-01', 'responsabile' => '1', 'soggetti' => ['50' => '1']]), $adempimento);
        $this->assertSame('adempimento/show/adempimento/10', $cattura->redirectUrl);
        $this->assertTrue($this->eseguitaQuery('INSERT INTO `assegnazione_adempimento`'));

        // adempimento dello studio: termini, eliminazione bloccata se assegnato
        $proprio = $this->creaAdempimento(1000, 'INTERNO', true);
        $termineController = new TermineAdempimentoController($this->dataMapper);
        $termineController->create($this->creaRequest(['ordine' => '1', 'periodo' => 'anno', 'mese' => '4', 'giorno' => '31', 'anniSuccessivi' => '0']), $proprio);
        $this->assertSame('termineAdempimento/form', $cattura->vista);
        $this->assertNotSame('', $cattura->vars['errors']->giornoCustomMessage);
        $termineController->create($this->creaRequest(['ordine' => '1', 'periodo' => 'anno', 'mese' => '4', 'giorno' => '30', 'anniSuccessivi' => '0']), $proprio);
        $this->assertSame('adempimento/show/adempimento/1000', $cattura->redirectUrl);

        $this->conteggio = 1;
        $controller->delete($this->creaRequest(), $proprio);
        $this->assertSame('adempimento/show/adempimento/1000', $cattura->redirectUrl);
        $this->conteggio = 0;
        $controller->delete($this->creaRequest(), $proprio);
        $this->assertSame('adempimento/index', $cattura->redirectUrl);
    }

    public function testProrogheDelloStudio(): void
    {
        $cattura = $this->catturaRenderERouter();
        [, , $termine] = $this->creaScenario();
        $controller = new ProrogaAdempimentoController($this->dataMapper);

        $controller->create($this->creaRequest([], [], [], 'GET'), $termine, 2025);
        $this->assertSame('prorogaAdempimento/form', $cattura->vista);
        $this->assertSame('2026-10-31', $cattura->vars['ordinario']->format('Y-m-d'));

        // non può anticipare il termine ordinario
        $controller->create($this->creaRequest(['data' => '2026-10-15', 'provvedimento' => 'D.L. di prova']), $termine, 2025);
        $this->assertNotSame('', $cattura->vars['errors']->dataCustomMessage);
        $controller->create($this->creaRequest(['data' => '2026-11-30', 'provvedimento' => 'D.L. di prova']), $termine, 2025);
        $this->assertSame('adempimento/show/adempimento/10/anno/2025', $cattura->redirectUrl);

        // quella del calendario fiscale non si modifica
        $this->expectException(AccessDeniedException::class);
        $controller->update($this->creaRequest([], [], [], 'GET'), $this->creaProroga($termine, 2025, '2026-11-30'));
    }

    public function testAdempimentiDelCliente(): void
    {
        $cattura = $this->catturaRenderERouter();
        [$soggetto, $adempimento, , $assegnazione] = $this->creaScenario();
        $controller = new AssegnazioneAdempimentoController($this->dataMapper);

        $controller->create($this->creaRequest([], [], [], 'GET'), $soggetto);
        $this->assertSame('assegnazioneAdempimento/form', $cattura->vista);
        $this->assertTrue($cattura->vars['nuova']);
        $this->assertArrayHasKey('Proposti per il soggetto', $cattura->vars['adempimenti']);

        // stessa decorrenza della versione esistente: errore dello storico
        $controller->create($this->creaRequest(['adempimento' => '10', 'validoDal' => '2025-01-01']), $soggetto);
        $this->assertSame('assegnazioneAdempimento/form', $cattura->vista);
        $this->assertStringContainsString('decorrenza', $cattura->vars['errors']->validoDalCustomMessage);

        $controller->variazione($this->creaRequest(['validoDal' => '2026-07-01', 'responsabile' => '1']), $assegnazione);
        $this->assertSame('scadenzario/soggetto/soggetto/50', $cattura->redirectUrl);

        $controller->update($this->creaRequest([], [], [], 'GET'), $assegnazione);
        $this->assertFalse($cattura->vars['nuova']);
        $this->assertCount(1, $cattura->vars['storico']);

        $controller->delete($this->creaRequest(), $assegnazione);
        $this->assertSame('scadenzario/soggetto/soggetto/50', $cattura->redirectUrl);
    }
}
