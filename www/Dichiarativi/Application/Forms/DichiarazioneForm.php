<?php

namespace Dichiarativi\Application\Forms;

use Dichiarativi\Application\Entities\Dichiarazione;
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
                ->addFilterFieldMode('annotazioni', FilterType::isMaxLimitString, [500], true);
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
        if (($dati->intermediario !== null) && ($dati->dataImpegno === null)) {
            $this->formFilterError->dataImpegnoCustomMessage = 'Indicare la data dell\'impegno a trasmettere.';
            return false;
        }
        return true;
    }

}
