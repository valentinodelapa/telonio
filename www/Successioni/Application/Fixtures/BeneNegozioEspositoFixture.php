<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\QuadroBene;

/**
 * Negozio di Trento (quadro EM, valore catastale 2.000 x 1,05 x 40,8), bene aziendale della ferramenta.
 */
class BeneNegozioEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
        $this->addDependency(ImmobileNegozioEspositoFixture::class);
        $this->addDependency(BeneAziendaEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $bene = new BeneSuccessione($this->dataMapper);
        $bene->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $bene->quadro = QuadroBene::fabbricatiTavolare;
        $bene->ordine = 1;
        $bene->immobile = $this->getEntityByFixtureName(ImmobileNegozioEspositoFixture::class);
        $bene->azienda = $this->getEntityByFixtureName(BeneAziendaEspositoFixture::class);
        $bene->codiceDiritto = CodiceDiritto::proprieta;
        $bene->valore = 85680;
        $this->addEntity($bene);
    }
}
