<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Liquidazione IVA demo di Alfa per febbraio 2026 con, a titolo di
 * esempio, IVA già versata con F24 elementi identificativi sulle auto di
 * provenienza UE (120 euro, rigo VP10) e un credito d'imposta scomputato
 * dal versamento (80 euro, rigo VP11).
 */
class LiquidazioneAlfaFebbraioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(LiquidazioneAlfaGennaioFixture::class)
                ->addDependency(FatturaVenditaAlfaFebbraioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity((new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->getEntityByFixtureName(SoggettoAlfaFixture::class), 2026, 2, 0.0, true, 120.0, 80.0));
    }
}
