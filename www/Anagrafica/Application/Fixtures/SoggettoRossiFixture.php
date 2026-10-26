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
 * Persona fisica nata in Italia, cliente dello studio, amministratore e
 * socio di Alfa.
 */
class SoggettoRossiFixture extends BaseFixture
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
        $soggetto->codice = 'C001';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->clienteStudio = true;
        $soggetto->cognome = 'Rossi';
        $soggetto->nome = 'Mario';
        $soggetto->sesso = Sesso::maschio;
        $soggetto->dataNascita = new SismaDate('1980-01-01');
        $soggetto->comuneNascita = (new ComuneModel($this->dataMapper))->getByCodiceAl('H501', $soggetto->dataNascita);
        $soggetto->cittadinanza = (new NazioneModel($this->dataMapper))->getItalia();
        $soggetto->statoCivile = StatoCivile::coniugato;
        $this->addEntity($soggetto);
    }
}
