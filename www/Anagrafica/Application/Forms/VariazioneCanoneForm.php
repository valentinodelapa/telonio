<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\VariazioneCanone;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Nuovo canone annuo dalla data. Il contratto è impostato dal controller.
 */
class VariazioneCanoneForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return VariazioneCanone::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['canoneAnnuo']) && is_string($this->request->input['canoneAnnuo'])) {
            $canone = trim($this->request->input['canoneAnnuo']);
            $this->addRequest('canoneAnnuo', str_contains($canone, ',') ? str_replace(',', '.', str_replace('.', '', $canone)) : $canone);
        }
        $this->addRequest('rinegoziazione', ($this->request->input['rinegoziazione'] ?? '') === '1' ? '1' : '0');
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('canoneAnnuo', FilterType::isNumeric)
                ->addFilterFieldMode('rinegoziazione', FilterType::isBoolean)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $contratto = $this->entity->contrattoLocazione;
        $dal = $this->entityData->validoDal;
        if (($dal !== null) && ($dal->format('Y-m-d') <= $contratto->dataInizio->format('Y-m-d'))) {
            $this->formFilterError->validoDalCustomMessage = 'La variazione decorre dopo l\'inizio del contratto.';
            $valido = false;
        }
        if (($dal !== null) && ($contratto->dataFine !== null) && ($dal->format('Y-m-d') > $contratto->dataFine->format('Y-m-d'))) {
            $this->formFilterError->validoDalCustomMessage = 'La variazione decorre dopo la fine del contratto.';
            $valido = false;
        }
        if (($this->entityData->canoneAnnuo !== null) && ($this->entityData->canoneAnnuo < 0)) {
            $this->formFilterError->canoneAnnuoCustomMessage = 'Il canone non può essere negativo.';
            $valido = false;
        }
        return $valido;
    }
}
