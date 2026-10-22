<?php

namespace Successioni\Application\Forms;

use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;

/**
 * Beneficiario del quadro EA. Dichiarazione e ordine sono impostati dal
 * controller.
 */
class BeneficiarioSuccessioneForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return BeneficiarioSuccessione::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['rinunciaSerie', 'rinunciaNumero', 'rinunciaSottonumero'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, strtoupper(trim($this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('soggetto', FilterType::isEntity)
                ->addFilterFieldMode('tipoBeneficiario', FilterType::isEnumeration)
                ->addFilterFieldMode('gradoParentela', FilterType::isEnumeration)
                ->addFilterFieldMode('disabile', FilterType::isBoolean)
                ->addFilterFieldMode('certificatoreDisabilita', FilterType::isMaxLimitString, [35], true)
                ->addFilterFieldMode('rinuncia', FilterType::isBoolean)
                ->addFilterFieldMode('rinunciaUfficio', FilterType::isEntity, [], true)
                ->addFilterFieldMode('rinunciaSerie', FilterType::isMaxLimitString, [2], true)
                ->addFilterFieldMode('rinunciaNumero', FilterType::isMaxLimitString, [6], true)
                ->addFilterFieldMode('rinunciaSottonumero', FilterType::isMaxLimitString, [3], true)
                ->addFilterFieldMode('rinunciaData', FilterType::isDate, [], true)
                ->addFilterFieldMode('rappresentato', FilterType::isEntity, [], true)
                ->addFilterFieldMode('gradoParentelaRappresentato', FilterType::isEnumeration, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        $valido = true;
        if (($dati->soggetto !== null) && ($dati->soggetto->id === $this->entity->dichiarazioneSuccessione->defunto->id)) {
            $this->formFilterError->soggettoCustomMessage = 'Il defunto non può essere beneficiario.';
            $valido = false;
        }
        if (($dati->tipoBeneficiario === TipoBeneficiario::coniugeRinunciatario) && (($dati->gradoParentela !== GradoParentela::coniuge) || ($dati->rinuncia === false))) {
            $this->formFilterError->tipoBeneficiarioCustomMessage = 'Il coniuge rinunciatario ha grado 01 e la rinuncia indicata.';
            $valido = false;
        }
        if ($dati->disabile && ($dati->certificatoreDisabilita === null)) {
            $this->formFilterError->certificatoreDisabilitaCustomMessage = 'Indicare chi ha certificato la disabilità.';
            $valido = false;
        }
        if ($dati->rinuncia && (($dati->rinunciaUfficio === null) || ($dati->rinunciaSerie === null) || ($dati->rinunciaNumero === null) || ($dati->rinunciaData === null))) {
            $this->formFilterError->rinunciaUfficioCustomMessage = 'Indicare gli estremi di registrazione della rinuncia.';
            $valido = false;
        }
        if (($dati->rappresentato !== null) && (in_array($dati->gradoParentelaRappresentato, [GradoParentela::figlio, GradoParentela::fratelloGermano, GradoParentela::fratelloUnilaterale], true) === false)) {
            $this->formFilterError->gradoParentelaRappresentatoCustomMessage = 'Il rappresentato è un figlio o un fratello del defunto.';
            $valido = false;
        }
        if (($dati->soggetto !== null) && ($dati->soggetto->isPersonaFisica() === false) && ($dati->gradoParentela !== null)
                && (in_array($dati->gradoParentela, [GradoParentela::estraneo, GradoParentela::stato, GradoParentela::entiTerritorialiTerzoSettore, GradoParentela::entiFondazioniArt3c1, GradoParentela::entiFondazioniArt3c2], true) === false)) {
            $this->formFilterError->gradoParentelaCustomMessage = 'Per società ed enti il grado è 35 (estraneo) o 36-39 (Stato ed enti).';
            $valido = false;
        }
        return $valido;
    }
}
