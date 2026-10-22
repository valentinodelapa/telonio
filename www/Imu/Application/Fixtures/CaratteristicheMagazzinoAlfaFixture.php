<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\CaratteristicheImu;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Caratteristiche IMU demo: fabbricato del gruppo D senza rendita, a valore contabile.
 */
class CaratteristicheMagazzinoAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileMagazzinoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $caratteristiche = new CaratteristicheImu($this->dataMapper);
        $caratteristiche->immobile = $this->getEntityByFixtureName(ImmobileMagazzinoAlfaFixture::class);
        $caratteristiche->validoDal = new SismaDate('2023-05-10');
        $caratteristiche->valoreContabile = true;
        $caratteristiche->note = 'Accatastamento non ancora richiesto';
        $this->addEntity($caratteristiche);
    }
}
