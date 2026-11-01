<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\RigaContabile;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Riga in partita doppia della registrazione.
 */
class RigaContabileForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return RigaContabile::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['dare', 'avere'] as $campo) {
            $valore = $this->request->input[$campo] ?? '';
            $this->addRequest($campo, is_string($valore) && (trim($valore) !== '') ? Importo::normalizza($valore) : '0');
        }
        if (isset($this->request->input['descrizione']) && is_string($this->request->input['descrizione'])) {
            $this->addRequest('descrizione', trim($this->request->input['descrizione']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('conto', FilterType::isEntity)
                ->addFilterFieldMode('controparte', FilterType::isEntity, [], true)
                ->addFilterFieldMode('dare', FilterType::isNumeric)
                ->addFilterFieldMode('avere', FilterType::isNumeric)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [255], true);
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

    public static function isVuota(array $input): bool
    {
        return (trim((string) ($input['conto'] ?? '')) === '') && (trim((string) ($input['dare'] ?? '')) === '') && (trim((string) ($input['avere'] ?? '')) === '');
    }
}
