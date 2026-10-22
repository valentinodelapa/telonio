<?php

namespace Anagrafica\Application\Voters;

use Anagrafica\Application\Entities\Soggetto;
use CommSuite\Application\Entities\Utente;
use SismaFramework\Security\BaseClasses\BaseVoter;

/**
 * Un utente può operare solo sui soggetti del proprio studio.
 */
class SoggettoVoter extends BaseVoter
{

    #[\Override]
    protected function isInstancePermitted(): bool
    {
        return ($this->subject instanceof Soggetto) && ($this->authenticable instanceof Utente);
    }

    #[\Override]
    protected function checkVote(): bool
    {
        return $this->subject->studio->id === $this->authenticable->studio->id;
    }
}
