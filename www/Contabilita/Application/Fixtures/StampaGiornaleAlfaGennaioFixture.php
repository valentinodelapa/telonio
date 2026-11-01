<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use Contabilita\Application\Services\StampaDefinitiva\StampeDefinitive;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Stampa definitiva demo del libro giornale di Alfa per gennaio 2026
 * (pagine dalla 2026/1): le registrazioni di gennaio non si modificano
 * più. Dipende da tutte le registrazioni di gennaio, compreso il
 * giroconto della liquidazione.
 */
class StampaGiornaleAlfaGennaioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(FatturaVenditaAlfaFixture::class)
                ->addDependency(FatturaAcquistoAlfaFixture::class)
                ->addDependency(FatturaSplitAlfaFixture::class)
                ->addDependency(NotaCreditoAlfaFixture::class)
                ->addDependency(AcquistoReverseChargeAlfaFixture::class)
                ->addDependency(IncassoBetaAlfaFixture::class)
                ->addDependency(SezionaleVenditeAlfaFixture::class)
                ->addDependency(LiquidazioneAlfaGennaioFixture::class)
                ->addDependency(ComunicazioneLipeAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity((new StampeDefinitive($this->dataMapper))->registra($this->getEntityByFixtureName(SoggettoAlfaFixture::class), TipoStampaDefinitiva::giornale, null, 2026, new SismaDate('2026-01-31')));
    }
}
