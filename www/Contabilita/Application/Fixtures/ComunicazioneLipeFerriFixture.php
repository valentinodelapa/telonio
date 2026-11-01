<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Deleghe\Application\Fixtures\IntermediarioStudioFixture;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Comunicazione demo delle liquidazioni periodiche del negozio Ferri
 * (trimestrale, persona fisica senza dichiarante) per il primo trimestre
 * 2026, in bozza: un modulo con gli interessi e l'imponibile dei
 * corrispettivi ventilati.
 */
class ComunicazioneLipeFerriFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class)
                ->addDependency(IntermediarioStudioFixture::class)
                ->addDependency(LiquidazioneFerriPrimoTrimestreFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $this->addEntity((new PreparazioneLipe($this->dataMapper))->prepara($this->getEntityByFixtureName(SoggettoFerriFixture::class), 2026, 1));
    }
}
