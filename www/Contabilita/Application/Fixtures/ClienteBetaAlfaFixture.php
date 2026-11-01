<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Services\Partitario;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Cliente demo di Alfa, con il sottoconto del partitario.
 */
class ClienteBetaAlfaFixture extends BaseFixture
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
        $controparte = new Controparte($this->dataMapper);
        $controparte->studio = $alfa->studio;
        $controparte->soggetto = $alfa;
        $controparte->denominazione = 'Beta Commerciale S.p.A.';
        $controparte->partitaIva = '02812341002';
        $controparte->codiceFiscale = '02812341002';
        $controparte->indirizzo = 'Via Roma 10';
        $controparte->cap = '20121';
        $controparte->comune = 'Milano';
        $controparte->provincia = 'MI';
        $controparte->codiceDestinatario = 'ABC1234';
        $controparte->cliente = true;
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        $this->addEntity($controparte);
    }
}
