<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: casa di Rossi a Massimeno (TN): non si applica l'IMU ma l'IMIS (comma 739).
 */
class ImmobileMassimenoRossiFixture extends BaseFixture
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
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('F045');
        $immobile->foglio = '3';
        $immobile->particella = '91';
        $immobile->subalterno = '2';
        $immobile->indirizzo = 'Via Roma';
        $immobile->numeroCivico = '5';
        $immobile->descrizione = 'Casa di vacanza';
        $this->addEntity($immobile);
    }
}
