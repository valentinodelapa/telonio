<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\SoggettoNeriFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: unica abitazione di Neri a Bergamo, pensionata residente all'estero (imposta al 50% e aliquota differenziata).
 */
class PossessoPensionatoNeriFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobilePensionatoNeriFixture::class)
                ->addDependency(SoggettoNeriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobilePensionatoNeriFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoNeriFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2015-07-01');
        $possesso->utilizzo = UtilizzoImmobile::aDisposizione;
        $this->addEntity($possesso);
    }
}
