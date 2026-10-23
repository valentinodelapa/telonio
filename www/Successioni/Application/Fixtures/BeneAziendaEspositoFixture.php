<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\QuadroBene;

/**
 * Ditta individuale demo del defunto (quadro EN), passata ai figli con l'agevolazione per il passaggio generazionale.
 */
class BeneAziendaEspositoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
        $this->addDependency(SoggettoEspositoGiuseppeFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $bene = new BeneSuccessione($this->dataMapper);
        $bene->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $bene->quadro = QuadroBene::aziende;
        $bene->ordine = 1;
        $bene->descrizione = 'Ferramenta Esposito Giuseppe - commercio al dettaglio di ferramenta';
        $bene->codiceFiscaleEmittente = $this->getEntityByFixtureName(SoggettoEspositoGiuseppeFixture::class)->codiceFiscale;
        $bene->dataInventario = new SismaDate('2025-09-30');
        $bene->dataDeposito = new SismaDate('2025-10-15');
        $bene->cameraCommercio = 'Trento';
        $bene->valore = 120000;
        $this->addEntity($bene);
    }
}
