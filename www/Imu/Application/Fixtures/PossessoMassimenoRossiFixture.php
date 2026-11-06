<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\SoggettoRossiFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: casa di Rossi a Massimeno (TN): non si applica l'IMU ma l'IMIS (comma 739).
 */
class PossessoMassimenoRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileMassimenoRossiFixture::class)
                ->addDependency(SoggettoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileMassimenoRossiFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2019-06-01');
        $possesso->utilizzo = UtilizzoImmobile::aDisposizione;
        $this->addEntity($possesso);
    }
}
