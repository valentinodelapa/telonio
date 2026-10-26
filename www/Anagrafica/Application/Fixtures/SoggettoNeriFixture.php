<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\StatoCivile;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use Telonio\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Coniuge di Verdi, collaboratrice nell'impresa familiare.
 */
class SoggettoNeriFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(StudioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->studio = $this->getEntityByFixtureName(StudioFixture::class);
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = 'NRENNA78L44F205O';
        $soggetto->nominativo = 'NERI ANNA';
        $soggetto->clienteStudio = false;
        $soggetto->cognome = 'Neri';
        $soggetto->nome = 'Anna';
        $soggetto->sesso = Sesso::femmina;
        $soggetto->dataNascita = new SismaDate('1978-07-04');
        $soggetto->comuneNascita = (new ComuneModel($this->dataMapper))->getByCodiceAl('F205', $soggetto->dataNascita);
        $soggetto->cittadinanza = (new NazioneModel($this->dataMapper))->getItalia();
        $soggetto->statoCivile = StatoCivile::coniugato;
        $this->addEntity($soggetto);
    }
}
