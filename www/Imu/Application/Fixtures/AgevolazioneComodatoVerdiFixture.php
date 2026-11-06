<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoVerdiFixture;
use Imu\Application\Entities\AgevolazioneImu;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Agevolazione demo: comodato al figlio con i requisiti del comma 747, lett. c).
 */
class AgevolazioneComodatoVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileComodatoVerdiFixture::class)
                ->addDependency(SoggettoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $agevolazione = new AgevolazioneImu($this->dataMapper);
        $agevolazione->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $agevolazione->immobile = $this->getEntityByFixtureName(ImmobileComodatoVerdiFixture::class);
        $agevolazione->validoDal = new SismaDate('2018-04-01');
        $agevolazione->comodatoParenti = true;
        $this->addEntity($agevolazione);
    }
}
