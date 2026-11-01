<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Services\Partitario;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fornitore demo di Alfa per lavori edili in subappalto (inversione
 * contabile, art. 17, c. 6, lett. a, D.P.R. 633/1972).
 */
class FornitoreDeltaAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(EsercizioAlfa2026Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $azienda = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $azienda->studio;
        $controparte->soggetto = $azienda;
        $controparte->denominazione = 'Delta Edilizia S.r.l.';
        $controparte->partitaIva = '04123451009';
        $controparte->codiceFiscale = '04123451009';
        $controparte->indirizzo = 'Via Napoli 3';
        $controparte->cap = '80100';
        $controparte->comune = 'Napoli';
        $controparte->provincia = 'NA';
        $controparte->cliente = false;
        $controparte->fornitore = true;
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        $this->addEntity($controparte);
    }
}
