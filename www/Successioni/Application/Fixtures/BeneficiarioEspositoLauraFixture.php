<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;

/**
 * Figlia erede per legge (1/3).
 */
class BeneficiarioEspositoLauraFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
        $this->addDependency(SoggettoEspositoLauraFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $beneficiario->soggetto = $this->getEntityByFixtureName(SoggettoEspositoLauraFixture::class);
        $beneficiario->ordine = 3;
        $beneficiario->tipoBeneficiario = TipoBeneficiario::erede;
        $beneficiario->gradoParentela = GradoParentela::figlio;
        $this->addEntity($beneficiario);
    }
}
