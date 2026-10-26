<?php

namespace GestioneStudio\Application\Forms;

use GestioneStudio\Application\Entities\TermineAdempimento;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Termine di un adempimento dello studio; l'adempimento è impostato dal
 * controller.
 */
class TermineAdempimentoForm extends BaseForm
{

    private const GIORNI_MESE = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    #[\Override]
    protected static function getEntityName(): string
    {
        return TermineAdempimento::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('ordine', FilterType::isInteger)
                ->addFilterFieldMode('periodo', FilterType::isLimitString, [1, 50])
                ->addFilterFieldMode('mese', FilterType::isInteger)
                ->addFilterFieldMode('giorno', FilterType::isInteger)
                ->addFilterFieldMode('anniSuccessivi', FilterType::isInteger);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        $valido = true;
        if (($dati->ordine < 1) || ($dati->ordine > 99)) {
            $this->formFilterError->ordineCustomMessage = 'Ordine da 1 a 99.';
            $valido = false;
        }
        if (($dati->mese < 1) || ($dati->mese > 12)) {
            $this->formFilterError->meseCustomMessage = 'Mese da 1 a 12.';
            return false;
        }
        if (($dati->giorno < 0) || ($dati->giorno > self::GIORNI_MESE[$dati->mese - 1])) {
            $this->formFilterError->giornoCustomMessage = 'Giorno non valido per il mese (0 = ultimo giorno del mese).';
            $valido = false;
        }
        if (($dati->anniSuccessivi < 0) || ($dati->anniSuccessivi > 5)) {
            $this->formFilterError->anniSuccessiviCustomMessage = 'Da 0 a 5 anni dopo l\'anno di riferimento.';
            $valido = false;
        }
        return $valido;
    }
}
