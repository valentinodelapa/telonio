<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Sede legale vigente di Alfa, dopo il trasferimento a Milano.
 */
class SedeLegaleAlfaTrasferimentoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(SedeLegaleAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $indirizzo->tipoIndirizzo = TipoIndirizzo::sedeLegale;
        $indirizzo->validoDal = new SismaDate('2024-02-01');
        $indirizzo->indirizzo = 'Corso Buenos Aires';
        $indirizzo->numeroCivico = '10';
        $indirizzo->cap = '20124';
        $indirizzo->comune = (new ComuneModel($this->dataMapper))->getByCodice('F205');
        $indirizzo->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->addEntity($indirizzo);
    }
}
