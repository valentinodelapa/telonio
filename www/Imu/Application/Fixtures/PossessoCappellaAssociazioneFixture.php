<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Possesso demo: cappella dell'associazione destinata esclusivamente al culto (esente, comma 759, lett. d).
 */
class PossessoCappellaAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(ImmobileCappellaAssociazioneFixture::class)
                ->addDependency(SoggettoAssociazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->getEntityByFixtureName(ImmobileCappellaAssociazioneFixture::class);
        $possesso->soggetto = $this->getEntityByFixtureName(SoggettoAssociazioneFixture::class);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2010-03-01');
        $possesso->utilizzo = UtilizzoImmobile::altro;
        $this->addEntity($possesso);
    }
}
