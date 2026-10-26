<?php

namespace GestioneStudio\Application\Forms;

use GestioneStudio\Application\Entities\LavorazioneScadenza;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Stato di lavorazione, responsabile e note di una scadenza; i dati della
 * scadenza sono impostati dallo scadenzario.
 */
class LavorazioneScadenzaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return LavorazioneScadenza::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('stato', FilterType::isEnumeration)
                ->addFilterFieldMode('responsabile', FilterType::isEntity, [], true)
                ->addFilterFieldMode('note', FilterType::isString, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $responsabile = $this->entityData->responsabile;
        if (($responsabile !== null) && ($responsabile->studio->id !== $this->entity->studio->id)) {
            $this->formFilterError->responsabileCustomMessage = 'Il responsabile deve essere un operatore dello studio.';
            return false;
        }
        return true;
    }
}
