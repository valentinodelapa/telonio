<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\ModalitaPagamento;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Services\Partitario;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Cliente demo di Alfa, con il sottoconto del partitario e i termini di
 * pagamento abituali (RIBA a 30 e 60 giorni fine mese) proposti come rate.
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
        $controparte->giorniPagamento = '30,60';
        $controparte->fineMesePagamento = true;
        $controparte->modalitaPagamento = ModalitaPagamento::riba;
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        $this->addEntity($controparte);
    }
}
