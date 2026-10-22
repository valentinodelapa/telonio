<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\ContoCorrente;
use SismaFramework\Core\BaseClasses\BaseFixture;

class ContoCorrenteVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $conto = new ContoCorrente($this->dataMapper);
        $conto->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $conto->iban = 'IT77D0306909606100000063482';
        $conto->descrizione = 'Banca demo - filiale di Milano';
        $conto->predefinito = true;
        $this->addEntity($conto);
    }
}
