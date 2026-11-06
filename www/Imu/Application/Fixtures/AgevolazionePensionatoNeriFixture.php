<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoNeriFixture;
use Imu\Application\Entities\AgevolazioneImu;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Agevolazione demo: pensionata residente all'estero (L. 178/2020, c. 48).
 */
class AgevolazionePensionatoNeriFixture extends BaseFixture
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
        $agevolazione = new AgevolazioneImu($this->dataMapper);
        $agevolazione->soggetto = $this->getEntityByFixtureName(SoggettoNeriFixture::class);
        $agevolazione->immobile = $this->getEntityByFixtureName(ImmobilePensionatoNeriFixture::class);
        $agevolazione->validoDal = new SismaDate('2021-01-01');
        $agevolazione->pensionatoEstero = true;
        $this->addEntity($agevolazione);
    }
}
