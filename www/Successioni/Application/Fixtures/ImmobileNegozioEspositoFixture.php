<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Models\ComuneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Negozio demo a Trento (catasto tavolare), sede della ferramenta di Giuseppe Esposito.
 */
class ImmobileNegozioEspositoFixture extends BaseFixture
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
        $immobile->comune = (new ComuneModel($this->dataMapper))->getByCodice('L378');
        $immobile->foglio = '5';
        $immobile->particella = '1234/2';
        $immobile->subalterno = '3';
        $immobile->indirizzo = 'Via Belenzani';
        $immobile->numeroCivico = '10';
        $immobile->descrizione = 'Negozio';
        $immobile->partitaTavolare = '4567';
        $immobile->corpoTavolare = '1';
        $immobile->comuneCatastale = 'Trento';
        $this->addEntity($immobile);
    }
}
