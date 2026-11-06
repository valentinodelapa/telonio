<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: alloggio regolarmente assegnato di tipo IACP (detrazione del comma 749).
 */
class ImmobileAlloggioAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $immobile = new Immobile($this->dataMapper);
        $immobile->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('B157');
        $immobile->foglio = '59';
        $immobile->particella = '412';
        $immobile->subalterno = '6';
        $immobile->indirizzo = 'Via delle Case Popolari';
        $immobile->numeroCivico = '8';
        $immobile->descrizione = 'Alloggio assegnato (IACP)';
        $this->addEntity($immobile);
    }
}
