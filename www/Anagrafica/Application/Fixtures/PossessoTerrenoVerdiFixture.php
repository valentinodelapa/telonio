<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: Verdi proprietario del terreno.
 */
class PossessoTerrenoVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileTerrenoVerdiFixture::class)
                ->addDependency(SoggettoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileTerrenoVerdiFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2018-06-01');
        $possesso->utilizzo = UtilizzoImmobile::altro;
        $this->addEntity($possesso);
    }
}
