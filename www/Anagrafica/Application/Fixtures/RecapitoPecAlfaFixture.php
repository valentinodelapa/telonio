<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Recapito;
use Anagrafica\Application\Enumerations\TipoRecapito;
use SismaFramework\Core\BaseClasses\BaseFixture;

class RecapitoPecAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $recapito = new Recapito($this->dataMapper);
        $recapito->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $recapito->tipoRecapito = TipoRecapito::pec;
        $recapito->valore = 'alfaconsulting@pec.it';
        $recapito->principale = true;
        $this->addEntity($recapito);
    }
}
