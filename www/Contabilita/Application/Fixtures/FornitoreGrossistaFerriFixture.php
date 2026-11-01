<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Fixtures\SoggettoFerriFixture;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\Partitario;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Grossista demo del negozio Ferri: merci per la rivendita, con il costo
 * abituale "Acquisti di merci".
 */
class FornitoreGrossistaFerriFixture extends BaseFixture
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
        $controparte->denominazione = 'Distribuzione Alimentare Sud S.p.A.';
        $controparte->partitaIva = '07135791007';
        $controparte->codiceFiscale = '07135791007';
        $controparte->indirizzo = 'Via dei Mercati 12';
        $controparte->cap = '00155';
        $controparte->comune = 'Roma';
        $controparte->provincia = 'RM';
        $controparte->cliente = false;
        $controparte->fornitore = true;
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($azienda);
        $controparte->contoContropartita = (new ContoModel($this->dataMapper))->getPerFunzione($piano, FunzioneConto::costi);
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        $this->addEntity($controparte);
    }
}
