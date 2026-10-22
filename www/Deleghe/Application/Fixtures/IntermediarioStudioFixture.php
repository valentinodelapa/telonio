<?php

namespace Deleghe\Application\Fixtures;

use CommSuite\Application\Fixtures\StudioFixture;
use Deleghe\Application\Entities\Intermediario;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Intermediario Entratel demo: lo studio, con il conto per gli invii con
 * addebito unico.
 */
class IntermediarioStudioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(SoggettoStudioFixture::class)
                ->addDependency(ContoCorrenteStudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $intermediario->soggetto = $this->getEntityByFixtureName(SoggettoStudioFixture::class);
        $intermediario->telefono = '0612345678';
        $intermediario->email = 'deleghe@studiodemo.example';
        $intermediario->contoAddebito = $this->getEntityByFixtureName(ContoCorrenteStudioFixture::class);
        $intermediario->predefinito = true;
        $this->addEntity($intermediario);
    }
}
