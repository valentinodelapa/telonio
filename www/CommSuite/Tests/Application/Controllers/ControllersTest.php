<?php

namespace CommSuite\Tests\Application\Controllers;

use CommSuite\Application\BaseClasses\SuiteController;
use CommSuite\Application\Controllers\AuthController;
use CommSuite\Application\Controllers\HomeController;
use CommSuite\Application\Controllers\ProfiloController;
use CommSuite\Application\Controllers\StudioController;
use CommSuite\Application\Controllers\UtenteController;
use CommSuite\Application\Entities\Password;
use CommSuite\Application\Enumerations\RuoloUtente;
use CommSuite\Application\Exceptions\AutenticazioneRichiestaException;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Core\HelperClasses\Encryptor;
use SismaFramework\Security\HttpClasses\Authentication;

class ControllersTest extends SuiteTestCase
{

    public function testSuiteControllerLanciaEccezioneSeNonAutenticato(): void
    {
        $this->catturaRenderERouter();
        $this->expectException(AutenticazioneRichiestaException::class);
        new HomeController($this->dataMapper);
    }

    public function testSuiteControllerLanciaEccezioneSeUtenteOStudioInattivo(): void
    {
        $this->catturaRenderERouter();
        $studioInattivo = $this->creaStudio(1, 'Studio', '01234567897', false);
        $utente = $this->creaUtente($studioInattivo);
        $this->autenticaUtente($utente);
        $this->testAdapter->accodaSelect([$utente]);

        try {
            new HomeController($this->dataMapper);
            $this->fail('Attesa AutenticazioneRichiestaException');
        } catch (AutenticazioneRichiestaException) {
            $this->assertArrayNotHasKey(SuiteController::SESSIONE_UTENTE, $_SESSION);
        }
    }

    public function testAuthControllerLoginELogout(): void
    {
        $stato = $this->catturaRenderERouter();
        $controller = new AuthController($this->dataMapper);

        // Già autenticato -> redirect a home/index
        $this->autenticaUtente();
        $controller->login($this->creaRequest([], [], [], 'GET'), $this->createStub(Authentication::class));
        $this->assertSame('home/index', $stato->redirectUrl);

        // Non autenticato, form inviato e valido -> salva ultimoAccesso e imposta sessione
        $_SESSION = [];
        $utente = $this->creaUtente();
        $authValida = $this->createStub(Authentication::class);
        $authValida->method('isSubmitted')->willReturn(true);
        $authValida->method('checkAuthenticable')->willReturn(true);
        $authValida->method('getAuthenticableInterface')->willReturn($utente);

        $controller->login($this->creaRequest(), $authValida);
        $this->assertSame('home/index', $stato->redirectUrl);
        $this->assertSame($utente->id, $_SESSION[SuiteController::SESSIONE_UTENTE]);
        $this->assertNotNull($utente->ultimoAccesso);

        // Non autenticato, accesso fallito -> render vista login con accessoFallito = true
        $_SESSION = [];
        $authFallita = $this->createStub(Authentication::class);
        $authFallita->method('isSubmitted')->willReturn(true);
        $authFallita->method('checkAuthenticable')->willReturn(false);

        $controller->login($this->creaRequest([\Config\AUTHENTICATION_IDENTIFIER_FIELD => 'mario']), $authFallita);
        $this->assertSame('auth/login', $stato->vista);
        $this->assertTrue($stato->vars['accessoFallito']);
        $this->assertSame('mario', $stato->vars['identificativo']);

        // Logout con GET o token CSRF non valido -> redirect a home/index
        $this->autenticaUtente($utente);
        $controller->logout($this->creaRequest([], [], [], 'GET'));
        $this->assertSame('home/index', $stato->redirectUrl);

        // Logout con POST e CSRF valido -> redirect a auth/login
        $this->autenticaUtente($utente);
        $controller->logout($this->creaRequest([], [], [], 'POST', true));
        $this->assertSame('auth/login', $stato->redirectUrl);
    }

    public function testHomeControllerIndex(): void
    {
        $stato = $this->catturaRenderERouter();
        $utente = $this->autenticaUtente();
        // Select per caricaUtente, conteggi dei RiepilogoScrivania dei moduli e scadenze (nessuna)
        $this->testAdapter->impostaSelectCallback(function (string $cmd) use ($utente) {
            if (str_contains($cmd, 'COUNT(')) {
                return \CommSuite\Tests\TestResultSet::conteggio(0);
            }
            if (str_contains($cmd, 'FROM `utente`')) {
                return new \CommSuite\Tests\TestResultSet([$utente]);
            }
            return new \CommSuite\Tests\TestResultSet([]);
        });

        $controller = new HomeController($this->dataMapper);
        $controller->index();

        $this->assertSame('home/index', $stato->vista);
        $this->assertSame($utente, $stato->vars['utente']);
        $this->assertArrayHasKey('riepilogoModuli', $stato->vars);
        $this->assertSame(['Scadute' => 0, 'Entro 7 giorni' => 0, 'Entro 30 giorni' => 0], $stato->vars['riepilogoModuli']['GestioneStudio']);
    }

    public function testProfiloControllerUpdatePasswordECsrfInvalido(): void
    {
        $stato = $this->catturaRenderERouter();
        $utente = $this->autenticaUtente();

        // Update profilo valido
        $this->testAdapter->accodaSelect([$utente]);
        $controller = new ProfiloController($this->dataMapper);
        $controller->update($this->creaRequest([
            'cognome' => 'Bianchi',
            'nome' => 'Paolo',
            'email' => 'paolo.bianchi@studio.it',
        ]));
        $this->assertSame('profilo/update', $stato->redirectUrl);
        $this->assertSame('Bianchi', $utente->cognome);

        // Verifica che il messaggio flash venga estratto alla richiesta successiva e controllo CSRF
        $this->testAdapter->accodaSelect([$utente]);
        $controllerSuccessivo = new ProfiloController($this->dataMapper);
        $controllerSuccessivo->update($this->creaRequest([], [], [], 'GET', false));
        $this->assertSame('profilo/update', $stato->vista);
        $this->assertCount(1, $stato->vars['messaggiFlash']);
        $this->assertSame('Profilo aggiornato.', $stato->vars['messaggiFlash'][0]['testo']);

        // Cambio password valido
        $passwordAttuale = new Password($this->dataMapper);
        $passwordAttuale->utente = $utente;
        $passwordAttuale->password = Encryptor::getBlowfishHash('VecchiaPassword123');
        $this->testAdapter->accodaSelect([$passwordAttuale]);

        $controllerSuccessivo->password($this->creaRequest([
            'passwordAttuale' => 'VecchiaPassword123',
            'password' => 'NuovaPassword456',
            'confermaPassword' => 'NuovaPassword456',
        ]));
        $this->assertSame('profilo/update', $stato->redirectUrl);

        // Invio con CSRF non valido -> AccessDeniedException
        $this->expectException(AccessDeniedException::class);
        $controllerSuccessivo->update($this->creaRequest([
            'cognome' => 'Bianchi',
            'nome' => 'Paolo',
            'email' => 'paolo.bianchi@studio.it',
        ], [], [], 'POST', false));
    }

    public function testStudioControllerUpdate(): void
    {
        $stato = $this->catturaRenderERouter();
        $utente = $this->autenticaUtente();
        $this->testAdapter->accodaSelect([$utente]); // caricaUtente
        $this->testAdapter->accodaSelect([]); // esisteCodiceFiscale

        $controller = new StudioController($this->dataMapper);
        $controller->update($this->creaRequest([
            'denominazione' => 'Studio Aggiornato',
            'codiceFiscale' => '01234567897',
            'partitaIva' => '01234567897',
        ]));

        $this->assertSame('studio/update', $stato->redirectUrl);
        $this->assertSame('Studio Aggiornato', $utente->studio->denominazione);
    }

    public function testUtenteControllerIndexCreateUpdateEPassword(): void
    {
        $stato = $this->catturaRenderERouter();
        $admin = $this->autenticaUtente();
        $operatore = $this->creaUtente($admin->studio, RuoloUtente::operatore, 2, 'operatore1');

        // Index
        $this->testAdapter->accodaSelect([$admin]); // caricaUtente
        $this->testAdapter->accodaSelect([$admin, $operatore]); // getElenco
        $controller = new UtenteController($this->dataMapper);
        $controller->index();
        $this->assertSame('utente/index', $stato->vista);
        $this->assertCount(2, $stato->vars['utenti']);

        // Create valido
        $this->testAdapter->accodaSelect([]); // esisteUsername
        $controller->create($this->creaRequest([
            'username' => 'nuovo.op',
            'cognome' => 'Neri',
            'nome' => 'Sara',
            'email' => 'sara.neri@studio.it',
            'ruolo' => 'operatore',
            'attivo' => '1',
            'password' => 'PasswordSicura123',
            'confermaPassword' => 'PasswordSicura123',
        ]));
        $this->assertSame('utente/index', $stato->redirectUrl);

        // Update valido
        $this->testAdapter->accodaSelect([$operatore]); // esisteUsername (stesso utente)
        $this->testAdapter->accodaSelect([$admin]); // contaAmministratoriAttivi
        $controller->update($this->creaRequest([
            'username' => 'operatore1',
            'cognome' => 'Modificato',
            'nome' => 'Marco',
            'email' => 'marco@studio.it',
            'ruolo' => 'operatore',
            'attivo' => '1',
        ]), $operatore);
        $this->assertSame('utente/index', $stato->redirectUrl);
        $this->assertSame('Modificato', $operatore->cognome);

        // Reimposta password valido
        $controller->password($this->creaRequest([
            'password' => 'NuovaPassOperatore99',
            'confermaPassword' => 'NuovaPassOperatore99',
        ]), $operatore);
        $this->assertSame('utente/index', $stato->redirectUrl);
    }
}
