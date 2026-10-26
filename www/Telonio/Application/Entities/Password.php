<?php

namespace Telonio\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Security\Interfaces\Entities\PasswordInterface;

/**
 * Hash bcrypt della password di un utente. Le password precedenti restano
 * storicizzate: è valida quella con data di creazione più recente.
 */
class Password extends BaseEntity implements PasswordInterface
{

    protected int $id;
    protected Utente $utente;
    protected string $password;
    protected SismaDateTime $dataCreazione;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataCreazione = new SismaDateTime();
    }
}
