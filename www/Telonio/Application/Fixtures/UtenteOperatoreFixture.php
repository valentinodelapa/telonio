<?php

namespace Telonio\Application\Fixtures;

use Telonio\Application\Entities\Utente;
use Telonio\Application\Enumerations\RuoloUtente;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Operatore demo dello studio (password in PasswordOperatoreFixture).
 */
class UtenteOperatoreFixture extends BaseFixture
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
        $utente->username = 'operatore';
        $utente->cognome = 'Collaboratore';
        $utente->nome = 'Luca';
        $utente->email = 'operatore@telonio.localhost';
        $utente->ruolo = RuoloUtente::operatore;
        $utente->attivo = true;
        $this->addEntity($utente);
    }
}
