<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Services\Partitario;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Cliente demo di Alfa: pubblica amministrazione, con la scissione dei
 * pagamenti (art. 17-ter D.P.R. 633/1972) proposta sulle vendite.
 */
class ClientePaAlfaFixture extends BaseFixture
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
        $controparte->denominazione = 'Comune di Esempio';
        $controparte->partitaIva = null;
        $controparte->codiceFiscale = '80012345676';
        $controparte->indirizzo = 'Piazza del Municipio 1';
        $controparte->cap = '00100';
        $controparte->comune = 'Esempio';
        $controparte->provincia = 'RM';
        $controparte->cliente = true;
        $controparte->fornitore = false;
        $controparte->pubblicaAmministrazione = true;
        $controparte->codiceDestinatario = 'UF1234';
        $this->dataMapper->save($controparte);
        (new Partitario($this->dataMapper))->allinea($controparte);
        $this->addEntity($controparte);
    }
}
