<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\OpzioneTassazione;
use Anagrafica\Application\Enumerations\TipoOpzioneTassazione;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

class OpzioneTassazioneForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return OpzioneTassazione::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipoOpzione', FilterType::isEnumeration)
                ->addFilterFieldMode('consolidante', FilterType::isEntity, [], true)
                ->addFilterFieldMode('annoInizio', FilterType::isInteger)
                ->addFilterFieldMode('annoFine', FilterType::isInteger)
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
        $dati = $this->entityData;
        if (($dati->annoInizio < 2000) || ($dati->annoInizio > 2100)) {
            $this->formFilterError->annoInizioCustomMessage = 'Anno non valido.';
            $valido = false;
        }
        if ($dati->annoFine < $dati->annoInizio) {
            $this->formFilterError->annoFineCustomMessage = 'L\'anno di fine non può precedere quello di inizio.';
            $valido = false;
        }
        if ($dati->tipoOpzione === TipoOpzioneTassazione::consolidatoNazionale) {
            if ($dati->consolidante === null) {
                $this->formFilterError->consolidanteCustomMessage = 'Indicare la consolidante (la società stessa, se è la consolidante).';
                $valido = false;
            } elseif ($dati->consolidante->studio->id !== $this->entity->soggetto->studio->id) {
                $this->formFilterError->consolidanteCustomMessage = 'Soggetto non trovato.';
                $valido = false;
            } elseif ($dati->consolidante->isPersonaFisica()) {
                $this->formFilterError->consolidanteCustomMessage = 'La consolidante è una società o un ente.';
                $valido = false;
            }
        } elseif ($dati->consolidante !== null) {
            $this->formFilterError->consolidanteCustomMessage = 'La consolidante si indica solo per il consolidato.';
            $valido = false;
        }
        return $valido;
    }
}
