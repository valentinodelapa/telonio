<?php

namespace Imu\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\CaratteristicheImu;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Versione delle caratteristiche IMU dell'immobile; l'immobile è
 * impostato dal controller.
 */
class CaratteristicheImuForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return CaratteristicheImu::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['valoreVenale']) && is_string($this->request->input['valoreVenale'])) {
            $this->addRequest('valoreVenale', Importo::normalizza($this->request->input['valoreVenale']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('storicoArtistico', FilterType::isBoolean)
                ->addFilterFieldMode('inagibile', FilterType::isBoolean)
                ->addFilterFieldMode('ruraleStrumentale', FilterType::isBoolean)
                ->addFilterFieldMode('beneMerce', FilterType::isBoolean)
                ->addFilterFieldMode('areaFabbricabile', FilterType::isBoolean)
                ->addFilterFieldMode('valoreVenale', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('valoreContabile', FilterType::isBoolean)
                ->addFilterFieldMode('exComune', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('esenzione', FilterType::isEnumeration, [], true)
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
        if ($dati->areaFabbricabile && (($dati->valoreVenale === null) || ($dati->valoreVenale <= 0))) {
            $this->formFilterError->valoreVenaleCustomMessage = 'Indicare il valore venale in comune commercio al 1° gennaio (comma 746).';
            return false;
        }
        if (($dati->areaFabbricabile === false) && ($dati->valoreVenale !== null)) {
            $this->formFilterError->valoreVenaleCustomMessage = 'Il valore venale si indica solo per le aree fabbricabili.';
            return false;
        }
        if ($dati->valoreContabile && $dati->areaFabbricabile) {
            $this->formFilterError->valoreContabileCustomMessage = 'Un\'area fabbricabile non è un fabbricato a valore contabile.';
            return false;
        }
        return true;
    }
}
