<?php

namespace Telonio\Application\Enumerations;

/**
 * Protezione della connessione al server SMTP: SSL/TLS implicito (di norma
 * porta 465, quella dei gestori PEC), STARTTLS (porta 587) o nessuna (solo
 * per server locali, es. Mailpit in sviluppo).
 */
enum CifraturaSmtp: string
{

    case sslTls = 'S';
    case starttls = 'T';
    case nessuna = 'N';

    public function getLabel(): string
    {
        return match ($this) {
            self::sslTls => 'SSL/TLS',
            self::starttls => 'STARTTLS',
            self::nessuna => 'Nessuna (solo server locali)',
        };
    }

    public function getPortaPredefinita(): int
    {
        return match ($this) {
            self::sslTls => 465,
            self::starttls => 587,
            self::nessuna => 25,
        };
    }

    /**
     * Valore di MAIL_ENCRYPTION nel .env (ssl, tls/starttls, none).
     */
    public static function daAmbiente(string $valore): self
    {
        return match (strtolower(trim($valore))) {
            'ssl', 'smtps' => self::sslTls,
            'tls', 'starttls' => self::starttls,
            default => self::nessuna,
        };
    }
}
