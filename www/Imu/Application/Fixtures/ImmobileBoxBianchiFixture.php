<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: box auto C/6 pertinenza della villa di Bianchi.
 */
class ImmobileBoxBianchiFixture extends BaseFixture
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
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('H501');
        $immobile->foglio = '410';
        $immobile->particella = '88';
        $immobile->subalterno = '5';
        $immobile->indirizzo = 'Via di Casal Palocco';
        $immobile->numeroCivico = '21';
        $immobile->descrizione = 'Box auto';
        $this->addEntity($immobile);
    }
}
