<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\QuadroBene;

/**
 * Metà dell'appartamento di Napoli (valore catastale: 1.100 x 1,05 x 120 / 2).
 */
class BeneCasaEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
        $this->addDependency(ImmobileCasaEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $bene = new BeneSuccessione($this->dataMapper);
        $bene->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $bene->quadro = QuadroBene::fabbricati;
        $bene->ordine = 1;
        $bene->immobile = $this->getEntityByFixtureName(ImmobileCasaEspositoFixture::class);
        $bene->codiceDiritto = CodiceDiritto::proprieta;
        $bene->quotaNumeratore = 1.0;
        $bene->quotaDenominatore = 2;
        $bene->valore = 69300;
        $this->addEntity($bene);
    }
}
