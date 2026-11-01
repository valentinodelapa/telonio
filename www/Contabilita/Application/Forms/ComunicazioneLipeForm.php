<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\ComunicazioneLipe;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Frontespizio della comunicazione delle liquidazioni periodiche:
 * dichiarante e impegno dell'intermediario. Periodo e moduli vengono
 * dalla preparazione.
 */
class ComunicazioneLipeForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return ComunicazioneLipe::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('dichiarante', FilterType::isEntity, [], true)
                ->addFilterFieldMode('codiceCarica', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('intermediario', FilterType::isEntity, [], true)
                ->addFilterFieldMode('impegno', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('dataImpegno', FilterType::isDate, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        if (($dati->dichiarante !== null) && ($dati->dichiarante->isPersonaFisica() === false)) {
            $this->formFilterError->dichiaranteCustomMessage = 'Il dichiarante deve essere una persona fisica.';
            return false;
        }
        if (($dati->dichiarante !== null) && ($dati->codiceCarica === null)) {
            $this->formFilterError->codiceCaricaCustomMessage = 'Indicare il codice di carica del dichiarante.';
            return false;
        }
        if (($dati->intermediario !== null) && ($dati->impegno === null)) {
            $this->formFilterError->impegnoCustomMessage = 'Indicare chi ha predisposto la comunicazione.';
            return false;
        }
        if (($dati->intermediario !== null) && ($dati->dataImpegno === null)) {
            $this->formFilterError->dataImpegnoCustomMessage = 'Indicare la data dell\'impegno a trasmettere.';
            return false;
        }
        return true;
    }
}
