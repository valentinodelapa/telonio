<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: capannone D/7 di Alfa a Brescia (quota dello Stato, codice 3925).
 */
class ImmobileCapannoneAlfaFixture extends BaseFixture
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
        $immobile->foglio = '58';
        $immobile->particella = '210';
        $immobile->subalterno = '1';
        $immobile->indirizzo = 'Via Industriale';
        $immobile->numeroCivico = '14';
        $immobile->descrizione = 'Capannone';
        $this->addEntity($immobile);
    }
}
