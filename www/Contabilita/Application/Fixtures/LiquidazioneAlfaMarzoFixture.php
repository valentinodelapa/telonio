<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Liquidazione IVA demo di Alfa per marzo 2026: completa il trimestre per
 * la comunicazione delle liquidazioni periodiche.
 */
class LiquidazioneAlfaMarzoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(LiquidazioneAlfaFebbraioFixture::class)
                ->addDependency(FatturaElettronicaNonConformeAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity((new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->getEntityByFixtureName(SoggettoAlfaFixture::class), 2026, 3, 0.0, true));
    }
}
