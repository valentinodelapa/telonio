<?php

namespace Deleghe\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Lo studio demo come soggetto dell'anagrafica (non cliente): è
 * l'intermediario Entratel che trasmette le deleghe.
 */
class SoggettoStudioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $studio = $this->getEntityByFixtureName(StudioFixture::class);
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::societaPersone;
        $soggetto->codice = 'STUDIO';
        $soggetto->codiceFiscale = $studio->codiceFiscale;
        $soggetto->nominativo = $studio->denominazione;
        $soggetto->clienteStudio = false;
        $this->addEntity($soggetto);
    }
}
