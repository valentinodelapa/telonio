<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Sede legale originaria di Alfa, chiusa dal trasferimento a Milano.
 */
class SedeLegaleAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $indirizzo->tipoIndirizzo = TipoIndirizzo::sedeLegale;
        $indirizzo->validoDal = new SismaDate('2020-01-15');
        $indirizzo->validoAl = new SismaDate('2024-01-31');
        $indirizzo->indirizzo = 'Via Roma';
        $indirizzo->numeroCivico = '1';
        $indirizzo->cap = '00184';
        $indirizzo->comune = (new ComuneModel($this->dataMapper))->getByCodice('H501');
        $indirizzo->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->addEntity($indirizzo);
    }
}
