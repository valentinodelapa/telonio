<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;

/**
 * Devoluzione per legge demo: un terzo di casa a Marco.
 */
class DevoluzioneCasaMarcoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(BeneCasaEspositoFixture::class);
        $this->addDependency(BeneficiarioEspositoMarcoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $devoluzione = new DevoluzioneSuccessione($this->dataMapper);
        $devoluzione->beneSuccessione = $this->getEntityByFixtureName(BeneCasaEspositoFixture::class);
        $devoluzione->beneficiarioSuccessione = $this->getEntityByFixtureName(BeneficiarioEspositoMarcoFixture::class);
        $devoluzione->quotaNumeratore = 1;
        $devoluzione->quotaDenominatore = 3;
        $devoluzione->valoreQuota = 23100.00;
        $devoluzione->codiceDiritto = CodiceDiritto::proprieta;
        $devoluzione->agevolazione = null;
        $this->addEntity($devoluzione);
    }
}
