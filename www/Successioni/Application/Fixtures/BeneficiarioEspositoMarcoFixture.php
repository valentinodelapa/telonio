<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;

/**
 * Figlio erede per legge (1/3), dichiarante.
 */
class BeneficiarioEspositoMarcoFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
        $this->addDependency(SoggettoEspositoMarcoFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $beneficiario->soggetto = $this->getEntityByFixtureName(SoggettoEspositoMarcoFixture::class);
        $beneficiario->ordine = 2;
        $beneficiario->tipoBeneficiario = TipoBeneficiario::erede;
        $beneficiario->gradoParentela = GradoParentela::figlio;
        $this->addEntity($beneficiario);
    }
}
