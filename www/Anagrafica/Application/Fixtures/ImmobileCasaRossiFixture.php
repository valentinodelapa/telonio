<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo: abitazione di Rossi.
 */
class ImmobileCasaRossiFixture extends BaseFixture
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
        $immobile->foglio = '812';
        $immobile->particella = '45';
        $immobile->subalterno = '12';
        $immobile->indirizzo = 'Via Appia Nuova';
        $immobile->numeroCivico = '100';
        $immobile->piano = '3';
        $immobile->interno = '7';
        $immobile->descrizione = 'Appartamento';
        $this->addEntity($immobile);
    }
}
