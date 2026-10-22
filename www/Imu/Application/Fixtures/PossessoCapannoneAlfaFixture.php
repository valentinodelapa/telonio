<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: capannone strumentale di Alfa.
 */
class PossessoCapannoneAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCapannoneAlfaFixture::class)
                ->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileCapannoneAlfaFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2019-01-01');
        $possesso->utilizzo = UtilizzoImmobile::strumentale;
        $this->addEntity($possesso);
    }
}
