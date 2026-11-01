<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\EsercizioContabile;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Date dell'esercizio contabile; studio, soggetto e anno sono impostati
 * dal servizio di apertura.
 */
class EsercizioContabileForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return EsercizioContabile::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('dataInizio', FilterType::isDate)
                ->addFilterFieldMode('dataFine', FilterType::isDate);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if (isset($this->entityData->dataInizio, $this->entityData->dataFine) && ($this->entityData->dataFine->format('Y-m-d') < $this->entityData->dataInizio->format('Y-m-d'))) {
            $this->formFilterError->dataFineCustomMessage = 'La fine dell\'esercizio precede l\'inizio.';
            return false;
        }
        return true;
    }
}
