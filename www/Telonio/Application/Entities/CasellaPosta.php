<?php

namespace Telonio\Application\Entities;

use Telonio\Application\Enumerations\CifraturaSmtp;
use Telonio\Application\Enumerations\TipoCasellaPosta;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Configurazione di invio di una casella dello studio (una per tipo: e-mail
 * ordinaria e PEC): server SMTP, credenziali e mittente. La password è
 * cifrata nel database con ENCRYPTION_PASSPHRASE.
 *
 * Senza la casella e-mail dello studio si usano i parametri MAIL_* del .env
 * (InvioPosta::getCasella); la PEC va sempre configurata, perché il
 * mittente deve essere la casella PEC dello studio.
 */
class CasellaPosta extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected TipoCasellaPosta $tipoCasella;
    protected string $host;
    protected int $porta;
    protected CifraturaSmtp $cifratura = CifraturaSmtp::sslTls;
    protected ?string $utente = null;
    protected ?string $password = null;
    protected ?string $passwordInitializationVector = null;
    protected string $indirizzo;
    protected ?string $nomeMittente = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {
        $this->addEncryptedProperty('password');
    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Configurazione salvata nel database (non quella ricavata dal .env).
     */
    public function isSalvata(): bool
    {
        return isset($this->id);
    }

    public function getMittente(): string
    {
        return $this->nomeMittente ?? $this->studio->denominazione;
    }
}
