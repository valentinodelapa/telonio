<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\CaratteristicheImu;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Caratteristiche IMU demo: area fabbricabile dal 2025 con il valore venale al 1° gennaio.
 */
class CaratteristicheAreaAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileAreaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $caratteristiche = new CaratteristicheImu($this->dataMapper);
        $caratteristiche->immobile = $this->getEntityByFixtureName(ImmobileAreaAlfaFixture::class);
        $caratteristiche->validoDal = new SismaDate('2025-01-01');
        $caratteristiche->areaFabbricabile = true;
        $caratteristiche->valoreVenale = 192000.0;
        $caratteristiche->note = 'Valore da delibera comunale dei valori delle aree';
        $this->addEntity($caratteristiche);
    }
}
