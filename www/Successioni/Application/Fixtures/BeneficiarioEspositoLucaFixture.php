<?php

namespace Successioni\Application\Fixtures;

use SismaFramework\Core\BaseClasses\BaseFixture;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;

/**
 * Nipote legatario per testamento (aliquota 6%, senza franchigia).
 */
class BeneficiarioEspositoLucaFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(DichiarazioneEspositoFixture::class);
        $this->addDependency(SoggettoEspositoLucaFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->dichiarazioneSuccessione = $this->getEntityByFixtureName(DichiarazioneEspositoFixture::class);
        $beneficiario->soggetto = $this->getEntityByFixtureName(SoggettoEspositoLucaFixture::class);
        $beneficiario->ordine = 4;
        $beneficiario->tipoBeneficiario = TipoBeneficiario::legatario;
        $beneficiario->gradoParentela = GradoParentela::nipoteFratello;
        $this->addEntity($beneficiario);
    }
}
