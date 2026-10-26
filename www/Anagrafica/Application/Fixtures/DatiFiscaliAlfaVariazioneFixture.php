<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Dati fiscali di Alfa dopo il cambio di denominazione, fino al passaggio
 * ad ATECO 2025.
 */
class DatiFiscaliAlfaVariazioneFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(DatiFiscaliAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $datiFiscali->validoDal = new SismaDate('2023-07-01');
        $datiFiscali->validoAl = new SismaDate('2025-03-31');
        $datiFiscali->denominazione = 'Alfa Consulting S.r.l.';
        $datiFiscali->naturaGiuridica = NaturaGiuridica::societaResponsabilitaLimitata;
        $datiFiscali->partitaIva = '01234567897';
        $datiFiscali->regimeFiscale = RegimeFiscale::ordinario;
        $datiFiscali->periodicitaIva = PeriodicitaIva::mensile;
        $datiFiscali->soggettoIsa = true;
        $datiFiscali->sostitutoImposta = true;
        $datiFiscali->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2022, '69.20.11');
        $datiFiscali->descrizioneAttivita = 'Servizi forniti da dottori commercialisti';
        $this->addEntity($datiFiscali);
    }
}
