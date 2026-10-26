<?php

namespace Telonio\Application\Fixtures;

use Telonio\Application\Entities\Utente;
use Telonio\Application\Enumerations\RuoloUtente;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Utente amministratore dello studio demo (password in PasswordFixture).
 */
class UtenteFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $utente = new Utente($this->dataMapper);
        $utente->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $utente->username = 'admin';
        $utente->cognome = 'Amministratore';
        $utente->nome = 'Utente';
        $utente->email = 'admin@telonio.localhost';
        $utente->ruolo = RuoloUtente::amministratore;
        $utente->attivo = true;
        $this->addEntity($utente);
    }
}
