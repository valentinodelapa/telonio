<?php

namespace Successioni\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\StatoCivile;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use Telonio\Application\Fixtures\StudioFixture;
use Telonio\Application\HelperClasses\CodiceFiscale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Defunto demo: Giuseppe Esposito, deceduto a Napoli il 12 settembre 2025 (successione con autoliquidazione).
 */
class SoggettoEspositoGiuseppeFixture extends BaseFixture
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
        $soggetto->codice = 'C020';
        $soggetto->cognome = 'Esposito';
        $soggetto->nome = 'Giuseppe';
        $soggetto->nominativo = mb_strtoupper($soggetto->cognome . ' ' . $soggetto->nome);
        $soggetto->clienteStudio = true;
        $soggetto->sesso = Sesso::maschio;
        $soggetto->dataNascita = new SismaDate('1948-03-15');
        $soggetto->comuneNascita = (new ComuneModel($this->dataMapper))->getByCodiceAl('F839', $soggetto->dataNascita);
        $soggetto->codiceFiscale = CodiceFiscale::calcola($soggetto->cognome, $soggetto->nome, $soggetto->sesso->value, $soggetto->dataNascita, 'F839');
        $soggetto->cittadinanza = (new NazioneModel($this->dataMapper))->getItalia();
        $soggetto->statoCivile = StatoCivile::coniugato;
        $soggetto->dataDecesso = new SismaDate('2025-09-12');
        $this->addEntity($soggetto);
    }
}
