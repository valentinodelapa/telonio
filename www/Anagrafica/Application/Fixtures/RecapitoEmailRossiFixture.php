<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Recapito;
use Anagrafica\Application\Enumerations\TipoRecapito;
use SismaFramework\Core\BaseClasses\BaseFixture;

class RecapitoEmailRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $recapito = new Recapito($this->dataMapper);
        $recapito->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $recapito->tipoRecapito = TipoRecapito::email;
        $recapito->valore = 'mario.rossi@example.com';
        $recapito->principale = true;
        $this->addEntity($recapito);
    }
}
