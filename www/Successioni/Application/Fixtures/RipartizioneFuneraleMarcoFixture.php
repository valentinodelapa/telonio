<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\RipartizionePassivita;

/**
 * Quota delle spese funerarie sostenuta da Marco.
 */
class RipartizioneFuneraleMarcoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(PassivitaFuneraleEspositoFixture::class);
        $this->addDependency(BeneficiarioEspositoMarcoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $ripartizione = new RipartizionePassivita($this->dataMapper);
        $ripartizione->passivitaSuccessione = $this->getEntityByFixtureName(PassivitaFuneraleEspositoFixture::class);
        $ripartizione->beneficiarioSuccessione = $this->getEntityByFixtureName(BeneficiarioEspositoMarcoFixture::class);
        $ripartizione->quotaNumeratore = 1;
        $ripartizione->quotaDenominatore = 3;
        $ripartizione->valoreQuota = 344.33;
        $this->addEntity($ripartizione);
    }
}
