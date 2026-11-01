<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Fixtures\SoggettoFerriFixture;
use Contabilita\Application\Services\Partitario;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fornitore demo del negozio Ferri di materiali di consumo, esclusi dalla
 * base della ventilazione.
 */
class FornitoreServiziFerriFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class)
                ->addDependency(EsercizioFerri2026Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $azienda = $this->getEntityByFixtureName(SoggettoFerriFixture::class);
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $azienda->studio;
        $controparte->soggetto = $azienda;
        $controparte->denominazione = 'Gamma Servizi S.r.l.';
        $controparte->partitaIva = '03987651001';
        $controparte->codiceFiscale = '03987651001';
        $controparte->indirizzo = 'Corso Italia 5';
        $controparte->cap = '00198';
        $controparte->comune = 'Roma';
        $controparte->provincia = 'RM';
        $controparte->cliente = false;
        $controparte->fornitore = true;
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        $this->addEntity($controparte);
    }
}
