<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Titolare demo di un negozio di alimentari al dettaglio, cliente dello
 * studio per la contabilità con la ventilazione dei corrispettivi.
 */
class SoggettoFerriFixture extends BaseFixture
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
        $soggetto->codice = 'C030';
        $soggetto->codiceFiscale = 'FRRLCU78E52H501I';
        $soggetto->nominativo = 'FERRI LUCIA';
        $soggetto->partitaIva = '06987651004';
        $soggetto->tipoAttivita = TipoAttivita::impresaIndividuale;
        $soggetto->clienteStudio = true;
        $soggetto->cognome = 'Ferri';
        $soggetto->nome = 'Lucia';
        $soggetto->sesso = Sesso::femmina;
        $soggetto->dataNascita = new SismaDate('1978-05-12');
        $soggetto->comuneNascita = (new ComuneModel($this->dataMapper))->getByCodiceAl('H501', $soggetto->dataNascita);
        $soggetto->cittadinanza = (new NazioneModel($this->dataMapper))->getItalia();
        $this->addEntity($soggetto);
    }
}
