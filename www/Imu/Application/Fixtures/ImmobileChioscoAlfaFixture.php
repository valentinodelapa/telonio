<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: chiosco di Alfa a Roma su area demaniale: soggetto passivo il concessionario (comma 743).
 */
class ImmobileChioscoAlfaFixture extends BaseFixture
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
        $immobile->foglio = '1101';
        $immobile->particella = '77';
        $immobile->subalterno = '1';
        $immobile->indirizzo = 'Lungomare Paolo Toscanelli';
        $immobile->numeroCivico = '100';
        $immobile->descrizione = 'Chiosco su area demaniale';
        $this->addEntity($immobile);
    }
}
