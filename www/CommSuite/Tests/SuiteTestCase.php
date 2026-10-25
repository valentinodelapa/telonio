<?php

namespace CommSuite\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Application\BaseClasses\SuiteController;
use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Entities\Utente;
use CommSuite\Application\Enumerations\RuoloUtente;
use PHPUnit\Framework\TestCase;
use SismaFramework\Core\Enumerations\ResponseType;
use SismaFramework\Core\HelperClasses\Config;
use SismaFramework\Core\HelperClasses\Debugger;
use SismaFramework\Core\HttpClasses\Request;
use SismaFramework\Core\HttpClasses\Response;
use SismaFramework\Core\Services\RenderService;
use SismaFramework\Core\Services\RouterService;
use SismaFramework\Orm\BaseClasses\BaseAdapter;
use SismaFramework\Orm\HelperClasses\Cache;
use SismaFramework\Orm\HelperClasses\DataMapper;
use SismaFramework\Orm\HelperClasses\ProcessedEntitiesCollection;

/**
 * Classe base per gli unit test dei moduli della suite: isola Config,
 * BaseAdapter, Session, Cache, RouterService e RenderService senza database.
 */
abstract class SuiteTestCase extends TestCase
{

    protected Config $config;
    protected TestAdapter $testAdapter;
    protected TestAdapter $adapter;
    protected DataMapper $dataMapper;
    protected Studio $studioPredefinito;
    protected Studio $studioTest;
    protected Utente $utenteCorrente;

    #[\Override]
    protected function setUp(): void
    {
        $_SESSION = [];
        Debugger::resetInstance();
        RouterService::resetInstance();
        RenderService::resetInstance();
        Cache::clearEntityCache();
        ProcessedEntitiesCollection::getInstance()->clear();

        $this->config = new class extends Config {
            public function __construct()
            {
                parent::__construct();
                $this->ormCache = false;
            }
        };
        Config::setInstance($this->config);

        $this->testAdapter = new TestAdapter();
        $this->adapter = $this->testAdapter;
        BaseAdapter::setDefault($this->testAdapter);

        $this->dataMapper = new DataMapper($this->testAdapter, ProcessedEntitiesCollection::getInstance(), $this->config);
        $this->dataMapper->setOrmCacheStatus(false);

        $this->studioPredefinito = $this->creaStudio();
        $this->studioTest = $this->studioPredefinito;
        $this->utenteCorrente = $this->creaUtente($this->studioPredefinito);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $_SESSION = [];
        Debugger::resetInstance();
        RouterService::resetInstance();
        RenderService::resetInstance();
        Cache::clearEntityCache();
        ProcessedEntitiesCollection::getInstance()->clear();
    }

    protected function creaStudio(int $id = 1, string $denominazione = 'Studio Associato Test', string $codiceFiscale = '01234567897', bool $attivo = true): Studio
    {
        $studio = new Studio($this->dataMapper);
        $studio->id = $id;
        $studio->denominazione = $denominazione;
        $studio->codiceFiscale = $codiceFiscale;
        $studio->partitaIva = $codiceFiscale;
        $studio->attivo = $attivo;
        return $studio;
    }

    protected function creaUtente(
        ?Studio $studio = null,
        RuoloUtente $ruolo = RuoloUtente::amministratore,
        int $id = 1,
        string $username = 'admin',
        bool $attivo = true,
    ): Utente {
        $utente = new Utente($this->dataMapper);
        $utente->id = $id;
        $utente->studio = $studio ?? $this->creaStudio();
        $utente->username = $username;
        $utente->cognome = 'Rossi';
        $utente->nome = 'Mario';
        $utente->email = 'mario.rossi@studio.it';
        $utente->ruolo = $ruolo;
        $utente->attivo = $attivo;
        return $utente;
    }

    protected function autenticaUtente(?Utente $utente = null): Utente
    {
        $utente ??= $this->creaUtente();
        $_SESSION[SuiteController::SESSIONE_UTENTE] = $utente->id;
        $_SESSION[\Config\CSRF_TOKEN_KEY] = 'csrf-token-test-valido';
        return $utente;
    }

    protected function creaRequest(array $input = [], array $query = [], array $files = [], string $metodo = 'POST', bool $conCsrf = true): Request
    {
        $request = $this->createStub(Request::class);
        if ($metodo === 'POST' && !empty($input)) {
            $input['submitted'] ??= '1';
        }
        if ($conCsrf && isset($_SESSION[\Config\CSRF_TOKEN_KEY])) {
            $input[\Config\CSRF_TOKEN_KEY] ??= $_SESSION[\Config\CSRF_TOKEN_KEY];
        }
        $request->input = $input;
        $request->query = $query;
        $request->files = $files;
        $request->cookie = [];
        $request->headers = [];
        $request->server = [
            'REQUEST_METHOD' => $metodo,
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit',
        ];
        return $request;
    }

    /**
     * Configura stub di RenderService e RouterService che catturano vista, variabili, JSON e redirect.
     */
    protected function catturaRenderERouter(): object
    {
        $stato = new class {
            public ?string $vista = null;
            public ?string $ultimaVista = null;
            public array $vars = [];
            public array $ultimeVars = [];
            public ?string $redirectUrl = null;
            public ?string $ultimoRedirect = null;
            public ?array $json = null;
            public ?array $ultimoJson = null;
            public Response $response;
        };
        $stato->response = new Response(ResponseType::httpOk);

        $renderStub = $this->createStub(RenderService::class);
        $renderStub->method('generateView')
                ->willReturnCallback(function (string $view, array $vars = []) use ($stato): Response {
                    $stato->vista = $view;
                    $stato->ultimaVista = $view;
                    $stato->vars = $vars;
                    $stato->ultimeVars = $vars;
                    return $stato->response;
                });
        $renderStub->method('generateJson')
                ->willReturnCallback(function (array $data) use ($stato): Response {
                    $stato->json = $data;
                    $stato->ultimoJson = $data;
                    return $stato->response;
                });
        $renderStub->method('generateData')
                ->willReturnCallback(function (string $view, array $vars = []) use ($stato): Response {
                    $stato->vista = $view;
                    $stato->ultimaVista = $view;
                    $stato->vars = $vars;
                    $stato->ultimeVars = $vars;
                    return $stato->response;
                });
        RenderService::setInstance($renderStub);

        $routerStub = $this->createStub(RouterService::class);
        $routerStub->method('redirect')
                ->willReturnCallback(function (string $url) use ($stato): Response {
                    $stato->redirectUrl = $url;
                    $stato->ultimoRedirect = $url;
                    return $stato->response;
                });
        $routerStub->method('getControllerUrl')->willReturn('test');
        $routerStub->method('getActionUrl')->willReturn('index');
        $routerStub->method('getMetaUrl')->willReturn('');
        $routerStub->method('getActualCleanUrl')->willReturn('/test/index/');
        $routerStub->method('getRootUrl')->willReturn('http://localhost');
        RouterService::setInstance($routerStub);

        return $stato;
    }

    protected function creaSoggettoFisico(int $id, Studio $studio, string $codiceFiscale, string $cognome, string $nome): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = $id;
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = $codiceFiscale;
        $soggetto->cognome = $cognome;
        $soggetto->nome = $nome;
        $soggetto->nominativo = trim($cognome . ' ' . $nome);
        return $soggetto;
    }
}
