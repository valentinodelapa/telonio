<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Entities\ContoCorrente;
use SismaFramework\Core\BaseClasses\BaseFixture;

class ContoCorrenteStudioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $conto = new ContoCorrente($this->dataMapper);
        $conto->soggetto = $this->getEntityByFixtureName(SoggettoStudioFixture::class);
        $conto->iban = 'IT47K0538712900000002345678';
        $conto->descrizione = 'Conto dello studio per gli addebiti unici F24';
        $conto->predefinito = true;
        $this->addEntity($conto);
    }
}
