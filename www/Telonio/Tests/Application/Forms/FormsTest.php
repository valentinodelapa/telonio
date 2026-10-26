<?php

namespace Telonio\Tests\Application\Forms;

use Telonio\Application\Entities\Password;
use Telonio\Application\Entities\Studio;
use Telonio\Application\Entities\Utente;
use Telonio\Application\Enumerations\RuoloUtente;
use Telonio\Application\Forms\PasswordForm;
use Telonio\Application\Forms\ProfiloForm;
use Telonio\Application\Forms\StudioForm;
use Telonio\Application\Forms\UtenteForm;
use Telonio\Tests\SuiteTestCase;
use SismaFramework\Core\HelperClasses\Encryptor;

class FormsTest extends SuiteTestCase
{

    public function testStudioFormValidoENormalizzazioni(): void
    {
        $studio = $this->creaStudio(1);
        $form = new StudioForm($studio, $this->dataMapper);
        $request = $this->creaRequest([
            'denominazione' => '  Studio   Associato   Verdi  ',
            'codiceFiscale' => ' rssmra 80a01 h501u ',
            'partitaIva' => ' it 01234567897 ',
        ]);

        // StudioModel::esisteCodiceFiscale -> nessun duplicato
        $this->testAdapter->accodaSelect([]);

        $form->handleRequest($request);
        $this->assertTrue($form->isValid());

        /** @var Studio $risultato */
        $risultato = $form->resolveEntity();
        $this->assertSame('Studio Associato Verdi', $risultato->denominazione);
        $this->assertSame('RSSMRA80A01H501U', $risultato->codiceFiscale);
        $this->assertSame('01234567897', $risultato->partitaIva);
    }

    public function testStudioFormErroriCustomFilter(): void
    {
        // CF non valido e P.IVA non valida
        $form = new StudioForm(null, $this->dataMapper);
        $request = $this->creaRequest([
            'denominazione' => 'Studio Test',
            'codiceFiscale' => 'CFNONVALIDO',
            'partitaIva' => '01234567890',
        ]);
        $form->handleRequest($request);
        $this->assertFalse($form->isValid());
        $errori = $form->getFilterErrors();
        $this->assertNotEmpty($errori->codiceFiscaleCustomMessage);
        $this->assertNotEmpty($errori->partitaIvaCustomMessage);

        // CF valido ma duplicato
        $altroStudio = $this->creaStudio(99, 'Altro Studio', '01234567897');
        $this->testAdapter->accodaSelect([$altroStudio]);
        $formDuplicato = new StudioForm($this->creaStudio(1), $this->dataMapper);
        $formDuplicato->handleRequest($this->creaRequest([
            'denominazione' => 'Studio Test',
            'codiceFiscale' => '01234567897',
            'partitaIva' => '',
        ]));
        $this->assertFalse($formDuplicato->isValid());
        $this->assertStringContainsString('Esiste già', (string) $formDuplicato->getFilterErrors()->codiceFiscaleCustomMessage);
    }

    public function testUtenteFormValidoENormalizzazioni(): void
    {
        $studio = $this->creaStudio(1);
        $admin = $this->creaUtente($studio, RuoloUtente::amministratore, 1, 'admin');
        $nuovo = new Utente($this->dataMapper);
        $nuovo->studio = $studio;

        $this->testAdapter->accodaSelect([]); // esisteUsername -> false

        $form = new UtenteForm($nuovo, $admin, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'username' => '  Mario.Rossi_1 ',
            'cognome' => '  De   Rossi ',
            'nome' => '  Mario   Luigi ',
            'email' => '  MARIO.ROSSI@Studio.IT ',
            'ruolo' => 'operatore',
            'attivo' => '1',
        ]));

        $this->assertTrue($form->isValid());
        /** @var Utente $risultato */
        $risultato = $form->resolveEntity();
        $this->assertSame('mario.rossi_1', $risultato->username);
        $this->assertSame('De Rossi', $risultato->cognome);
        $this->assertSame('Mario Luigi', $risultato->nome);
        $this->assertSame('mario.rossi@studio.it', $risultato->email);
        $this->assertSame(RuoloUtente::operatore, $risultato->ruolo);
        $this->assertTrue($risultato->attivo);
    }

    public function testUtenteFormErroriUsernameEmailAutoDisattivazioneEUltimoAmministratore(): void
    {
        $studio = $this->creaStudio(1);
        $admin = $this->creaUtente($studio, RuoloUtente::amministratore, 1, 'admin');

        // Username formato non valido + email non valida + auto-disattivazione + auto-revoca ruolo
        $formStesso = new UtenteForm($admin, $admin, $this->dataMapper);
        $formStesso->handleRequest($this->creaRequest([
            'username' => 'a!',
            'cognome' => 'Rossi',
            'nome' => 'Mario',
            'email' => 'email-non-valida',
            'ruolo' => 'operatore',
        ]));
        $this->assertFalse($formStesso->isValid());
        $errori = $formStesso->getFilterErrors();
        $this->assertNotEmpty($errori->usernameCustomMessage);
        $this->assertNotEmpty($errori->emailCustomMessage);
        $this->assertStringContainsString('disattivare il tuo stesso utente', (string) $errori->attivoCustomMessage);
        $this->assertStringContainsString('revocare il ruolo di amministratore al tuo stesso utente', (string) $errori->ruoloCustomMessage);

        // Username duplicato e tentativo di rimuovere l'ultimo amministratore attivo su altro utente
        $altroAdmin = $this->creaUtente($studio, RuoloUtente::amministratore, 2, 'admin2');
        $this->testAdapter->accodaSelect([$admin]); // esisteUsername -> trova admin (id 1 != 2)
        $this->testAdapter->accodaSelect([$altroAdmin]); // contaAmministratoriAttivi -> escludendo altroAdmin resta 0

        $formUltimo = new UtenteForm($altroAdmin, $admin, $this->dataMapper);
        $formUltimo->handleRequest($this->creaRequest([
            'username' => 'admin',
            'cognome' => 'Bianchi',
            'nome' => 'Luca',
            'email' => 'luca@studio.it',
            'ruolo' => 'operatore',
            'attivo' => '1',
        ]));
        $this->assertFalse($formUltimo->isValid());
        $erroriUltimo = $formUltimo->getFilterErrors();
        $this->assertStringContainsString('già utilizzato', (string) $erroriUltimo->usernameCustomMessage);
        $this->assertStringContainsString('almeno un amministratore attivo', (string) $erroriUltimo->ruoloCustomMessage);
    }

    public function testProfiloFormValidoEConEmailNonValida(): void
    {
        $utente = $this->creaUtente();
        $form = new ProfiloForm($utente, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'cognome' => '  Verdi  ',
            'nome' => '  Anna  ',
            'email' => '  ANNA.VERDI@STUDIO.IT ',
        ]));
        $this->assertTrue($form->isValid());
        /** @var Utente $aggiornato */
        $aggiornato = $form->resolveEntity();
        $this->assertSame('Verdi', $aggiornato->cognome);
        $this->assertSame('Anna', $aggiornato->nome);
        $this->assertSame('anna.verdi@studio.it', $aggiornato->email);

        $formErrato = new ProfiloForm($utente, $this->dataMapper);
        $formErrato->handleRequest($this->creaRequest([
            'cognome' => 'Verdi',
            'nome' => 'Anna',
            'email' => 'non-un-email',
        ]));
        $this->assertFalse($formErrato->isValid());
        $this->assertNotEmpty($formErrato->getFilterErrors()->emailCustomMessage);
    }

    public function testPasswordFormConESenzaPasswordAttualeEHashingInResolveEntity(): void
    {
        $utente = $this->creaUtente();
        $passwordEntita = new Password($this->dataMapper);
        $passwordEntita->utente = $utente;

        // Senza richiesta password attuale, valida
        $form = new PasswordForm($passwordEntita, false, $this->dataMapper);
        $form->handleRequest($this->creaRequest([
            'password' => 'NuovaPassword123',
            'confermaPassword' => 'NuovaPassword123',
        ]));
        $this->assertTrue($form->isValid());
        /** @var Password $risolta */
        $risolta = $form->resolveEntity();
        $this->assertNotSame('NuovaPassword123', $risolta->password);
        $this->assertTrue(Encryptor::verifyBlowfishHash('NuovaPassword123', $risolta->password));

        // Con password troppo corta e conferma non coincidente e password attuale errata
        $passwordAttualeSalvata = new Password($this->dataMapper);
        $passwordAttualeSalvata->utente = $utente;
        $passwordAttualeSalvata->password = Encryptor::getBlowfishHash('PasswordVecchia123');
        $this->testAdapter->accodaSelect([$passwordAttualeSalvata]);

        $formCambio = new PasswordForm(new Password($this->dataMapper), true, $this->dataMapper);
        $passwordConUtente = new Password($this->dataMapper);
        $passwordConUtente->utente = $utente;
        $formCambio = new PasswordForm($passwordConUtente, true, $this->dataMapper);
        $formCambio->handleRequest($this->creaRequest([
            'passwordAttuale' => 'Sbagliata',
            'password' => 'corta',
            'confermaPassword' => 'diversa',
        ]));
        $this->assertFalse($formCambio->isValid());
        $errori = $formCambio->getFilterErrors();
        $this->assertNotEmpty($errori->passwordAttualeCustomMessage);
        $this->assertNotEmpty($errori->passwordCustomMessage);
        $this->assertNotEmpty($errori->confermaPasswordCustomMessage);
    }
}
