<?php

namespace Telonio\Application\Entities;

use Telonio\Application\Enumerations\RuoloUtente;
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
    protected RuoloUtente $ruolo;
    protected bool $attivo = true;
    protected ?SismaDateTime $ultimoAccesso = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->ruolo = RuoloUtente::operatore;
    }

    public function getNomeCompleto(): string
    {
        return $this->nome . ' ' . $this->cognome;
    }

    public function isAmministratore(): bool
    {
        return $this->ruolo === RuoloUtente::amministratore;
    }
}
