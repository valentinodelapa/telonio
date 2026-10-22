<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\StatoCivile;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Titolare di ditta individuale, trasformata in impresa familiare.
 *
 * Partita IVA e tipo di attività sono i valori correnti, coerenti con
 * DatiFiscaliVerdiImpresaFamiliareFixture.
 */
class SoggettoVerdiFixture extends BaseFixture
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
        $soggetto->codice = 'C003';
        $soggetto->codiceFiscale = 'VRDGPP75C12F205K';
        $soggetto->nominativo = 'VERDI GIUSEPPE';
        $soggetto->partitaIva = '07654321012';
        $soggetto->tipoAttivita = TipoAttivita::impresaFamiliare;
        $soggetto->clienteStudio = true;
        $soggetto->cognome = 'Verdi';
        $soggetto->nome = 'Giuseppe';
        $soggetto->sesso = Sesso::maschio;
        $soggetto->dataNascita = new SismaDate('1975-03-12');
        $soggetto->comuneNascita = (new ComuneModel($this->dataMapper))->getByCodiceAl('F205', $soggetto->dataNascita);
        $soggetto->cittadinanza = (new NazioneModel($this->dataMapper))->getItalia();
        $soggetto->statoCivile = StatoCivile::coniugato;
        $this->addEntity($soggetto);
    }
}
