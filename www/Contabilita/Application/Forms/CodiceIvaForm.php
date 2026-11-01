<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Models\CodiceIvaModel;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Codice IVA dello studio; lo studio è impostato dal controller.
 */
class CodiceIvaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return CodiceIva::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['codice']) && is_string($this->request->input['codice'])) {
            $this->addRequest('codice', strtoupper(trim($this->request->input['codice'])));
        }
        foreach (['aliquota', 'detraibilita'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza($this->request->input[$campo]));
            }
        }
        $this->addRequest('inversioneContabile', isset($this->request->input['inversioneContabile']) ? '1' : '0');
        $this->addRequest('ventilazione', isset($this->request->input['ventilazione']) ? '1' : '0');
        $this->addRequest('attivo', isset($this->request->input['attivo']) ? '1' : '0');
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codice', FilterType::isLimitString, [1, 10])
                ->addFilterFieldMode('descrizione', FilterType::isLimitString, [2, 150])
                ->addFilterFieldMode('aliquota', FilterType::isNumeric)
                ->addFilterFieldMode('natura', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('detraibilita', FilterType::isNumeric)
                ->addFilterFieldMode('inversioneContabile', FilterType::isBoolean)
                ->addFilterFieldMode('ventilazione', FilterType::isBoolean)
                ->addFilterFieldMode('norma', FilterType::isMaxLimitString, [255], true)
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
        $dati = $this->entityData;
        if ((new CodiceIvaModel())->esisteCodice($this->entity->studio, (string) $dati->codice, $this->entity)) {
            $this->formFilterError->codiceCustomMessage = 'Codice già presente (della suite o dello studio).';
            $valido = false;
        }
        if (($dati->aliquota < 0) || ($dati->aliquota > 100)) {
            $this->formFilterError->aliquotaCustomMessage = 'Aliquota tra 0 e 100.';
            $valido = false;
        } elseif ($dati->ventilazione) {
            if (($dati->aliquota > 0) || (($dati->natura ?? null) !== null) || $dati->inversioneContabile) {
                $this->formFilterError->ventilazioneCustomMessage = 'Il codice di ventilazione non ha aliquota, natura né integrazione: l\'aliquota si attribuisce nella liquidazione.';
                $valido = false;
            }
        } elseif ((($dati->natura ?? null) !== null) === ($dati->aliquota > 0)) {
            $this->formFilterError->naturaCustomMessage = 'Indicare l\'aliquota oppure, per le operazioni senza imposta, la natura.';
            $valido = false;
        }
        if (($dati->detraibilita < 0) || ($dati->detraibilita > 100)) {
            $this->formFilterError->detraibilitaCustomMessage = 'Detraibilità tra 0 e 100.';
            $valido = false;
        }
        if ($dati->inversioneContabile && (($dati->natura ?? null) !== null)) {
            $this->formFilterError->inversioneContabileCustomMessage = 'L\'integrazione dell\'imposta richiede un\'aliquota.';
            $valido = false;
        }
        return $valido;
    }
}
