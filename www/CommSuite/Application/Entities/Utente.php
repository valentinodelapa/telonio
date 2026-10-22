<?php

namespace CommSuite\Application\Entities;

use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use SismaFramework\Security\Interfaces\Entities\AuthenticableInterface;

/**
 * Operatore dello studio abilitato all'uso della suite.
 */
class Utente extends ReferencedEntity implements AuthenticableInterface
{

    protected int $id;
    protected Studio $studio;
    protected string $username;
    protected string $cognome;
    protected string $nome;
    protected string $email;
    protected bool $attivo = true;
    protected ?SismaDateTime $ultimoAccesso = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getNomeCompleto(): string
    {
        return $this->nome . ' ' . $this->cognome;
    }
}
