<?php

namespace Dichiarativi\Application\Forms;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\OttoPerMille;
use Dichiarativi\Application\Services\Redditi\PartitiDuePerMille;
use Dichiarativi\Application\Services\Frontespizio;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Frontespizio della dichiarazione: tipo, natura giuridica, sottoscrittore,
 * intermediario e impegno alla presentazione, conferma dei dati non
 * coerenti. Contribuente, modello e anno sono impostati dal controller.
 */
class DichiarazioneForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Dichiarazione::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['beneficiarioCinquePerMille', 'duePerMille'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(trim($this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipologia', FilterType::isEnumeration)
                ->addFilterFieldMode('naturaGiuridica', FilterType::isInteger, [], true)
                ->addFilterFieldMode('dichiarante', FilterType::isEntity, [], true)
                ->addFilterFieldMode('codiceCarica', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('intermediario', FilterType::isEntity, [], true)
                ->addFilterFieldMode('impegno', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('dataImpegno', FilterType::isDate, [], true)
                ->addFilterFieldMode('flagConferma', FilterType::isBoolean)
                ->addFilterFieldMode('vistoProfessionista', FilterType::isEntity, [], true)
                ->addFilterFieldMode('vistoCaf', FilterType::isEntity, [], true)
                ->addFilterFieldMode('vistoResponsabileCaf', FilterType::isEntity, [], true)
                ->addFilterFieldMode('esoneroVisto', FilterType::isBoolean)
                ->addFilterFieldMode('attestazionePatrimoniale', FilterType::isBoolean)
                ->addFilterFieldMode('verificaAccertamenti', FilterType::isBoolean)
                ->addFilterFieldMode('adempimentoCollaborativo', FilterType::isBoolean)
                ->addFilterFieldMode('concordatoBiennale', FilterType::isBoolean)
                ->addFilterFieldMode('annotazioni', FilterType::isMaxLimitString, [500], true)
                ->addFilterFieldMode('ottoPerMille', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('finalitaStato', FilterType::isInteger, [], true)
                ->addFilterFieldMode('cinquePerMille', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('beneficiarioCinquePerMille', FilterType::isMaxLimitString, [11], true)
                ->addFilterFieldMode('duePerMille', FilterType::isMaxLimitString, [3], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        if (($dati->naturaGiuridica !== null) && (isset(Frontespizio::TABELLA_NATURA_GIURIDICA[(int) $dati->naturaGiuridica]) === false)) {
            $this->formFilterError->naturaGiuridicaCustomMessage = 'Codice non presente nella tabella della natura giuridica.';
            return false;
        }
        if (($dati->dichiarante !== null) && ($dati->dichiarante->isPersonaFisica() === false)) {
            $this->formFilterError->dichiaranteCustomMessage = 'Il sottoscrittore deve essere una persona fisica.';
            return false;
        }
        if (($dati->dichiarante !== null) && ($dati->codiceCarica === null)) {
            $this->formFilterError->codiceCaricaCustomMessage = 'Indicare il codice di carica del sottoscrittore.';
            return false;
        }
        if (($dati->intermediario !== null) && ($dati->impegno === null)) {
            $this->formFilterError->impegnoCustomMessage = 'Indicare chi ha predisposto la dichiarazione.';
            return false;
        }
        if (($dati->vistoProfessionista !== null) && ($dati->vistoProfessionista->isPersonaFisica() === false)) {
            $this->formFilterError->vistoProfessionistaCustomMessage = 'Il visto di conformità si appone da un professionista persona fisica.';
            return false;
        }
        if (($dati->vistoProfessionista !== null) && ($dati->vistoCaf !== null)) {
            $this->formFilterError->vistoCafCustomMessage = 'Il visto è del professionista o del CAF, non di entrambi.';
            return false;
        }
        if (($dati->vistoCaf !== null) && ((preg_match('/^\d{11}$/', $dati->vistoCaf->codiceFiscale) !== 1) || ($dati->vistoResponsabileCaf === null) || ($dati->vistoResponsabileCaf->isPersonaFisica() === false))) {
            $this->formFilterError->vistoResponsabileCafCustomMessage = 'Indicare il CAF (codice fiscale numerico) e il responsabile dell\'assistenza fiscale (persona fisica).';
            return false;
        }
        if (($dati->finalitaStato !== null) && (($dati->ottoPerMille !== OttoPerMille::stato) || (isset(OttoPerMille::getFinalitaStato()[(int) $dati->finalitaStato]) === false))) {
            $this->formFilterError->finalitaStatoCustomMessage = 'La finalità si indica solo con la scelta per lo Stato.';
            return false;
        }
        $beneficiario = $dati->beneficiarioCinquePerMille;
        if (($beneficiario !== null) && ($beneficiario !== '')) {
            if (($dati->cinquePerMille === null) || ($dati->cinquePerMille->getPosizioneCodiceFiscale() === null)) {
                $this->formFilterError->beneficiarioCinquePerMilleCustomMessage = 'Il beneficiario si indica con una finalità del cinque per mille che lo prevede.';
                return false;
            }
            if (preg_match('/^\d{11}$/', $beneficiario) !== 1) {
                $this->formFilterError->beneficiarioCinquePerMilleCustomMessage = 'Codice fiscale numerico di 11 cifre.';
                return false;
            }
        }
        $partito = $dati->duePerMille;
        if (($partito !== null) && ($partito !== '') && (isset(PartitiDuePerMille::getPartiti($this->entity->anno)[$partito]) === false)) {
            $this->formFilterError->duePerMilleCustomMessage = 'Partito non presente nella tabella del modello.';
            return false;
        }
        if (($dati->intermediario !== null) && ($dati->dataImpegno === null)) {
            $this->formFilterError->dataImpegnoCustomMessage = 'Indicare la data dell\'impegno a trasmettere.';
            return false;
        }
        return true;
    }

}
