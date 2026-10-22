<?php

namespace Deleghe\Application\Forms;

use Deleghe\Application\Entities\Intermediario;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Intermediario Entratel dello studio: soggetto dell'anagrafica, recapiti
 * per le comunicazioni dell'Agenzia, conto per l'addebito unico.
 */
class IntermediarioForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Intermediario::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['telefono']) && is_string($this->request->input['telefono'])) {
            $this->addRequest('telefono', preg_replace('/[^\d+]/', '', $this->request->input['telefono']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('soggetto', FilterType::isEntity)
                ->addFilterFieldMode('telefono', FilterType::isMaxLimitString, [12], true)
                ->addFilterFieldMode('email', FilterType::isEmail, [], true)
                ->addFilterFieldMode('contoAddebito', FilterType::isEntity, [], true)
                ->addFilterFieldMode('predefinito', FilterType::isBoolean);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $soggetto = $this->entityData->soggetto;
        if (($soggetto !== null) && ($soggetto->studio->id !== $this->entity->studio->id)) {
            $this->formFilterError->soggettoCustomMessage = 'Soggetto non trovato.';
            return false;
        }
        $conto = $this->entityData->contoAddebito;
        if (($conto !== null) && (($soggetto === null) || ($conto->soggetto->id !== $soggetto->id))) {
            $this->formFilterError->contoAddebitoCustomMessage = 'Il conto deve essere intestato all\'intermediario.';
            return false;
        }
        if (($this->entityData->email !== null) && (mb_strlen($this->entityData->email) > 55)) {
            $this->formFilterError->emailCustomMessage = 'E-mail: al massimo 55 caratteri (tracciato telematico).';
            return false;
        }
        return true;
    }
}
