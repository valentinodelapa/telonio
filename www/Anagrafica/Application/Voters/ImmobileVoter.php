<?php

namespace Anagrafica\Application\Voters;

use Anagrafica\Application\Entities\Immobile;
use CommSuite\Application\Entities\Utente;
use SismaFramework\Security\BaseClasses\BaseVoter;

/**
 * Un utente può operare solo sugli immobili dell'archivio del proprio
 * studio.
 */
class ImmobileVoter extends BaseVoter
{

    #[\Override]
    protected function isInstancePermitted(): bool
    {
        return ($this->subject instanceof Immobile) && ($this->authenticable instanceof Utente);
    }

    #[\Override]
    protected function checkVote(): bool
    {
        return $this->subject->studio->id === $this->authenticable->studio->id;
    }
}
