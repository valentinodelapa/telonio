<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Negozio di Trento di Giuseppe Esposito, bene della sua ditta individuale.
 */
class PossessoNegozioEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileNegozioEspositoFixture::class);
        $this->addDependency(SoggettoEspositoGiuseppeFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileNegozioEspositoFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoEspositoGiuseppeFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2005-01-01');
        $possesso->utilizzo = null;
        $this->addEntity($possesso);
    }
}
