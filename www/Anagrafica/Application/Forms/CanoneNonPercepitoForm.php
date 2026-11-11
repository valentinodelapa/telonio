<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\CanoneNonPercepito;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Canoni non percepiti di un anno del contratto (impostato dal
 * controller): anno nel periodo del contratto, dal 2015 (prescrizione
 * decennale indicata dalle istruzioni), importo positivo, convalida dello
 * sfratto non anteriore all'anno dei canoni.
 */
class CanoneNonPercepitoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return CanoneNonPercepito::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['importo']) && is_string($this->request->input['importo'])) {
            $importo = trim($this->request->input['importo']);
            $this->addRequest('importo', str_contains($importo, ',') ? str_replace(',', '.', str_replace('.', '', $importo)) : $importo);
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('anno', FilterType::isInteger)
                ->addFilterFieldMode('importo', FilterType::isNumeric)
                ->addFilterFieldMode('dataConvalida', FilterType::isDate)
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
        $anno = $this->entityData->anno;
        if (($anno !== null) && (($anno < 2015) || ($anno < (int) $contratto->dataInizio->format('Y')) || (($contratto->dataFine !== null) && ($anno > (int) $contratto->dataFine->format('Y'))))) {
            $this->formFilterError->annoCustomMessage = 'Anno nel periodo del contratto, dal 2015.';
            $valido = false;
        }
        if (($this->entityData->importo !== null) && ($this->entityData->importo <= 0)) {
            $this->formFilterError->importoCustomMessage = 'Indica i canoni non percepiti nell\'anno.';
            $valido = false;
        }
        $convalida = $this->entityData->dataConvalida;
        if (($convalida !== null) && ($anno !== null) && ((int) $convalida->format('Y') < $anno)) {
            $this->formFilterError->dataConvalidaCustomMessage = 'La convalida dello sfratto non precede l\'anno dei canoni.';
            $valido = false;
        }
        return $valido;
    }
}
