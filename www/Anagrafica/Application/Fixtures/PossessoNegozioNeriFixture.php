<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: Neri comproprietaria al 50%, quota concessa in comodato all'impresa.
 */
class PossessoNegozioNeriFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileNegozioVerdiFixture::class)
                ->addDependency(SoggettoNeriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileNegozioVerdiFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoNeriFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 50.0;
        $possesso->validoDal = new SismaDate('2015-03-01');
        $possesso->utilizzo = UtilizzoImmobile::comodato;
        $this->addEntity($possesso);
    }
}
