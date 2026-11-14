<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\RateoRisconto;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Costo o ricavo a cavallo dell'esercizio per ratei e risconti; studio,
 * soggetto ed esercizio sono impostati dal controller.
 */
class RateoRiscontoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return RateoRisconto::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['descrizione']) && is_string($this->request->input['descrizione'])) {
            $this->addRequest('descrizione', trim($this->request->input['descrizione']));
        }
        if (isset($this->request->input['importo']) && is_string($this->request->input['importo'])) {
            $this->addRequest('importo', Importo::normalizza($this->request->input['importo']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('conto', FilterType::isEntity)
                ->addFilterFieldMode('descrizione', FilterType::isLimitString, [2, 255])
                ->addFilterFieldMode('importo', FilterType::isNumeric)
                ->addFilterFieldMode('competenzaDal', FilterType::isDate)
                ->addFilterFieldMode('competenzaAl', FilterType::isDate)
                ->addFilterFieldMode('registratoNellEsercizio', FilterType::isBoolean);
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
        $conto = $dati->conto ?? null;
        if (($conto !== null) && (($conto->pianoConti->soggetto?->id !== $this->entity->soggetto->id) || ($conto->natura->isEconomico() === false) || ($conto->isMovimentabile() === false))) {
            $this->formFilterError->contoCustomMessage = 'Scegliere un conto di costo o di ricavo del piano dell\'azienda.';
            $valido = false;
        }
        if (($dati->importo ?? 0) <= 0) {
            $this->formFilterError->importoCustomMessage = 'L\'importo deve essere positivo.';
            $valido = false;
        }
        if ((($dati->competenzaDal ?? null) !== null) && (($dati->competenzaAl ?? null) !== null) && ($dati->competenzaAl->format('Y-m-d') < $dati->competenzaDal->format('Y-m-d'))) {
            $this->formFilterError->competenzaAlCustomMessage = 'La fine della competenza non precede l\'inizio.';
            $valido = false;
        }
        return $valido;
    }
}
