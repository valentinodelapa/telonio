<?php

namespace CommSuite\Application\Fixtures;

use CommSuite\Application\Entities\Studio;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Studio demo per lo sviluppo.
 */
class StudioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {

    }

    #[\Override]
    public function setEntity(): void
    {
        $studio = new Studio($this->dataMapper);
        $studio->denominazione = 'Studio Demo Associati';
        $studio->codiceFiscale = '80012345676';
        $studio->partitaIva = '80012345676';
        $studio->attivo = true;
        $this->addEntity($studio);
    }
}
