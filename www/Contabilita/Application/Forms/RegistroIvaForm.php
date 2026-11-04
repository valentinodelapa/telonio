<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Models\RegistroIvaModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Registro IVA o sezionale; studio e soggetto sono impostati dal
 * controller, tipo e sezionale si scelgono solo alla creazione (la
 * numerazione dei protocolli ne dipende).
 */
class RegistroIvaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return RegistroIva::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['sezionale']) && is_string($this->request->input['sezionale'])) {
            $this->addRequest('sezionale', strtoupper(trim($this->request->input['sezionale'])));
        }
        $this->addRequest('attivo', isset($this->request->input['attivo']) ? '1' : '0');
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        if (isset($this->entity->id) === false) {
            $this->addFilterFieldMode('tipo', FilterType::isEnumeration)
                    ->addFilterFieldMode('sezionale', FilterType::isLimitString, [1, 10]);
        }
        $this->addFilterFieldMode('descrizione', FilterType::isLimitString, [2, 100])
                ->addFilterFieldMode('attivo', FilterType::isBoolean)
                ->addFilterFieldMode('unitaLocale', FilterType::isEntity, [], true)
                ->addFilterFieldMode('attivitaIva', FilterType::isEntity, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if (isset($this->entity->id)) {
            return true;
        }
        if (preg_match('/^[A-Z0-9]{1,10}$/', (string) $this->entityData->sezionale) !== 1) {
            $this->formFilterError->sezionaleCustomMessage = 'Lettere e cifre, fino a 10 caratteri.';
            return false;
        }
        if ((new RegistroIvaModel())->getRegistro($this->entity->soggetto, $this->entityData->tipo, $this->entityData->sezionale) !== null) {
            $this->formFilterError->sezionaleCustomMessage = 'Sezionale già presente per il registro.';
            return false;
        }
        return true;
    }
}
