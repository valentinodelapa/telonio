<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\TipoCespite;

/**
 * Saldo del conto corrente del defunto alla data del decesso.
 */
class BeneContoEspositoFixture extends BaseFixture
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
        $bene->quadro = QuadroBene::altriBeni;
        $bene->ordine = 1;
        $bene->tipoCespite = TipoCespite::crediti;
        $bene->descrizione = 'Saldo del conto corrente n. 4471 presso Banca di Napoli al 12/09/2025';
        $bene->valore = 85000;
        $this->addEntity($bene);
    }
}
