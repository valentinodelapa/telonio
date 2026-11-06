<?php

namespace Dichiarativi\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Dichiarativi\Application\Entities\AttoAccertamento;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Atto di accertamento: studio e contribuente sono impostati dal controller.
 */
class AttoAccertamentoForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return AttoAccertamento::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['importoDichiarato', 'importoAccertato'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza($this->request->input[$campo]));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('annoImposta', FilterType::isInteger)
                ->addFilterFieldMode('dataNotifica', FilterType::isDate)
                ->addFilterFieldMode('estremi', FilterType::isMaxLimitString, [60], true)
                ->addFilterFieldMode('importoDichiarato', FilterType::isNumeric)
                ->addFilterFieldMode('importoAccertato', FilterType::isNumeric);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if ($this->entityData->importoDichiarato < 0) {
            $this->formFilterError->importoDichiaratoCustomMessage = 'L\'importo dichiarato non può essere negativo.';
            return false;
        }
        if ($this->entityData->importoAccertato < 0) {
            $this->formFilterError->importoAccertatoCustomMessage = 'L\'importo accertato non può essere negativo.';
            return false;
        }
        return true;
    }
}
