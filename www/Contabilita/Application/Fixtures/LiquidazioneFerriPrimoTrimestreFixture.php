<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Services\ElaborazioneLiquidazione;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Liquidazione IVA demo del negozio Ferri per il primo trimestre 2026:
 * ventilazione dei corrispettivi con i rapporti degli acquisti per la
 * rivendita (anche di altri prodotti), interessi dell'1% e giroconto.
 */
class LiquidazioneFerriPrimoTrimestreFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class)
                ->addDependency(CorrispettiviFerriMarzoFixture::class)
                ->addDependency(CorrispettiviFerriGennaioFixture::class)
                ->addDependency(CorrispettiviFerriFebbraioFixture::class)
                ->addDependency(AcquistoFerriAltriProdottiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity((new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->getEntityByFixtureName(SoggettoFerriFixture::class), 2026, 1, 0.0, true));
    }
}
