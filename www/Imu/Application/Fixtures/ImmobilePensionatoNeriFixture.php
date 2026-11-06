<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: unica abitazione di Neri a Bergamo, pensionata residente all'estero (imposta al 50% e aliquota differenziata).
 */
class ImmobilePensionatoNeriFixture extends BaseFixture
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
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('A794');
        $immobile->foglio = '41';
        $immobile->particella = '305';
        $immobile->subalterno = '7';
        $immobile->indirizzo = 'Via Moroni';
        $immobile->numeroCivico = '60';
        $immobile->descrizione = 'Abitazione del pensionato residente all\'estero';
        $this->addEntity($immobile);
    }
}
