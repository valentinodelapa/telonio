<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\CaratteristicheImu;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Caratteristiche IMU demo: alloggio regolarmente assegnato (comma 749).
 */
class CaratteristicheAlloggioAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileAlloggioAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $caratteristiche = new CaratteristicheImu($this->dataMapper);
        $caratteristiche->immobile = $this->getEntityByFixtureName(ImmobileAlloggioAlfaFixture::class);
        $caratteristiche->validoDal = new SismaDate('2012-02-01');
        $caratteristiche->alloggioIacp = true;
        $this->addEntity($caratteristiche);
    }
}
