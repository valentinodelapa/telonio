<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo: terreno di Verdi.
 */
class ImmobileTerrenoVerdiFixture extends BaseFixture
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
        $immobile->foglio = '5';
        $immobile->particella = '77';
        $immobile->descrizione = 'Terreno agricolo';
        $this->addEntity($immobile);
    }
}
