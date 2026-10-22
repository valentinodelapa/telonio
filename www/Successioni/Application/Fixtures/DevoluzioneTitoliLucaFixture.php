<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;

/**
 * Legato demo: le azioni al nipote Luca.
 */
class DevoluzioneTitoliLucaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(BeneTitoliEspositoFixture::class);
        $this->addDependency(BeneficiarioEspositoLucaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $devoluzione = new DevoluzioneSuccessione($this->dataMapper);
        $devoluzione->beneSuccessione = $this->getEntityByFixtureName(BeneTitoliEspositoFixture::class);
        $devoluzione->beneficiarioSuccessione = $this->getEntityByFixtureName(BeneficiarioEspositoLucaFixture::class);
        $devoluzione->quotaNumeratore = 1;
        $devoluzione->quotaDenominatore = 1;
        $devoluzione->valoreQuota = 50000.00;
        $devoluzione->codiceDiritto = CodiceDiritto::proprieta;
        $this->addEntity($devoluzione);
    }
}
