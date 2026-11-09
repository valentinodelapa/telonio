<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\PertinenzaLocata;
use Anagrafica\Application\Enumerations\TipoCatasto;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Pertinenza locata insieme all'immobile del contratto: un fabbricato
 * dello stesso studio, diverso dall'immobile principale.
 */
class PertinenzaLocataForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return PertinenzaLocata::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('immobile', FilterType::isEntity);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $immobile = $this->entityData->immobile;
        $contratto = $this->entity->contrattoLocazione;
        $messaggio = match (true) {
            $immobile === null => null,
            $immobile->studio->id !== $contratto->immobile->studio->id => 'Elemento non trovato nell\'archivio dello studio.',
            $immobile->id === $contratto->immobile->id => 'È l\'immobile del contratto.',
            $immobile->tipoCatasto !== TipoCatasto::fabbricati => 'La pertinenza è un fabbricato.',
            default => null,
        };
        if ($messaggio !== null) {
            $this->formFilterError->immobileCustomMessage = $messaggio;
            return false;
        }
        return true;
    }
}
