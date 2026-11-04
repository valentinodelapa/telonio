<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\GruppoIva;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Opzione per la liquidazione IVA di gruppo; studio e controllante sono
 * impostati dal controller.
 */
class GruppoIvaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return GruppoIva::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['percentualeMaggioranza']) && is_string($this->request->input['percentualeMaggioranza'])) {
            $this->addRequest('percentualeMaggioranza', str_replace(',', '.', trim($this->request->input['percentualeMaggioranza'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('annoInizio', FilterType::isInteger)
                ->addFilterFieldMode('annoRevoca', FilterType::isInteger, [], true)
                ->addFilterFieldMode('maggioranza', FilterType::isEntity, [], true)
                ->addFilterFieldMode('dataPossessoMaggioranza', FilterType::isDate, [], true)
                ->addFilterFieldMode('percentualeMaggioranza', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if (($this->entityData->annoRevoca !== null) && ($this->entityData->annoRevoca <= $this->entityData->annoInizio)) {
            $this->formFilterError->annoRevocaCustomMessage = 'La revoca ha effetto da un anno successivo all\'inizio.';
            return false;
        }
        if (($this->entityData->maggioranza !== null) && (($this->entityData->percentualeMaggioranza === null) || ($this->entityData->percentualeMaggioranza <= 50) || ($this->entityData->dataPossessoMaggioranza === null))) {
            $this->formFilterError->percentualeMaggioranzaCustomMessage = 'Per il soggetto che possiede la maggioranza della controllante (VG1) indicare la data di inizio del possesso e una percentuale superiore al 50%.';
            return false;
        }
        return true;
    }
}
