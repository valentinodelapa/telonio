<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Enumerations\RuoloLegame;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

class SocioBianchiAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(SoggettoBianchiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $legame = new Legame($this->dataMapper);
        $legame->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $legame->soggettoCollegato = $this->getEntityByFixtureName(SoggettoBianchiFixture::class);
        $legame->ruolo = RuoloLegame::socio;
        $legame->validoDal = new SismaDate('2022-01-01');
        $legame->quotaPartecipazione = 39.5;
        $this->addEntity($legame);
    }
}
