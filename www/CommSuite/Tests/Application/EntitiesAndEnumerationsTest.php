<?php

namespace CommSuite\Tests\Application;

use CommSuite\Application\Entities\Password;
use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Entities\Utente;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\Enumerations\RuoloUtente;
use CommSuite\Application\Exceptions\AutenticazioneRichiestaException;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Orm\CustomTypes\SismaDateTime;

class EntitiesAndEnumerationsTest extends SuiteTestCase
{

    public function testStudioEntityDefaultValues(): void
    {
        $studio = new Studio($this->dataMapper);
        $studio->denominazione = 'Studio Bianchi';
        $studio->codiceFiscale = '01234567897';

        $this->assertTrue($studio->attivo);
        $this->assertNull($studio->partitaIva);
        $this->assertSame('Studio Bianchi', $studio->denominazione);
    }

    public function testUtenteEntityDefaultRuoloNomeCompletoEIsAmministratore(): void
    {
        $utente = new Utente($this->dataMapper);
        $utente->nome = 'Giuseppe';
        $utente->cognome = 'Verdi';

        $this->assertSame(RuoloUtente::operatore, $utente->ruolo);
        $this->assertTrue($utente->attivo);
        $this->assertNull($utente->ultimoAccesso);
        $this->assertSame('Giuseppe Verdi', $utente->getNomeCompleto());
        $this->assertFalse($utente->isAmministratore());

        $utente->ruolo = RuoloUtente::amministratore;
        $this->assertTrue($utente->isAmministratore());
    }

    public function testPasswordEntityDefaultDataCreazione(): void
    {
        $password = new Password($this->dataMapper);
        $this->assertInstanceOf(SismaDateTime::class, $password->dataCreazione);
    }

    public function testRuoloUtenteLabelsEDescrizioni(): void
    {
        $this->assertSame('Amministratore', RuoloUtente::amministratore->getLabel());
        $this->assertSame('Operatore', RuoloUtente::operatore->getLabel());
        $this->assertNotEmpty(RuoloUtente::amministratore->getDescrizione());
        $this->assertNotEmpty(RuoloUtente::operatore->getDescrizione());
    }

    public function testModuloSuiteMetodiEDisponibilita(): void
    {
        foreach (ModuloSuite::cases() as $modulo) {
            $this->assertNotEmpty($modulo->getLabel());
            $this->assertNotEmpty($modulo->getDescrizione());
            $this->assertStringStartsWith('bi-', $modulo->getIcona());
            $this->assertNotEmpty($modulo->getUrl());
        }

        // Moduli registrati in MODULE_FOLDERS
        $this->assertTrue(ModuloSuite::anagrafica->isDisponibile());
        $this->assertTrue(ModuloSuite::deleghe->isDisponibile());
        $this->assertTrue(ModuloSuite::imu->isDisponibile());
        $this->assertTrue(ModuloSuite::successioni->isDisponibile());
        $this->assertSame('/soggetto/index', ModuloSuite::anagrafica->getUrl());
        $this->assertSame('/contribuente/index', ModuloSuite::deleghe->getUrl());
        $this->assertSame('/contribuente-imu/index', ModuloSuite::imu->getUrl());
        $this->assertSame('/defunto/index', ModuloSuite::successioni->getUrl());

        // Moduli futuri non in MODULE_FOLDERS
        $this->assertFalse(ModuloSuite::contabilita->isDisponibile());
        $this->assertSame('#', ModuloSuite::contabilita->getUrl());
    }

    public function testAutenticazioneRichiestaExceptionReindirizzaALogin(): void
    {
        $stato = $this->catturaRenderERouter();
        $eccezione = new AutenticazioneRichiestaException();
        $response = $eccezione->redirect();

        $this->assertSame($stato->response, $response);
        $this->assertSame('auth/login', $stato->redirectUrl);
    }
}
