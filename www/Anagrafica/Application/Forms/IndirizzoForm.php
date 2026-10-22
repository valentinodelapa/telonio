<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Models\NazioneModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

class IndirizzoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Indirizzo::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (empty($this->request->input['nazione'])) {
            $this->addRequest('nazione', (new NazioneModel())->getItalia()->id);
        }
        foreach (['presso', 'indirizzo', 'numeroCivico', 'frazione', 'cap', 'localitaEstera'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim(preg_replace('/\s+/', ' ', $this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipoIndirizzo', FilterType::isEnumeration)
                ->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('presso', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('indirizzo', FilterType::isMaxLimitString, [150])
                ->addFilterFieldMode('numeroCivico', FilterType::isMaxLimitString, [20], true)
                ->addFilterFieldMode('frazione', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('cap', FilterType::isMaxLimitString, [10], true)
                ->addFilterFieldMode('comune', FilterType::isEntity, [], true)
                ->addFilterFieldMode('localitaEstera', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('nazione', FilterType::isEntity);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $tipoIndirizzo = $this->entityData->tipoIndirizzo;
        if (($tipoIndirizzo !== null) && ($tipoIndirizzo->isApplicabile($this->entity->soggetto->tipoSoggetto) === false)) {
            $this->formFilterError->tipoIndirizzoCustomMessage = 'Tipo di indirizzo non previsto per questo soggetto.';
            $valido = false;
        }
        $nazione = $this->entityData->nazione;
        if (($nazione !== null) && $nazione->isItalia()) {
            if ($this->entityData->comune === null) {
                $this->formFilterError->comuneCustomMessage = 'Per gli indirizzi in Italia indicare il comune.';
                $valido = false;
            }
            if (($this->entityData->cap !== null) && (preg_match('/^\d{5}$/', $this->entityData->cap) !== 1)) {
                $this->formFilterError->capCustomMessage = 'Il CAP italiano è composto da 5 cifre.';
                $valido = false;
            }
        } elseif ($nazione !== null) {
            if ($this->entityData->comune !== null) {
                $this->formFilterError->comuneCustomMessage = 'Per gli indirizzi esteri indicare la località, non il comune.';
                $valido = false;
            }
            if ($this->entityData->localitaEstera === null) {
                $this->formFilterError->localitaEsteraCustomMessage = 'Indicare la località estera.';
                $valido = false;
            }
        }
        return $valido;
    }
}
