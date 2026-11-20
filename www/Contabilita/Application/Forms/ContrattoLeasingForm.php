<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\ContrattoLeasing;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Contratto di leasing dell'azienda; studio e soggetto sono impostati dal
 * controller.
 */
class ContrattoLeasingForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return ContrattoLeasing::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['descrizione', 'concedente', 'numeroContratto', 'note'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim($this->request->input[$campo]));
            }
        }
        foreach (['costoConcedente', 'totaleCanoni', 'prezzoRiscatto'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $valore = trim($this->request->input[$campo]);
                $this->addRequest($campo, $valore === '' ? '0' : Importo::normalizza($valore));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('descrizione', FilterType::isLimitString, [2, 255])
                ->addFilterFieldMode('concedente', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('numeroContratto', FilterType::isMaxLimitString, [40], true)
                ->addFilterFieldMode('dataInizio', FilterType::isDate)
                ->addFilterFieldMode('dataFine', FilterType::isDate)
                ->addFilterFieldMode('dataRiscatto', FilterType::isDate, [], true)
                ->addFilterFieldMode('costoConcedente', FilterType::isNumeric)
                ->addFilterFieldMode('totaleCanoni', FilterType::isNumeric)
                ->addFilterFieldMode('prezzoRiscatto', FilterType::isNumeric)
                ->addFilterFieldMode('beneImmobile', FilterType::isBoolean)
                ->addFilterFieldMode('immobile', FilterType::isEntity, [], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        if ($dati->dataFine->format('Y-m-d') <= $dati->dataInizio->format('Y-m-d')) {
            $this->formFilterError->dataFineCustomMessage = 'La scadenza deve seguire l\'inizio del contratto.';
            return false;
        }
        if (($dati->dataRiscatto !== null) && (($dati->dataRiscatto->format('Y-m-d') < $dati->dataInizio->format('Y-m-d')) || ($dati->dataRiscatto->format('Y-m-d') > $dati->dataFine->format('Y-m-d')))) {
            $this->formFilterError->dataRiscattoCustomMessage = 'Il riscatto va tra l\'inizio e la scadenza del contratto.';
            return false;
        }
        foreach (['costoConcedente', 'totaleCanoni', 'prezzoRiscatto'] as $campo) {
            if ((float) $dati->$campo < 0) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'L\'importo non può essere negativo.';
                return false;
            }
        }
        if ((float) $dati->costoConcedente <= 0) {
            $this->formFilterError->costoConcedenteCustomMessage = 'Indicare il costo sostenuto dalla società concedente.';
            return false;
        }
        if (($dati->immobile !== null) && ($dati->immobile->studio->id !== $this->entity->soggetto->studio->id)) {
            $this->formFilterError->immobileCustomMessage = 'Immobile di un altro studio.';
            return false;
        }
        return true;
    }
}
