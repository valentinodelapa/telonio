<?php

namespace Imu\Application\Fixtures;

use Imu\Application\Entities\CaratteristicheImu;
use Imu\Application\Enumerations\EsenzioneImu;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Caratteristiche IMU demo: fabbricato destinato al culto (comma 759, lett. d).
 */
class CaratteristicheCappellaAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCappellaAssociazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $caratteristiche = new CaratteristicheImu($this->dataMapper);
        $caratteristiche->immobile = $this->getEntityByFixtureName(ImmobileCappellaAssociazioneFixture::class);
        $caratteristiche->validoDal = new SismaDate('2010-03-01');
        $caratteristiche->esenzione = EsenzioneImu::culto;
        $this->addEntity($caratteristiche);
    }
}
