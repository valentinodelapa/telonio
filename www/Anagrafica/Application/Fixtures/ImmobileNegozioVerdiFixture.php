<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Immobile demo: locale della ferramenta in comproprietà Verdi/Neri.
 */
class ImmobileNegozioVerdiFixture extends BaseFixture
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
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('F205');
        $immobile->foglio = '230';
        $immobile->particella = '112';
        $immobile->subalterno = '3';
        $immobile->indirizzo = 'Via Padova';
        $immobile->numeroCivico = '45';
        $immobile->piano = 'T';
        $immobile->descrizione = 'Negozio della ferramenta';
        $this->addEntity($immobile);
    }
}
