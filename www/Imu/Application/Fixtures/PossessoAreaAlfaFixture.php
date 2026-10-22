<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: lotto acquistato da Alfa per l'ampliamento.
 */
class PossessoAreaAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileAreaAlfaFixture::class)
                ->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileAreaAlfaFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2020-01-01');
        $possesso->utilizzo = UtilizzoImmobile::altro;
        $this->addEntity($possesso);
    }
}
