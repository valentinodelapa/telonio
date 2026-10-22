<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Enumerations\TipoAttivita;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Ditta individuale di Verdi, fino alla trasformazione in impresa familiare.
 */
class DatiFiscaliVerdiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoVerdiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->getEntityByFixtureName(SoggettoVerdiFixture::class);
        $datiFiscali->validoDal = new SismaDate('2015-03-01');
        $datiFiscali->validoAl = new SismaDate('2022-12-31');
        $datiFiscali->tipoAttivita = TipoAttivita::impresaIndividuale;
        $datiFiscali->denominazione = 'Ferramenta Verdi';
        $datiFiscali->partitaIva = '07654321012';
        $datiFiscali->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2022, '47.52.10');
        $datiFiscali->descrizioneAttivita = 'Commercio al dettaglio di ferramenta';
        $this->addEntity($datiFiscali);
    }
}
