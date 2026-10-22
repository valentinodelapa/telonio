<?php

namespace Deleghe\Application\Voters;

use CommSuite\Application\Entities\Utente;
use Deleghe\Application\Entities\Delega;
use SismaFramework\Security\BaseClasses\BaseVoter;

/**
 * Un utente può operare solo sulle deleghe del proprio studio.
 */
class DelegaVoter extends BaseVoter
{

    #[\Override]
    protected function isInstancePermitted(): bool
    {
        return ($this->subject instanceof Delega) && ($this->authenticable instanceof Utente);
    }

    #[\Override]
    protected function checkVote(): bool
    {
        return $this->subject->studio->id === $this->authenticable->studio->id;
    }
}
