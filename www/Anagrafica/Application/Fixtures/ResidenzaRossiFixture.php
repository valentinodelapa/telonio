<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ResidenzaRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $indirizzo->tipoIndirizzo = TipoIndirizzo::residenza;
        $indirizzo->validoDal = new SismaDate('2010-01-01');
        $indirizzo->indirizzo = 'Via Appia Nuova';
        $indirizzo->numeroCivico = '100';
        $indirizzo->cap = '00183';
        $indirizzo->comune = (new ComuneModel($this->dataMapper))->getByCodice('H501');
        $indirizzo->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->addEntity($indirizzo);
    }
}
