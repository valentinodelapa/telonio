<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\AccontoIva;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Metodo e base dell'acconto IVA; l'importo si calcola dal metodo.
 */
class AccontoIvaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return AccontoIva::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['base']) && is_string($this->request->input['base'])) {
            $this->addRequest('base', Importo::normalizza($this->request->input['base']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('metodo', FilterType::isEnumeration)
                ->addFilterFieldMode('base', FilterType::isNumeric)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [2000], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if ($this->entityData->base < 0) {
            $this->formFilterError->baseCustomMessage = 'La base non può essere negativa.';
            return false;
        }
        return true;
    }
}
