<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: sede di proprietà dell'associazione.
 */
class PossessoSedeAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileSedeAssociazioneFixture::class)
                ->addDependency(SoggettoAssociazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileSedeAssociazioneFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoAssociazioneFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2010-03-01');
        $possesso->utilizzo = UtilizzoImmobile::strumentale;
        $this->addEntity($possesso);
    }
}
