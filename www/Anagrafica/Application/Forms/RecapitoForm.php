<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\Recapito;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

class RecapitoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Recapito::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['valore']) && is_string($this->request->input['valore'])) {
            $this->addRequest('valore', trim($this->request->input['valore']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipoRecapito', FilterType::isEnumeration)
                ->addFilterFieldMode('valore', FilterType::isMaxLimitString, [255])
                ->addFilterFieldMode('principale', FilterType::isBoolean)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $tipoRecapito = $this->entityData->tipoRecapito;
        if (($tipoRecapito !== null) && $tipoRecapito->isIndirizzoEmail() && (filter_var($this->entityData->valore, FILTER_VALIDATE_EMAIL) === false)) {
            $this->formFilterError->valoreCustomMessage = 'Indirizzo e-mail non valido.';
            return false;
        }
        return true;
    }
}
