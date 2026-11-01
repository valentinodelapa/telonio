<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\RigaIva;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Riga IVA della registrazione con gli importi come nel documento
 * (positivi anche nelle note di credito: il segno è applicato dal
 * controller secondo la causale).
 */
class RigaIvaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return RigaIva::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['imponibile', 'imposta', 'detraibilita'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza($this->request->input[$campo]));
            }
        }
        if (($this->request->input['detraibilita'] ?? '') === '') {
            $this->addRequest('detraibilita', '100');
        }
        if (($this->request->input['imposta'] ?? '') === '') {
            $this->addRequest('imposta', '0');
        }
        $this->addRequest('rivendita', empty($this->request->input['rivendita']) ? '0' : '1');
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codiceIva', FilterType::isEntity)
                ->addFilterFieldMode('imponibile', FilterType::isNumeric)
                ->addFilterFieldMode('imposta', FilterType::isNumeric)
                ->addFilterFieldMode('detraibilita', FilterType::isNumeric)
                ->addFilterFieldMode('rivendita', FilterType::isBoolean)
                ->addFilterFieldMode('conto', FilterType::isEntity, [], true);
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

    /**
     * La riga è vuota (non inviata o senza importi né codice).
     */
    public static function isVuota(array $input): bool
    {
        return (trim((string) ($input['imponibile'] ?? '')) === '') && (trim((string) ($input['imposta'] ?? '')) === '');
    }
}
