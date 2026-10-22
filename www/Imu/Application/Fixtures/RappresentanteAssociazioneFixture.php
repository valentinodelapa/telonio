<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Fixtures\SoggettoBianchiFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Bianchi legale rappresentante dell'associazione (firma la dichiarazione IMU ENC).
 */
class RappresentanteAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAssociazioneFixture::class)
                ->addDependency(SoggettoBianchiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $legame = new Legame($this->dataMapper);
        $legame->soggetto = $this->getEntityByFixtureName(SoggettoAssociazioneFixture::class);
        $legame->soggettoCollegato = $this->getEntityByFixtureName(SoggettoBianchiFixture::class);
        $legame->ruolo = RuoloLegame::legaleRappresentante;
        $legame->validoDal = new SismaDate('2018-01-01');
        $this->addEntity($legame);
    }
}
