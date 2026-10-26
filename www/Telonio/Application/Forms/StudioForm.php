<?php

namespace Telonio\Application\Forms;

use Telonio\Application\Entities\Studio;
use Telonio\Application\HelperClasses\CodiceFiscale;
use Telonio\Application\HelperClasses\PartitaIva;
use Telonio\Application\Models\StudioModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Dati identificativi dello studio professionale.
 */
class StudioForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Studio::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['denominazione']) && is_string($this->request->input['denominazione'])) {
            $this->addRequest('denominazione', trim(preg_replace('/\s+/', ' ', $this->request->input['denominazione'])));
        }
        if (isset($this->request->input['codiceFiscale']) && is_string($this->request->input['codiceFiscale'])) {
            $this->addRequest('codiceFiscale', CodiceFiscale::normalizza($this->request->input['codiceFiscale']));
        }
        if (isset($this->request->input['partitaIva']) && is_string($this->request->input['partitaIva'])) {
            $this->addRequest('partitaIva', PartitaIva::normalizza($this->request->input['partitaIva']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('denominazione', FilterType::isMaxLimitString, [255])
                ->addFilterFieldMode('codiceFiscale', FilterType::isString)
                ->addFilterFieldMode('partitaIva', FilterType::isString, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $codiceFiscale = $this->entityData->codiceFiscale;
        if ($codiceFiscale !== null) {
            if ((CodiceFiscale::isValidoNumerico($codiceFiscale) === false) && (CodiceFiscale::isValidoPersonaFisica($codiceFiscale) === false)) {
                $this->formFilterError->codiceFiscaleCustomMessage = 'Codice fiscale non valido (indicare le 11 cifre per società/studi associati o i 16 caratteri per persona fisica).';
                $valido = false;
            } elseif ((new StudioModel())->esisteCodiceFiscale($codiceFiscale, $this->entity)) {
                $this->formFilterError->codiceFiscaleCustomMessage = 'Esiste già uno studio registrato con questo codice fiscale.';
                $valido = false;
            }
        }
        $partitaIva = $this->entityData->partitaIva;
        if (($partitaIva !== null) && (PartitaIva::isValida($partitaIva) === false)) {
            $this->formFilterError->partitaIvaCustomMessage = 'Partita IVA non valida (11 cifre con carattere di controllo valido).';
            $valido = false;
        }
        return $valido;
    }
}
