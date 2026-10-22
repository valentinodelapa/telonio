<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\ContoCorrente;
use SismaFramework\Core\BaseClasses\BaseFixture;

class ContoCorrenteRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $conto = new ContoCorrente($this->dataMapper);
        $conto->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $conto->iban = 'IT98Q0306903200100000012345';
        $conto->descrizione = 'Banca demo - filiale di Roma';
        $conto->predefinito = true;
        $this->addEntity($conto);
    }
}
