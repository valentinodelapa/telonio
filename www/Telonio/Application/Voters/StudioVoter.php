<?php

namespace Telonio\Application\Voters;

use Telonio\Application\Entities\Studio;
use Telonio\Application\Entities\Utente;
use SismaFramework\Security\BaseClasses\BaseVoter;

/**
 * Solo un amministratore dello studio può modificare i dati del proprio studio.
 */
class StudioVoter extends BaseVoter
{

    #[\Override]
    protected function isInstancePermitted(): bool
    {
        return ($this->subject instanceof Studio) && ($this->authenticable instanceof Utente);
    }

    #[\Override]
    protected function checkVote(): bool
    {
        return $this->authenticable->isAmministratore()
                && ($this->subject->id === $this->authenticable->studio->id);
    }
}
