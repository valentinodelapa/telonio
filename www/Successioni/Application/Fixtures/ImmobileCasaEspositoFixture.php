<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Appartamento demo a Napoli, in comproprietà tra Giuseppe Esposito e la moglie.
 */
class ImmobileCasaEspositoFixture extends BaseFixture
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
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('F839');
        $immobile->foglio = '128';
        $immobile->particella = '311';
        $immobile->subalterno = '9';
        $immobile->indirizzo = 'Via Toledo';
        $immobile->numeroCivico = '256';
        $immobile->piano = '2';
        $immobile->descrizione = 'Appartamento';
        $this->addEntity($immobile);
    }
}
