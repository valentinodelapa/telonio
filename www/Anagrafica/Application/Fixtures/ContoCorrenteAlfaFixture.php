<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\ContoCorrente;
use SismaFramework\Core\BaseClasses\BaseFixture;

class ContoCorrenteAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $conto = new ContoCorrente($this->dataMapper);
        $conto->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $conto->iban = 'IT20D0200805364000101234567';
        $conto->descrizione = 'Banca demo - conto aziendale';
        $conto->predefinito = true;
        $this->addEntity($conto);
    }
}
