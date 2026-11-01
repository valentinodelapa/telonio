<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Liquidazione IVA demo di Alfa per gennaio 2026, con il versamento
 * nell'archivio di Deleghe F24 e il giroconto in prima nota.
 */
class LiquidazioneAlfaGennaioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(IncassoBetaAlfaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity((new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->getEntityByFixtureName(SoggettoAlfaFixture::class), 2026, 1, 0.0, true));
    }
}
