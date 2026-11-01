<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\PianoConti;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Denominazione e descrizione del piano dei conti.
 */
class PianoContiForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return PianoConti::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['denominazione']) && is_string($this->request->input['denominazione'])) {
            $this->addRequest('denominazione', trim($this->request->input['denominazione']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('denominazione', FilterType::isLimitString, [2, 100])
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [2000], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        return true;
    }
}
