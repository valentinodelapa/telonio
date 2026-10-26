<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\NaturaGiuridica;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Versione vigente dei dati fiscali di Alfa: dal 01/04/2025 l'attività è
 * codificata in ATECO 2025 (69.20.11 ATECO 2022 -> 69.20.01).
 */
class DatiFiscaliAlfaAteco2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(DatiFiscaliAlfaVariazioneFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->getEntityByFixtureName(SoggettoAlfaFixture::class);
        $datiFiscali->validoDal = new SismaDate('2025-04-01');
        $datiFiscali->denominazione = 'Alfa Consulting S.r.l.';
        $datiFiscali->naturaGiuridica = NaturaGiuridica::societaResponsabilitaLimitata;
        $datiFiscali->partitaIva = '01234567897';
        $datiFiscali->regimeFiscale = RegimeFiscale::ordinario;
        $datiFiscali->periodicitaIva = PeriodicitaIva::mensile;
        $datiFiscali->soggettoIsa = true;
        $datiFiscali->sostitutoImposta = true;
        $datiFiscali->codiceAteco = (new CodiceAtecoModel($this->dataMapper))->getByCodice(ClassificazioneAteco::ateco2025, '69.20.01');
        $datiFiscali->descrizioneAttivita = 'Servizi forniti da dottori commercialisti';
        $this->addEntity($datiFiscali);
    }
}
