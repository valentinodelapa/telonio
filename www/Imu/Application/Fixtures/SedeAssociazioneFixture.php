<?php

namespace Imu\Application\Fixtures;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Sede legale demo dell'associazione.
 */
class SedeAssociazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAssociazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->soggetto = $this->getEntityByFixtureName(SoggettoAssociazioneFixture::class);
        $indirizzo->tipoIndirizzo = TipoIndirizzo::sedeLegale;
        $indirizzo->validoDal = new SismaDate('2010-03-01');
        $indirizzo->indirizzo = 'Via dei Sabelli';
        $indirizzo->numeroCivico = '12';
        $indirizzo->cap = '00185';
        $indirizzo->comune = (new ComuneModel($this->dataMapper))->getByCodice('H501');
        $indirizzo->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->addEntity($indirizzo);
    }
}
