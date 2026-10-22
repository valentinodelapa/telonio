<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Luogo di esercizio dell'attività della ditta individuale.
 */
class SedeOperativaVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $indirizzo->tipoIndirizzo = TipoIndirizzo::sedeOperativa;
        $indirizzo->validoDal = new SismaDate('2015-03-01');
        $indirizzo->indirizzo = 'Via Padova';
        $indirizzo->numeroCivico = '45';
        $indirizzo->cap = '20127';
        $indirizzo->comune = (new ComuneModel($this->dataMapper))->getByCodice('F205');
        $indirizzo->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->addEntity($indirizzo);
    }
}
