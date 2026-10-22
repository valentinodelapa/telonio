<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\StatoCivile;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use CommSuite\Application\Fixtures\StudioFixture;
use CommSuite\Application\HelperClasses\CodiceFiscale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Figlia demo: Laura Esposito.
 */
class SoggettoEspositoLauraFixture extends BaseFixture
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
        $soggetto->codice = 'C023';
        $soggetto->cognome = 'Esposito';
        $soggetto->nome = 'Laura';
        $soggetto->nominativo = mb_strtoupper($soggetto->cognome . ' ' . $soggetto->nome);
        $soggetto->clienteStudio = false;
        $soggetto->sesso = Sesso::femmina;
        $soggetto->dataNascita = new SismaDate('1982-04-08');
        $soggetto->comuneNascita = (new ComuneModel($this->dataMapper))->getByCodiceAl('F839', $soggetto->dataNascita);
        $soggetto->codiceFiscale = CodiceFiscale::calcola($soggetto->cognome, $soggetto->nome, $soggetto->sesso->value, $soggetto->dataNascita, 'F839');
        $soggetto->cittadinanza = (new NazioneModel($this->dataMapper))->getItalia();
        $soggetto->statoCivile = StatoCivile::celibeNubile;
        $this->addEntity($soggetto);
    }
}
