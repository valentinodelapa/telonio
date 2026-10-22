<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Models\ImmobileModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Identificativo catastale e ubicazione dell'immobile. Studio e tipo di
 * catasto sono impostati dal controller sull'entità e non sono modificabili.
 */
class ImmobileForm extends BaseForm
{

    private bool $consentiEsistente = false;

    #[\Override]
    protected static function getEntityName(): string
    {
        return Immobile::class;
    }

    /**
     * In inserimento da un soggetto, un immobile già presente nell'archivio
     * dello studio non è un errore: il controller lo riusa.
     */
    public function consentiImmobileEsistente(): self
    {
        $this->consentiEsistente = true;
        return $this;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['sezione', 'foglio', 'particella', 'subalterno', 'numeroCivico', 'piano', 'interno', 'partitaTavolare', 'corpoTavolare', 'porzioneMateriale'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(preg_replace('/\s+/', '', $this->request->input[$campo])));
            }
        }
        foreach (['indirizzo', 'descrizione', 'comuneCatastale'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim(preg_replace('/\s+/', ' ', $this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('comune', FilterType::isEntity)
                ->addFilterFieldMode('sezione', FilterType::isMaxLimitString, [5], true)
                ->addFilterFieldMode('foglio', FilterType::isMaxLimitString, [10])
                ->addFilterFieldMode('particella', FilterType::isMaxLimitString, [10])
                ->addFilterFieldMode('subalterno', FilterType::isMaxLimitString, [10], true)
                ->addFilterFieldMode('indirizzo', FilterType::isMaxLimitString, [150], true)
                ->addFilterFieldMode('numeroCivico', FilterType::isMaxLimitString, [20], true)
                ->addFilterFieldMode('piano', FilterType::isMaxLimitString, [10], true)
                ->addFilterFieldMode('interno', FilterType::isMaxLimitString, [10], true)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('partitaTavolare', FilterType::isMaxLimitString, [7], true)
                ->addFilterFieldMode('corpoTavolare', FilterType::isMaxLimitString, [3], true)
                ->addFilterFieldMode('comuneCatastale', FilterType::isMaxLimitString, [50], true)
                ->addFilterFieldMode('porzioneMateriale', FilterType::isMaxLimitString, [12], true)
                ->addFilterFieldMode('note', FilterType::isString, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if ($this->consentiEsistente || in_array(null, [$this->entityData->comune, $this->entityData->foglio, $this->entityData->particella], true)) {
            return true;
        }
        $candidato = new Immobile();
        $candidato->studio = $this->entity->studio;
        $candidato->tipoCatasto = $this->entity->tipoCatasto;
        foreach (['comune', 'sezione', 'foglio', 'particella', 'subalterno'] as $proprieta) {
            $candidato->$proprieta = $this->entityData->$proprieta;
        }
        if (isset($this->entity->id)) {
            $candidato->id = $this->entity->id;
        }
        if ((new ImmobileModel())->trovaPerChiaveCatastale($this->entity->studio, $candidato) !== null) {
            $this->formFilterError->particellaCustomMessage = 'Esiste già nell\'archivio dello studio un immobile con questo identificativo catastale.';
            return false;
        }
        return true;
    }
}
