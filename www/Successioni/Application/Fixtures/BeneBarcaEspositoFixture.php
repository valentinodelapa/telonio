<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\TipoUnita;

/**
 * Barca a vela demo del defunto (quadro EQ).
 */
class BeneBarcaEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $bene = new BeneSuccessione($this->dataMapper);
        $bene->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $bene->quadro = QuadroBene::navi;
        $bene->ordine = 1;
        $bene->tipoUnita = TipoUnita::vela;
        $bene->descrizione = 'Barca a vela Bavaria 34';
        $bene->nazionalita = 'Italia';
        $bene->ufficioIscrizione = 'NA';
        $bene->annoIscrizione = 2010;
        $bene->numeroIscrizione = '1234';
        $bene->annoCostruzione = 2009;
        $bene->dimensione = 1040;
        $bene->valore = 30000;
        $this->addEntity($bene);
    }
}
