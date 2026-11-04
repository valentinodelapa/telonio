<?php

namespace Dichiarativi\Application\Fixtures;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use Contabilita\Application\Fixtures\SoggettoFerriFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Sede operativa demo del negozio Ferri a Roma: luogo di esercizio
 * dell'attività per la ripartizione regionale dei corrispettivi nel quadro
 * VT della dichiarazione IVA.
 */
class SedeFerriFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->soggetto = $this->getEntityByFixtureName(SoggettoFerriFixture::class);
        $indirizzo->tipoIndirizzo = TipoIndirizzo::sedeOperativa;
        $indirizzo->validoDal = new SismaDate('2025-04-01');
        $indirizzo->indirizzo = 'Via Tuscolana';
        $indirizzo->numeroCivico = '210';
        $indirizzo->cap = '00182';
        $indirizzo->comune = (new ComuneModel($this->dataMapper))->getByCodice('H501');
        $indirizzo->nazione = (new NazioneModel($this->dataMapper))->getItalia();
        $this->addEntity($indirizzo);
    }
}
