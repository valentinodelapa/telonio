<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\Partitario;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Fornitore demo di Alfa (consulenze), con il sottoconto del partitario e
 * il costo abituale.
 */
class FornitoreGammaAlfaFixture extends BaseFixture
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
        $alfa = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($alfa);
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $alfa->studio;
        $controparte->soggetto = $alfa;
        $controparte->denominazione = 'Gamma Servizi S.r.l.';
        $controparte->partitaIva = '03987651001';
        $controparte->codiceFiscale = '03987651001';
        $controparte->indirizzo = 'Corso Italia 5';
        $controparte->cap = '00198';
        $controparte->comune = 'Roma';
        $controparte->provincia = 'RM';
        $controparte->fornitore = true;
        $controparte->contoContropartita = (new ContoModel($this->dataMapper))->getPerCodice($piano, '22.01.0001')
                ?? (new ContoModel($this->dataMapper))->getPerFunzione($piano, FunzioneConto::costi);
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        $this->addEntity($controparte);
    }
}
