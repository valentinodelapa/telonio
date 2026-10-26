<?php

namespace GestioneStudio\Application\Forms;

use GestioneStudio\Application\Entities\ProrogaAdempimento;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Proroga inserita dallo studio; studio, termine e anno sono impostati dal
 * controller.
 */
class ProrogaAdempimentoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return ProrogaAdempimento::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('data', FilterType::isDate)
                ->addFilterFieldMode('condizione', FilterType::isEnumeration)
                ->addFilterFieldMode('provvedimento', FilterType::isLimitString, [1, 255])
                ->addFilterFieldMode('note', FilterType::isString, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if ($this->entityData->data->format('Y-m-d') < $this->entity->termineAdempimento->getTermineOrdinario($this->entity->anno)->format('Y-m-d')) {
            $this->formFilterError->dataCustomMessage = 'La proroga non può anticipare il termine ordinario.';
            return false;
        }
        return true;
    }
}
