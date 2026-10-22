<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Enumerations\RuoloLegame;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Rossi socio unico di Alfa fino alla cessione di quote a Bianchi.
 */
class SocioRossiAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(SoggettoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $legame = new Legame($this->dataMapper);
        $legame->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $legame->soggettoCollegato = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $legame->ruolo = RuoloLegame::socio;
        $legame->validoDal = new SismaDate('2020-01-15');
        $legame->validoAl = new SismaDate('2021-12-31');
        $legame->quotaPartecipazione = 100.0;
        $this->addEntity($legame);
    }
}
