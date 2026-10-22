<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo IMU: particella di Alfa a Brescia divenuta area fabbricabile.
 */
class ImmobileAreaAlfaFixture extends BaseFixture
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
        $immobile->tipoCatasto = TipoCatasto::terreni;
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('B157');
        $immobile->foglio = '58';
        $immobile->particella = '540';
        $immobile->indirizzo = 'Via Industriale';
        $immobile->descrizione = 'Lotto edificabile';
        $this->addEntity($immobile);
    }
}
