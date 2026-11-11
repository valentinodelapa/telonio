<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Services\RighiRedditi;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Models\ContoModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Conto del piano; il piano è impostato dal controller. Il livello e il
 * conto superiore si ricavano dal codice (NN, NN.NN, NN.NN.NNNN), che non
 * si modifica dopo la creazione; la funzione è unica nel piano e ammessa
 * solo al suo livello, la voce CEE deve essere della sezione della
 * natura.
 */
class ContoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Conto::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['codice', 'descrizione'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim($this->request->input[$campo]));
            }
        }
        $this->addRequest('attivo', isset($this->request->input['attivo']) ? '1' : '0');
        if (isset($this->request->input['percentualeDeducibile']) && is_string($this->request->input['percentualeDeducibile'])) {
            $this->addRequest('percentualeDeducibile', str_replace(',', '.', trim($this->request->input['percentualeDeducibile'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        if (isset($this->entity->id) === false) {
            $this->addFilterFieldMode('codice', FilterType::isLimitString, [2, 20]);
        }
        $this->addFilterFieldMode('descrizione', FilterType::isLimitString, [2, 150])
                ->addFilterFieldMode('natura', FilterType::isEnumeration)
                ->addFilterFieldMode('funzione', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('voceCee', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('rigoRedditi', FilterType::isMaxLimitString, [10], true)
                ->addFilterFieldMode('percentualeDeducibile', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('variazioneRedditi', FilterType::isMaxLimitString, [10], true)
                ->addFilterFieldMode('attivo', FilterType::isBoolean);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        if (RighiRedditi::isValido($this->entityData->rigoRedditi ?? null) === false) {
            $this->formFilterError->rigoRedditiCustomMessage = 'Rigo della dichiarazione non previsto.';
            $valido = false;
        }
        $percentuale = $this->entityData->percentualeDeducibile ?? null;
        $variazione = $this->entityData->variazioneRedditi ?? null;
        if (($percentuale !== null) && (($percentuale < 0) || ($percentuale >= 100))) {
            $this->formFilterError->percentualeDeducibileCustomMessage = 'Percentuale da 0 a meno di 100 (vuota se il costo è interamente deducibile).';
            $valido = false;
        }
        if (($variazione !== null) && ($variazione !== '') && ((isset(RighiRedditi::VARIAZIONI[$variazione]) === false) || ($percentuale === null))) {
            $this->formFilterError->variazioneRedditiCustomMessage = 'Rigo della variazione non previsto o senza la percentuale deducibile.';
            $valido = false;
        }
        $natura = $this->entityData->natura ?? null;
        if (($percentuale !== null) && ($natura instanceof NaturaConto) && (in_array($natura, [NaturaConto::costo, NaturaConto::ricavo], true) === false)) {
            $this->formFilterError->percentualeDeducibileCustomMessage = 'La percentuale riguarda i conti di costo (parte deducibile) e di ricavo (parte imponibile).';
            $valido = false;
        }
        if (($variazione !== null) && ($variazione !== '') && ($natura instanceof NaturaConto)
                && (($variazione === RighiRedditi::VARIAZIONE_DIMINUZIONE) !== ($natura === NaturaConto::ricavo))) {
            $this->formFilterError->variazioneRedditiCustomMessage = $natura === NaturaConto::ricavo ? 'Per i ricavi la variazione è in diminuzione (RF55).' : 'Per i costi la variazione è in aumento (RF16, RF18 o RF31).';
            $valido = false;
        }
        $piano = $this->entity->pianoConti;
        $model = new ContoModel();
        $codice = $this->entityData->codice ?? $this->entity->codice ?? '';
        $livello = LivelloConto::daCodice((string) $codice);
        if ($livello === null) {
            $this->formFilterError->codiceCustomMessage = 'Codice nella forma NN (mastro), NN.NN (conto) o NN.NN.NNNN (sottoconto).';
            return false;
        }
        if (isset($this->entity->id) === false) {
            $padre = LivelloConto::codicePadre($codice);
            if (($padre !== null) && ($model->getPerCodice($piano, $padre) === null)) {
                $this->formFilterError->codiceCustomMessage = 'Il conto superiore ' . $padre . ' non esiste nel piano.';
                $valido = false;
            } elseif ($model->getPerCodice($piano, $codice) !== null) {
                $this->formFilterError->codiceCustomMessage = 'Codice già presente nel piano.';
                $valido = false;
            }
        }
        $funzione = $this->entityData->funzione ?? null;
        if ($funzione !== null) {
            $assegnato = $model->getPerFunzione($piano, $funzione);
            if ($funzione->getLivello() !== $livello) {
                $this->formFilterError->funzioneCustomMessage = 'La funzione si assegna a un ' . mb_strtolower($funzione->getLivello()->getLabel()) . '.';
                $valido = false;
            } elseif (($assegnato !== null) && ((isset($this->entity->id) === false) || ($assegnato->id !== $this->entity->id))) {
                $this->formFilterError->funzioneCustomMessage = 'Funzione già assegnata al conto ' . $assegnato->getEtichetta() . '.';
                $valido = false;
            }
        }
        $voce = $this->entityData->voceCee ?? null;
        if (($voce !== null) && isset($this->entityData->natura) && ($voce->isCompatibile($this->entityData->natura) === false)) {
            $this->formFilterError->voceCeeCustomMessage = 'La voce appartiene allo schema ' . mb_strtolower($voce->getSezione()) . ', non compatibile con la natura del conto.';
            $valido = false;
        }
        return $valido;
    }
}
