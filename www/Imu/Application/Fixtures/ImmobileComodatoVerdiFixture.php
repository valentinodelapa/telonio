<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: alloggio di Verdi a Bergamo in comodato al figlio (base al 50% e aliquota differenziata).
 */
class ImmobileComodatoVerdiFixture extends BaseFixture
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
        $immobile->foglio = '40';
        $immobile->particella = '118';
        $immobile->subalterno = '3';
        $immobile->indirizzo = 'Via Broseta';
        $immobile->numeroCivico = '22';
        $immobile->descrizione = 'Appartamento in comodato al figlio';
        $this->addEntity($immobile);
    }
}
