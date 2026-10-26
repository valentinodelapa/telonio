<?php

namespace GestioneStudio\Application\Forms;

use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Models\AdempimentoModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Adempimento proprio dello studio; lo studio è impostato dal controller.
 */
class AdempimentoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Adempimento::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['codice']) && is_string($this->request->input['codice'])) {
            $this->addRequest('codice', strtoupper(trim($this->request->input['codice'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codice', FilterType::isLimitString, [1, 30])
                ->addFilterFieldMode('denominazione', FilterType::isLimitString, [1, 150])
                ->addFilterFieldMode('categoria', FilterType::isEnumeration)
                ->addFilterFieldMode('ambito', FilterType::isEnumeration)
                ->addFilterFieldMode('fonte', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('slittamentoFestivi', FilterType::isBoolean)
                ->addFilterFieldMode('prorogaAgosto', FilterType::isBoolean)
                ->addFilterFieldMode('attivo', FilterType::isBoolean)
                ->addFilterFieldMode('note', FilterType::isString, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if ((new AdempimentoModel())->esisteCodice($this->entity->studio, $this->entityData->codice, $this->entity)) {
            $this->formFilterError->codiceCustomMessage = 'Codice già usato da un altro adempimento del calendario o dello studio.';
            return false;
        }
        if ($this->entityData->prorogaAgosto && ($this->entityData->slittamentoFestivi === false)) {
            $this->formFilterError->prorogaAgostoCustomMessage = 'Il differimento di agosto si applica insieme allo slittamento dei termini festivi.';
            return false;
        }
        return true;
    }
}
