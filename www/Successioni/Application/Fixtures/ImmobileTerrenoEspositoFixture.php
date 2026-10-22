<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Terreno agricolo demo a Caserta, di proprietà di Giuseppe Esposito.
 */
class ImmobileTerrenoEspositoFixture extends BaseFixture
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
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('B963');
        $immobile->foglio = '14';
        $immobile->particella = '87';
        $immobile->descrizione = 'Seminativo';
        $this->addEntity($immobile);
    }
}
