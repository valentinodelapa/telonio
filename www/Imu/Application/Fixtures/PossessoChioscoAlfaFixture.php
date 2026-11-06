<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: chiosco di Alfa a Roma su area demaniale: soggetto passivo il concessionario (comma 743).
 */
class PossessoChioscoAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileChioscoAlfaFixture::class)
                ->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileChioscoAlfaFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $possesso->titolo = TitoloPossesso::concessioneDemaniale;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2016-05-01');
        $possesso->utilizzo = UtilizzoImmobile::strumentale;
        $this->addEntity($possesso);
    }
}
