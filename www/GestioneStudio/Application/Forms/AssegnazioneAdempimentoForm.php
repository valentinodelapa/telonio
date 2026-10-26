<?php

namespace GestioneStudio\Application\Forms;

use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Versione dell'assegnazione di un adempimento al soggetto; studio e
 * soggetto sono impostati dal controller, l'adempimento si sceglie solo
 * nella prima assegnazione.
 */
class AssegnazioneAdempimentoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return AssegnazioneAdempimento::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        if (isset($this->entity->adempimento) === false) {
            $this->addFilterFieldMode('adempimento', FilterType::isEntity);
        }
        $this->addFilterFieldMode('responsabile', FilterType::isEntity, [], true)
                ->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $studio = $this->entity->studio;
        $adempimento = $this->entityData->adempimento ?? $this->entity->adempimento;
        if (($adempimento->studio !== null) && ($adempimento->studio->id !== $studio->id)) {
            $this->formFilterError->adempimentoCustomMessage = 'Adempimento non disponibile.';
            return false;
        }
        $responsabile = $this->entityData->responsabile;
        if (($responsabile !== null) && ($responsabile->studio->id !== $studio->id)) {
            $this->formFilterError->responsabileCustomMessage = 'Il responsabile deve essere un operatore dello studio.';
            return false;
        }
        return true;
    }
}
