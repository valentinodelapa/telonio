<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;

/**
 * Devoluzione per legge demo: un terzo di conto a Marco.
 */
class DevoluzioneContoMarcoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(BeneContoEspositoFixture::class);
        $this->addDependency(BeneficiarioEspositoMarcoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $devoluzione = new DevoluzioneSuccessione($this->dataMapper);
        $devoluzione->beneSuccessione = $this->getEntityByFixtureName(BeneContoEspositoFixture::class);
        $devoluzione->beneficiarioSuccessione = $this->getEntityByFixtureName(BeneficiarioEspositoMarcoFixture::class);
        $devoluzione->quotaNumeratore = 1;
        $devoluzione->quotaDenominatore = 3;
        $devoluzione->valoreQuota = 28333.33;
        $devoluzione->codiceDiritto = CodiceDiritto::proprieta;
        $devoluzione->agevolazione = null;
        $this->addEntity($devoluzione);
    }
}
