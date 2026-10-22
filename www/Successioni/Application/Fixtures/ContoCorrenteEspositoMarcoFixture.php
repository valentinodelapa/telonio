<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\ContoCorrente;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Conto di Marco Esposito per l'addebito delle imposte della successione.
 */
class ContoCorrenteEspositoMarcoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoEspositoMarcoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $conto = new ContoCorrente($this->dataMapper);
        $conto->soggetto = $this->getEntityByFixtureName(SoggettoEspositoMarcoFixture::class);
        $conto->iban = 'IT49S0306903496100000012345';
        $conto->descrizione = 'Conto personale';
        $conto->predefinito = true;
        $this->addEntity($conto);
    }
}
