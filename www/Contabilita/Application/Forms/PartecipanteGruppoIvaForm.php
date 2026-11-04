<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\PartecipanteGruppoIva;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Società partecipante alla liquidazione IVA di gruppo; il gruppo è
 * impostato dal controller.
 */
class PartecipanteGruppoIvaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return PartecipanteGruppoIva::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['percentualePossesso']) && is_string($this->request->input['percentualePossesso'])) {
            $this->addRequest('percentualePossesso', str_replace(',', '.', trim($this->request->input['percentualePossesso'])));
        }
        foreach (['detentoreEstero', 'soloCatena'] as $casella) {
            $this->addRequest($casella, isset($this->request->input[$casella]) ? '1' : '0');
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('soggetto', FilterType::isEntity)
                ->addFilterFieldMode('annoIngresso', FilterType::isInteger)
                ->addFilterFieldMode('annoUscita', FilterType::isInteger, [], true)
                ->addFilterFieldMode('dataCessazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('dataPossesso', FilterType::isDate, [], true)
                ->addFilterFieldMode('percentualePossesso', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('detentore', FilterType::isEntity, [], true)
                ->addFilterFieldMode('detentoreEstero', FilterType::isBoolean)
                ->addFilterFieldMode('soloCatena', FilterType::isBoolean);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if ($this->entityData->soggetto->isPersonaFisica()) {
            $this->formFilterError->soggettoCustomMessage = 'Partecipano solo società ed enti commerciali.';
            return false;
        }
        if (($this->entityData->annoUscita !== null) && ($this->entityData->annoUscita <= $this->entityData->annoIngresso)) {
            $this->formFilterError->annoUscitaCustomMessage = 'L\'uscita ha effetto da un anno successivo all\'ingresso.';
            return false;
        }
        if (($this->entityData->percentualePossesso !== null) && ($this->entityData->percentualePossesso <= 50)) {
            $this->formFilterError->percentualePossessoCustomMessage = 'Il controllo richiede più del 50% del capitale (art. 73, c. 3).';
            return false;
        }
        return true;
    }
}
