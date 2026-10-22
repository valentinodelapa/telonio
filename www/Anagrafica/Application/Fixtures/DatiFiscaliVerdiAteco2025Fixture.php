<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Models\CodiceAtecoModel;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Versione vigente dell'impresa familiare di Verdi, con l'attività
 * codificata in ATECO 2025 dal 01/04/2025.
 */
class DatiFiscaliVerdiAteco2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoVerdiFixture::class)
                ->addDependency(DatiFiscaliVerdiImpresaFamiliareFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $datiFiscali->validoDal = new SismaDate('2025-04-01');
        $datiFiscali->tipoAttivita = TipoAttivita::impresaFamiliare;
        $datiFiscali->denominazione = 'Ferramenta Verdi';
        $datiFiscali->partitaIva = '07654321012';
        $datiFiscali->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, '47.52.10');
        $datiFiscali->descrizioneAttivita = 'Commercio al dettaglio di ferramenta';
        $this->addEntity($datiFiscali);
    }
}
