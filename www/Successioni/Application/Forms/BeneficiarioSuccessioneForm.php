<?php

namespace Successioni\Application\Forms;

use CommSuite\Application\HelperClasses\CodiceFiscale;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use SismaFramework\Orm\ExtendedClasses\StandardEntity;
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
        if (isset($this->request->input['codiceFiscaleTrust']) && is_string($this->request->input['codiceFiscaleTrust'])) {
            $this->addRequest('codiceFiscaleTrust', strtoupper(trim($this->request->input['codiceFiscaleTrust'])));
        }
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
                ->addFilterFieldMode('gradoParentelaRappresentato', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('codiceFiscaleTrust', FilterType::isMaxLimitString, [16], true)
                ->addFilterFieldMode('beneficiarioFinale', FilterType::isEntity, [], true);
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
        if ($this->verificaTrust($dati) === false) {
            $valido = false;
        }
        if (($dati->tipoBeneficiario !== TipoBeneficiario::trust) && ($dati->soggetto !== null) && ($dati->soggetto->isPersonaFisica() === false) && ($dati->gradoParentela !== null)
                && (in_array($dati->gradoParentela, [GradoParentela::estraneo, GradoParentela::stato, GradoParentela::entiTerritorialiTerzoSettore, GradoParentela::entiFondazioniArt3c1, GradoParentela::entiFondazioniArt3c2], true) === false)) {
            $this->formFilterError->gradoParentelaCustomMessage = 'Per società ed enti il grado è 35 (estraneo) o 36-39 (Stato ed enti).';
            $valido = false;
        }
        return $valido;
    }

    /**
     * Trust testamentario (tipo 5): il soggetto è il trustee, il grado di
     * parentela è quello tra defunto e beneficiario finale (35 se non
     * individuato); il beneficiario finale è obbligatorio con un grado
     * diverso da 35 o con la disabilità.
     */
    private function verificaTrust(StandardEntity $dati): bool
    {
        $valido = true;
        if ($dati->tipoBeneficiario !== TipoBeneficiario::trust) {
            if (($dati->codiceFiscaleTrust !== null) || ($dati->beneficiarioFinale !== null)) {
                $this->formFilterError->codiceFiscaleTrustCustomMessage = 'Dati del trust solo per il tipo soggetto "Trust".';
                $valido = false;
            }
            return $valido;
        }
        if (($dati->beneficiarioFinale === null) && (($dati->gradoParentela !== GradoParentela::estraneo) || $dati->disabile)) {
            $this->formFilterError->beneficiarioFinaleCustomMessage = 'Indicare il beneficiario finale (se non è individuato il grado è 35 - estraneo).';
            $valido = false;
        }
        if (($dati->beneficiarioFinale !== null) && ($dati->soggetto !== null) && ($dati->beneficiarioFinale->id === $dati->soggetto->id)) {
            $this->formFilterError->beneficiarioFinaleCustomMessage = 'Il trustee non può essere beneficiario finale del trust (modello 4 cartaceo).';
            $valido = false;
        }
        if (($dati->beneficiarioFinale !== null) && ($dati->beneficiarioFinale->id === $this->entity->dichiarazioneSuccessione->defunto->id)) {
            $this->formFilterError->beneficiarioFinaleCustomMessage = 'Il defunto non può essere beneficiario finale.';
            $valido = false;
        }
        if (($dati->codiceFiscaleTrust !== null) && (CodiceFiscale::isValidoNumerico($dati->codiceFiscaleTrust) === false)) {
            $this->formFilterError->codiceFiscaleTrustCustomMessage = 'Codice fiscale del trust non valido (11 cifre).';
            $valido = false;
        }
        if ($dati->rinuncia || ($dati->rappresentato !== null)) {
            $this->formFilterError->tipoBeneficiarioCustomMessage = 'Per il trust non si indicano rinuncia e rappresentazione.';
            $valido = false;
        }
        return $valido;
    }
}
