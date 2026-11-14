<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\Cespite;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Services\Ammortamenti;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Cespite del registro dei beni ammortizzabili; studio e soggetto sono
 * impostati dal controller.
 */
class CespiteForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Cespite::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['codice', 'descrizione', 'note'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim($this->request->input[$campo]));
            }
        }
        foreach (['costo', 'percentuale', 'fondoIniziale', 'prezzoDismissione', 'percentualeFiscale', 'percentualeDeducibile', 'limiteCostoFiscale'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza($this->request->input[$campo]));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codice', FilterType::isLimitString, [1, 20])
                ->addFilterFieldMode('descrizione', FilterType::isLimitString, [2, 255])
                ->addFilterFieldMode('contoCespite', FilterType::isEntity)
                ->addFilterFieldMode('contoFondo', FilterType::isEntity)
                ->addFilterFieldMode('contoAmmortamento', FilterType::isEntity)
                ->addFilterFieldMode('dataAcquisto', FilterType::isDate)
                ->addFilterFieldMode('dataEntrataFunzione', FilterType::isDate, [], true)
                ->addFilterFieldMode('costo', FilterType::isNumeric)
                ->addFilterFieldMode('percentuale', FilterType::isNumeric)
                ->addFilterFieldMode('riduzionePrimoEsercizio', FilterType::isBoolean)
                ->addFilterFieldMode('deduzioneIntegrale', FilterType::isBoolean)
                ->addFilterFieldMode('percentualeFiscale', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('percentualeDeducibile', FilterType::isNumeric)
                ->addFilterFieldMode('limiteCostoFiscale', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('fondoIniziale', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('esercizioFondoIniziale', FilterType::isInteger, [], true)
                ->addFilterFieldMode('dataDismissione', FilterType::isDate, [], true)
                ->addFilterFieldMode('prezzoDismissione', FilterType::isNumeric, [], true)
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
        $valido = true;
        $soggetto = $this->entity->soggetto;
        foreach (['contoCespite' => [NaturaConto::attivita, 'un\'immobilizzazione'], 'contoFondo' => [NaturaConto::attivita, 'un fondo ammortamento (attività)'],
            'contoAmmortamento' => [NaturaConto::costo, 'un conto di costo']] as $campo => [$natura, $descrizione]) {
            $conto = $dati->$campo ?? null;
            if (($conto !== null) && (($conto->pianoConti->soggetto?->id !== $soggetto->id) || ($conto->natura !== $natura) || ($conto->isMovimentabile() === false))) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Scegliere ' . $descrizione . ' del piano dei conti dell\'azienda.';
                $valido = false;
            }
        }
        if (($dati->costo ?? 0) <= 0) {
            $this->formFilterError->costoCustomMessage = 'Il costo deve essere positivo.';
            $valido = false;
        }
        if ((($dati->percentuale ?? 0) <= 0) || ($dati->percentuale > 100)) {
            $this->formFilterError->percentualeCustomMessage = 'Coefficiente tra 0 e 100.';
            $valido = false;
        }
        foreach (['percentualeFiscale', 'percentualeDeducibile'] as $campo) {
            if ((($dati->$campo ?? null) !== null) && (($dati->$campo < 0) || ($dati->$campo > 100))) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Percentuale tra 0 e 100.';
                $valido = false;
            }
        }
        if ((($dati->limiteCostoFiscale ?? null) !== null) && ($dati->limiteCostoFiscale <= 0)) {
            $this->formFilterError->limiteCostoFiscaleCustomMessage = 'Il limite deve essere positivo.';
            $valido = false;
        }
        if (($dati->deduzioneIntegrale ?? false) && (($dati->costo ?? 0) > Ammortamenti::SOGLIA_BENI_MINORI)) {
            $this->formFilterError->deduzioneIntegraleCustomMessage = 'Solo per i beni di costo unitario fino a 516,46 euro.';
            $valido = false;
        }
        if ((($dati->fondoIniziale ?? 0) > 0) !== (($dati->esercizioFondoIniziale ?? null) !== null)) {
            $this->formFilterError->esercizioFondoInizialeCustomMessage = 'Fondo iniziale ed esercizio si indicano insieme.';
            $valido = false;
        } elseif (($dati->fondoIniziale ?? 0) > ($dati->costo ?? 0)) {
            $this->formFilterError->fondoInizialeCustomMessage = 'Il fondo non può superare il costo.';
            $valido = false;
        }
        $inizio = ($dati->dataEntrataFunzione ?? null) ?? ($dati->dataAcquisto ?? null);
        if (($dati->dataEntrataFunzione ?? null) !== null && ($dati->dataAcquisto ?? null) !== null && ($dati->dataEntrataFunzione->format('Y-m-d') < $dati->dataAcquisto->format('Y-m-d'))) {
            $this->formFilterError->dataEntrataFunzioneCustomMessage = 'L\'entrata in funzione non precede l\'acquisto.';
            $valido = false;
        }
        if ((($dati->dataDismissione ?? null) !== null) && ($inizio !== null) && ($dati->dataDismissione->format('Y-m-d') < $inizio->format('Y-m-d'))) {
            $this->formFilterError->dataDismissioneCustomMessage = 'La dismissione non precede l\'acquisto.';
            $valido = false;
        }
        if ((($dati->prezzoDismissione ?? null) !== null) && (($dati->dataDismissione ?? null) === null)) {
            $this->formFilterError->dataDismissioneCustomMessage = 'Indicare la data della dismissione.';
            $valido = false;
        }
        return $valido;
    }
}
