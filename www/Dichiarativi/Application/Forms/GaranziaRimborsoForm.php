<?php

namespace Dichiarativi\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Dichiarativi\Application\Entities\GaranziaRimborso;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Garanzia di un rimborso IVA: studio e contribuente sono impostati dal
 * controller, la scadenza vuota è proposta dalla data del rimborso.
 */
class GaranziaRimborsoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return GaranziaRimborso::class;
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
        $this->addFilterFieldMode('annoImposta', FilterType::isInteger)
                ->addFilterFieldMode('tipo', FilterType::isEnumeration)
                ->addFilterFieldMode('garante', FilterType::isMaxLimitString, [120], true)
                ->addFilterFieldMode('estremi', FilterType::isMaxLimitString, [60], true)
                ->addFilterFieldMode('importo', FilterType::isNumeric)
                ->addFilterFieldMode('dataRilascio', FilterType::isDate)
                ->addFilterFieldMode('dataRimborso', FilterType::isDate, [], true)
                ->addFilterFieldMode('dataScadenza', FilterType::isDate, [], true)
                ->addFilterFieldMode('dataSvincolo', FilterType::isDate, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if ($this->entityData->importo <= 0) {
            $this->formFilterError->importoCustomMessage = 'Indicare l\'importo garantito.';
            return false;
        }
        $rilascio = $this->entityData->dataRilascio->format('Y-m-d');
        foreach (['dataScadenza' => 'La scadenza', 'dataSvincolo' => 'Lo svincolo'] as $campo => $etichetta) {
            if (($this->entityData->$campo !== null) && ($this->entityData->$campo->format('Y-m-d') < $rilascio)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = $etichetta . ' non può precedere il rilascio.';
                return false;
            }
        }
        return true;
    }
}
