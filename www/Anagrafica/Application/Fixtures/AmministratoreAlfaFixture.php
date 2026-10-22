<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Enumerations\RuoloLegame;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Rossi amministratore unico di Alfa dalla costituzione.
 */
class AmministratoreAlfaFixture extends BaseFixture
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
        $legame->ruolo = RuoloLegame::amministratoreUnico;
        $legame->validoDal = new SismaDate('2020-01-15');
        $this->addEntity($legame);
    }
}
