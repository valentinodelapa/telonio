<?php

namespace Telonio\Application\Forms;

use Telonio\Application\Entities\Utente;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Dati personali modificabili dall'utente stesso nel proprio profilo.
 */
class ProfiloForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Utente::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['cognome', 'nome'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim(preg_replace('/\s+/', ' ', $this->request->input[$campo])));
            }
        }
        if (isset($this->request->input['email']) && is_string($this->request->input['email'])) {
            $this->addRequest('email', strtolower(trim($this->request->input['email'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('cognome', FilterType::isMaxLimitString, [100])
                ->addFilterFieldMode('nome', FilterType::isMaxLimitString, [100])
                ->addFilterFieldMode('email', FilterType::isMaxLimitString, [255]);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if (($this->entityData->email !== null) && (filter_var($this->entityData->email, FILTER_VALIDATE_EMAIL) === false)) {
            $this->formFilterError->emailCustomMessage = 'Indirizzo e-mail non valido.';
            return false;
        }
        return true;
    }
}
