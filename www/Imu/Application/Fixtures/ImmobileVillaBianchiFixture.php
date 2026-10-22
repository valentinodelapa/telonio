<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: villa A/8 a Roma, abitazione principale di Bianchi dal 10 marzo 2026 (detrazione).
 */
class ImmobileVillaBianchiFixture extends BaseFixture
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
        $immobile->subalterno = '2';
        $immobile->indirizzo = 'Via di Casal Palocco';
        $immobile->numeroCivico = '21';
        $immobile->descrizione = 'Villa';
        $this->addEntity($immobile);
    }
}
