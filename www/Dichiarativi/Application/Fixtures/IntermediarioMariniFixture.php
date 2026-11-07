<?php

namespace Dichiarativi\Application\Fixtures;

use Deleghe\Application\Entities\Intermediario;
use SismaFramework\Core\BaseClasses\BaseFixture;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Il dott. Marini, abilitato a Entratel, trasmette le dichiarazioni con il
 * suo visto di conformità: chi appone il visto deve essere l'incaricato.
 */
class IntermediarioMariniFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class)
                ->addDependency(ProfessionistaMariniFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $intermediario->soggetto = $this->getEntityByFixtureName(ProfessionistaMariniFixture::class);
        $intermediario->email = 'marini@studiodemo.example';
        $intermediario->predefinito = false;
        $this->addEntity($intermediario);
    }
}
