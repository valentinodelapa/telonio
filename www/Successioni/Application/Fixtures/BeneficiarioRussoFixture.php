<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;

/**
 * Coniuge erede per legge (1/3).
 */
class BeneficiarioRussoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
        $this->addDependency(SoggettoRussoAnnaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $beneficiario->soggetto = $this->getEntityByFixtureName(SoggettoRussoAnnaFixture::class);
        $beneficiario->ordine = 1;
        $beneficiario->tipoBeneficiario = TipoBeneficiario::erede;
        $beneficiario->gradoParentela = GradoParentela::coniuge;
        $this->addEntity($beneficiario);
    }
}
