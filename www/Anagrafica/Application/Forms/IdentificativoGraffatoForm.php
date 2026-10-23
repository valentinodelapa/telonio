<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\IdentificativoGraffato;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

class IdentificativoGraffatoForm extends BaseForm
{

    private const CAMPI = ['sezione', 'foglio', 'particella', 'subalterno'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return IdentificativoGraffato::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::CAMPI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(trim($this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('sezione', FilterType::isMaxLimitString, [5], true)
                ->addFilterFieldMode('foglio', FilterType::isMaxLimitString, [10])
                ->addFilterFieldMode('particella', FilterType::isMaxLimitString, [10])
                ->addFilterFieldMode('subalterno', FilterType::isMaxLimitString, [10], true);
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
