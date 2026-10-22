<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Terreno di Caserta di Giuseppe Esposito.
 */
class PossessoTerrenoEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileTerrenoEspositoFixture::class);
        $this->addDependency(SoggettoEspositoGiuseppeFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileTerrenoEspositoFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoEspositoGiuseppeFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2001-04-20');
        $possesso->utilizzo = null;
        $this->addEntity($possesso);
    }
}
