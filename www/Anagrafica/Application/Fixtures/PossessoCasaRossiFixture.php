<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: Rossi proprietario dell'abitazione principale.
 */
class PossessoCasaRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCasaRossiFixture::class)
                ->addDependency(SoggettoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileCasaRossiFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2010-01-01');
        $possesso->utilizzo = UtilizzoImmobile::abitazionePrincipale;
        $this->addEntity($possesso);
    }
}
