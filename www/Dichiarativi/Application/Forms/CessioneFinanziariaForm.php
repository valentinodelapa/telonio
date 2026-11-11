<?php

namespace Dichiarativi\Application\Forms;

use Dichiarativi\Application\Entities\CessioneFinanziaria;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Cessione di titoli, partecipazioni non qualificate o cripto-attività
 * (soggetto e anno impostati dal controller): data nell'anno d'imposta,
 * importi non negativi, detenzione dal 2024 o prima solo per le
 * cripto-attività.
 */
class CessioneFinanziariaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return CessioneFinanziaria::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['corrispettivo', 'costo'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $valore = trim($this->request->input[$campo]);
                $this->addRequest($campo, str_contains($valore, ',') ? str_replace(',', '.', str_replace('.', '', $valore)) : $valore);
            }
        }
        foreach (['costoRideterminato', 'paesePrivilegiato', 'anteriore2025'] as $campo) {
            $this->addRequest($campo, ($this->request->input[$campo] ?? '') === '1' ? '1' : '0');
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipo', FilterType::isMaxLimitString, [1])
                ->addFilterFieldMode('dataCessione', FilterType::isDate)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [150])
                ->addFilterFieldMode('corrispettivo', FilterType::isNumeric)
                ->addFilterFieldMode('costo', FilterType::isNumeric)
                ->addFilterFieldMode('costoRideterminato', FilterType::isBoolean)
                ->addFilterFieldMode('paesePrivilegiato', FilterType::isBoolean)
                ->addFilterFieldMode('anteriore2025', FilterType::isBoolean)
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
        if (isset(CessioneFinanziaria::TIPI[(string) $dati->tipo]) === false) {
            $this->formFilterError->tipoCustomMessage = 'Tipo di cessione non previsto.';
            $valido = false;
        }
        if (($dati->dataCessione !== null) && ((int) $dati->dataCessione->format('Y') !== $this->entity->annoImposta)) {
            $this->formFilterError->dataCessioneCustomMessage = 'Data nell\'anno d\'imposta ' . $this->entity->annoImposta . '.';
            $valido = false;
        }
        foreach (['corrispettivo', 'costo'] as $campo) {
            if (($dati->$campo !== null) && ($dati->$campo < 0)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Importo non negativo.';
                $valido = false;
            }
        }
        if ($dati->anteriore2025 && ($dati->tipo !== CessioneFinanziaria::CRIPTO)) {
            $this->formFilterError->anteriore2025CustomMessage = 'Solo per le cripto-attività.';
            $valido = false;
        }
        if ($dati->paesePrivilegiato && ($dati->tipo !== CessioneFinanziaria::TITOLI)) {
            $this->formFilterError->paesePrivilegiatoCustomMessage = 'Solo per le partecipazioni (rigo RT16).';
            $valido = false;
        }
        return $valido;
    }
}
