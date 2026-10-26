<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\ContoCorrente;
use Telonio\Application\HelperClasses\Iban;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

class ContoCorrenteForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return ContoCorrente::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['iban']) && is_string($this->request->input['iban'])) {
            $this->addRequest('iban', Iban::normalizza($this->request->input['iban']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('iban', FilterType::isMaxLimitString, [34])
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('predefinito', FilterType::isBoolean)
                ->addFilterFieldMode('attivo', FilterType::isBoolean);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if (($this->entityData->iban !== null) && (Iban::isValido($this->entityData->iban) === false)) {
            $this->formFilterError->ibanCustomMessage = 'IBAN non valido (controllare lunghezza e cifre di controllo).';
            return false;
        }
        if ($this->entityData->predefinito && ($this->entityData->attivo === false)) {
            $this->formFilterError->predefinitoCustomMessage = 'Un conto estinto non può essere predefinito.';
            return false;
        }
        return true;
    }
}
