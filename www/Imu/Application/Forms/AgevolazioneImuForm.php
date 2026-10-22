<?php

namespace Imu\Application\Forms;

use Anagrafica\Application\Enumerations\TipoCatasto;
use Imu\Application\Entities\AgevolazioneImu;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Versione delle agevolazioni IMU del possessore; soggetto e immobile sono
 * impostati dal controller.
 */
class AgevolazioneImuForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return AgevolazioneImu::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('comodatoParenti', FilterType::isBoolean)
                ->addFilterFieldMode('conduzioneAgricola', FilterType::isBoolean)
                ->addFilterFieldMode('assimilazione', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('pensionatoEstero', FilterType::isBoolean)
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
        $terreno = $this->entity->immobile->tipoCatasto === TipoCatasto::terreni;
        if ($terreno && ($dati->comodatoParenti || ($dati->assimilazione !== null) || $dati->pensionatoEstero)) {
            $this->formFilterError->comodatoParentiCustomMessage = 'Per un terreno si indica solo la conduzione da coltivatore diretto o IAP.';
            return false;
        }
        if ($dati->comodatoParenti && ($dati->assimilazione !== null)) {
            $this->formFilterError->assimilazioneCustomMessage = 'Un immobile in comodato non è assimilato all\'abitazione principale del possessore.';
            return false;
        }
        return true;
    }
}
