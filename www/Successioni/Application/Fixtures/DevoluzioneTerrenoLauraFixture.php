<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;

/**
 * Devoluzione per legge demo: un terzo di terreno a Laura.
 */
class DevoluzioneTerrenoLauraFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(BeneTerrenoEspositoFixture::class);
        $this->addDependency(BeneficiarioEspositoLauraFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $devoluzione = new DevoluzioneSuccessione($this->dataMapper);
        $devoluzione->beneSuccessione = $this->getEntityByFixtureName(BeneTerrenoEspositoFixture::class);
        $devoluzione->beneficiarioSuccessione = $this->getEntityByFixtureName(BeneficiarioEspositoLauraFixture::class);
        $devoluzione->quotaNumeratore = 1;
        $devoluzione->quotaDenominatore = 3;
        $devoluzione->valoreQuota = 3000.00;
        $devoluzione->codiceDiritto = CodiceDiritto::proprieta;
        $devoluzione->agevolazione = null;
        $this->addEntity($devoluzione);
    }
}
