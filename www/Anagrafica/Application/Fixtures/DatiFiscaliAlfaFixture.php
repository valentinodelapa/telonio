<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Prima versione dei dati fiscali di Alfa, chiusa dal cambio di
 * denominazione del 01/07/2023.
 */
class DatiFiscaliAlfaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $datiFiscali->validoDal = new SismaDate('2020-01-15');
        $datiFiscali->validoAl = new SismaDate('2023-06-30');
        $datiFiscali->denominazione = 'Alfa S.r.l.';
        $datiFiscali->naturaGiuridica = NaturaGiuridica::societaResponsabilitaLimitata;
        $datiFiscali->partitaIva = '01234567897';
        $datiFiscali->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2022, '69.20.11');
        $datiFiscali->descrizioneAttivita = 'Servizi forniti da dottori commercialisti';
        $this->addEntity($datiFiscali);
    }
}
