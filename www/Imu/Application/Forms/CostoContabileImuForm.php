<?php

namespace Imu\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\CostoContabileImu;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Costo contabile di un fabbricato a valore contabile; l'immobile è
 * impostato dal controller.
 */
class CostoContabileImuForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return CostoContabileImu::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['importo']) && is_string($this->request->input['importo'])) {
            $this->addRequest('importo', Importo::normalizza($this->request->input['importo']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('anno', FilterType::isInteger)
                ->addFilterFieldMode('importo', FilterType::isNumeric)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [150], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if (($this->entityData->anno < 1900) || ($this->entityData->anno > (int) date('Y') + 1)) {
            $this->formFilterError->annoCustomMessage = 'Anno di sostenimento non valido.';
            return false;
        }
        return true;
    }
}
