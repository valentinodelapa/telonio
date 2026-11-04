<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Fixtures\StudioFixture;

/**
 * Dottore commercialista demo dello studio che appone il visto di
 * conformità sulle dichiarazioni.
 */
class ProfessionistaMariniFixture extends BaseFixture
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
        $soggetto->codice = 'P001';
        $soggetto->codiceFiscale = 'MRNPLA70A01F205L';
        $soggetto->nominativo = 'MARINI PAOLO';
        $soggetto->clienteStudio = false;
        $soggetto->cognome = 'Marini';
        $soggetto->nome = 'Paolo';
        $soggetto->sesso = Sesso::maschio;
        $soggetto->dataNascita = new SismaDate('1970-01-01');
        $soggetto->comuneNascita = (new ComuneModel($this->dataMapper))->getByCodiceAl('F205', $soggetto->dataNascita);
        $soggetto->cittadinanza = (new NazioneModel($this->dataMapper))->getItalia();
        $soggetto->note = 'Dottore commercialista dello studio: visto di conformità';
        $this->addEntity($soggetto);
    }
}
