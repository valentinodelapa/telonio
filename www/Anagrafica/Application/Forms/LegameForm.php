<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Enumerations\RuoloLegame;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

class LegameForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Legame::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['quotaPartecipazione']) && is_string($this->request->input['quotaPartecipazione'])) {
            $this->addRequest('quotaPartecipazione', str_replace(',', '.', trim($this->request->input['quotaPartecipazione'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('soggettoCollegato', FilterType::isEntity)
                ->addFilterFieldMode('ruolo', FilterType::isEnumeration)
                ->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('quotaPartecipazione', FilterType::isNumeric, [], true)
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
        $collegato = $this->entityData->soggettoCollegato;
        if ($collegato !== null) {
            if ($collegato->studio->id !== $this->entity->soggetto->studio->id) {
                $this->formFilterError->soggettoCollegatoCustomMessage = 'Soggetto non trovato.';
                $valido = false;
            } elseif ($collegato->id === $this->entity->soggetto->id) {
                $this->formFilterError->soggettoCollegatoCustomMessage = 'Un soggetto non può essere collegato a sé stesso.';
                $valido = false;
            }
        }
        if (($this->entityData->ruolo === RuoloLegame::collaboratoreFamiliare) && ($this->entity->soggetto->isPersonaFisica() === false)) {
            $this->formFilterError->ruoloCustomMessage = 'Il collaboratore familiare è previsto solo per l\'impresa familiare di una persona fisica.';
            $valido = false;
        }
        $quota = $this->entityData->quotaPartecipazione;
        if ($quota !== null) {
            if (($this->entityData->ruolo !== null) && ($this->entityData->ruolo->prevedeQuota() === false)) {
                $this->formFilterError->quotaPartecipazioneCustomMessage = 'La quota è prevista solo per soci, eredi e collaboratori familiari.';
                $valido = false;
            } elseif (($quota <= 0) || ($quota > 100)) {
                $this->formFilterError->quotaPartecipazioneCustomMessage = 'La quota deve essere una percentuale maggiore di 0 e fino a 100.';
                $valido = false;
            }
        }
        return $valido;
    }
}
