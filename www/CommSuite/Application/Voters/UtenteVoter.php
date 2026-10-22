<?php

namespace CommSuite\Application\Voters;

use CommSuite\Application\Entities\Utente;
use SismaFramework\Security\BaseClasses\BaseVoter;

/**
 * Solo un amministratore dello studio può gestire gli utenti del proprio studio.
 */
class UtenteVoter extends BaseVoter
{

    #[\Override]
    protected function isInstancePermitted(): bool
    {
        return ($this->subject instanceof Utente) && ($this->authenticable instanceof Utente);
    }

    #[\Override]
    protected function checkVote(): bool
    {
        return $this->authenticable->isAmministratore()
                && ($this->subject->studio->id === $this->authenticable->studio->id);
    }
}
