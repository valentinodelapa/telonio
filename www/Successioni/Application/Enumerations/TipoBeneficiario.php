<?php

namespace Successioni\Application\Enumerations;

/**
 * Tipo soggetto del quadro EA (tabella TipoSoggetto delle specifiche
 * SUC13).
 */
enum TipoBeneficiario: string
{

    case erede = '1';
    case legatario = '2';
    case chiamato = '3';
    case coniugeRinunciatario = '4';
    case trust = '5';
    case altro = '6';

    public function getLabel(): string
    {
        return match ($this) {
            self::erede => 'Erede',
            self::legatario => 'Legatario',
            self::chiamato => 'Chiamato all\'eredità',
            self::coniugeRinunciatario => 'Coniuge/parte dell\'unione civile superstite rinunciatario',
            self::trust => 'Trust',
            self::altro => 'Altro',
        };
    }

    /**
     * Tenuto in solido al pagamento dell'imposta complessiva (eredi); il
     * legatario risponde solo della propria quota.
     */
    public function isErede(): bool
    {
        return in_array($this, [self::erede, self::chiamato, self::coniugeRinunciatario], true);
    }

    /**
     * Tipi gestiti dalla procedura (il trust ha quadri e regole propri).
     *
     * @return self[]
     */
    public static function getGestiti(): array
    {
        return [self::erede, self::legatario, self::chiamato, self::coniugeRinunciatario, self::altro];
    }
}
