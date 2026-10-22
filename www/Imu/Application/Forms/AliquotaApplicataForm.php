<?php

namespace Imu\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\AliquotaApplicata;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Aliquota dell'immobile per l'anno: una differenziata del prospetto o
 * un'aliquota indicata dallo studio. Soggetto, immobile e anno sono
 * impostati dal controller, che verifica anche che la differenziata sia
 * del prospetto del comune per l'anno.
 */
class AliquotaApplicataForm extends BaseForm
{

    /**
     * Aliquota massima (comma 755: 1,06% più lo 0,08% sostitutivo della
     * maggiorazione TASI).
     */
    public const ALIQUOTA_MASSIMA = 1.14;

    #[\Override]
    protected static function getEntityName(): string
    {
        return AliquotaApplicata::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['aliquota']) && is_string($this->request->input['aliquota'])) {
            $this->addRequest('aliquota', Importo::normalizza(str_replace('%', '', $this->request->input['aliquota'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('aliquotaDifferenziata', FilterType::isEntity, [], true)
                ->addFilterFieldMode('aliquota', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('motivo', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        if (($dati->aliquotaDifferenziata === null) === ($dati->aliquota === null)) {
            $this->formFilterError->aliquotaCustomMessage = 'Scegli un\'aliquota differenziata del prospetto oppure indica l\'aliquota.';
            return false;
        }
        if (($dati->aliquota !== null) && (($dati->aliquota < 0) || ($dati->aliquota > self::ALIQUOTA_MASSIMA))) {
            $this->formFilterError->aliquotaCustomMessage = 'Aliquota da 0 a ' . number_format(self::ALIQUOTA_MASSIMA, 2, ',', '') . '% (comma 755).';
            return false;
        }
        return true;
    }
}
