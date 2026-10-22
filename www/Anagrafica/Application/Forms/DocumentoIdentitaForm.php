<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\DocumentoIdentita;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

class DocumentoIdentitaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return DocumentoIdentita::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['numero']) && is_string($this->request->input['numero'])) {
            $this->addRequest('numero', strtoupper(str_replace(' ', '', $this->request->input['numero'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipoDocumento', FilterType::isEnumeration)
                ->addFilterFieldMode('numero', FilterType::isMaxLimitString, [30])
                ->addFilterFieldMode('rilasciatoDa', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('dataRilascio', FilterType::isDate, [], true)
                ->addFilterFieldMode('dataScadenza', FilterType::isDate, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if (($this->entityData->dataRilascio !== null) && ($this->entityData->dataScadenza !== null) && ($this->entityData->dataScadenza < $this->entityData->dataRilascio)) {
            $this->formFilterError->dataScadenzaCustomMessage = 'La scadenza non può precedere la data di rilascio.';
            return false;
        }
        return true;
    }
}
