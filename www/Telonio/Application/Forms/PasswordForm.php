<?php

namespace Telonio\Application\Forms;

use Telonio\Application\Entities\Password;
use Telonio\Application\Models\PasswordModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use SismaFramework\Core\HelperClasses\Encryptor;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Impostazione o cambio della password di un utente (minimo 8 caratteri,
 * con conferma e verifica facoltativa della password attuale).
 */
class PasswordForm extends BaseForm
{

    public const LUNGHEZZA_MINIMA = 8;

    public function __construct(
        ?BaseEntity $baseEntity = null,
        private bool $richiediPasswordAttuale = false,
        DataMapper $dataMapper = new DataMapper(),
    ) {
        parent::__construct($baseEntity, $dataMapper);
    }

    #[\Override]
    protected static function getEntityName(): string
    {
        return Password::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('password', FilterType::isMinLimitString, [self::LUNGHEZZA_MINIMA]);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        if ($this->richiediPasswordAttuale) {
            $passwordAttuale = (string) ($this->request->input['passwordAttuale'] ?? '');
            if (($passwordAttuale === '') || ((new PasswordModel())->verificaPassword($this->entity->utente, $passwordAttuale) === false)) {
                $this->formFilterError->passwordAttualeCustomMessage = 'La password attuale non è corretta.';
                $valido = false;
            }
        }
        if ($this->formFilterError->passwordError) {
            $this->formFilterError->passwordCustomMessage = 'La password deve contenere almeno ' . self::LUNGHEZZA_MINIMA . ' caratteri.';
        }
        $conferma = (string) ($this->request->input['confermaPassword'] ?? '');
        if (($this->entityData->password !== null) && ($conferma !== $this->entityData->password)) {
            $this->formFilterError->confermaPasswordCustomMessage = 'La conferma della password non coincide.';
            $valido = false;
        }
        return $valido;
    }

    #[\Override]
    public function resolveEntity(): BaseEntity
    {
        $password = parent::resolveEntity();
        $password->password = Encryptor::getBlowfishHash($password->password);
        return $password;
    }
}
