<?php

namespace Anagrafica\Application\Enumerations;

enum TipoRecapito: string
{

    case telefono = 'TEL';
    case cellulare = 'CEL';
    case email = 'EML';
    case pec = 'PEC';
    case fax = 'FAX';
    case sitoWeb = 'WEB';

    public function getLabel(): string
    {
        return match ($this) {
            self::telefono => 'Telefono',
            self::cellulare => 'Cellulare',
            self::email => 'E-mail',
            self::pec => 'PEC',
            self::fax => 'Fax',
            self::sitoWeb => 'Sito web',
        };
    }

    public function getIcona(): string
    {
        return match ($this) {
            self::telefono => 'bi-telephone',
            self::cellulare => 'bi-phone',
            self::email => 'bi-envelope',
            self::pec => 'bi-envelope-check',
            self::fax => 'bi-printer',
            self::sitoWeb => 'bi-globe',
        };
    }

    public function isIndirizzoEmail(): bool
    {
        return in_array($this, [self::email, self::pec], true);
    }

    public function getCollegamento(string $valore): ?string
    {
        return match ($this) {
            self::email, self::pec => 'mailto:' . $valore,
            self::telefono, self::cellulare => 'tel:' . preg_replace('/[^0-9+]/', '', $valore),
            self::sitoWeb => preg_match('#^https?://#i', $valore) === 1 ? $valore : 'https://' . $valore,
            self::fax => null,
        };
    }
}
