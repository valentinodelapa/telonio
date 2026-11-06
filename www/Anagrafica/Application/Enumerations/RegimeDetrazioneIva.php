<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Regime speciale di determinazione dell'IVA detraibile del settore
 * agricolo (rigo VF30 della dichiarazione IVA): non è il regime fiscale ma
 * si affianca al regime ordinario o semplificato. Più regimi nella stessa
 * impresa richiedono la contabilità separata (art. 36) e un modulo per
 * regime: il regime si indica allora sulla singola attività.
 */
enum RegimeDetrazioneIva: string
{

    case agricolo = 'AGR';
    case agricoloEsonerato = 'ESO';
    case attivitaConnesse = 'CON';
    case agriturismo = 'TUR';
    case enoturismo = 'ENO';
    case oleoturismo = 'OLE';
    case associazioniAgricole = 'ASS';

    public function getLabel(): string
    {
        return match ($this) {
            self::agricolo => 'Regime speciale agricolo (art. 34)',
            self::agricoloEsonerato => 'Agricoltore esonerato (art. 34, c. 6)',
            self::attivitaConnesse => 'Attività agricole connesse (art. 34-bis)',
            self::agriturismo => 'Agriturismo (art. 5 L. 413/1991)',
            self::enoturismo => 'Enoturismo (L. 205/2017)',
            self::oleoturismo => 'Oleoturismo (L. 160/2019)',
            self::associazioniAgricole => 'Associazioni operanti in agricoltura (art. 78, c. 8, L. 413/1991)',
        };
    }

    /**
     * Casella del rigo VF30.
     */
    public function getCasellaVf30(): string
    {
        return match ($this) {
            self::agricolo, self::agricoloEsonerato => 'VF030008',
            self::attivitaConnesse => 'VF030007',
            self::agriturismo => 'VF030004',
            self::enoturismo => 'VF030009',
            self::oleoturismo => 'VF030010',
            self::associazioniAgricole => 'VF030005',
        };
    }

    /**
     * Quota dell'imposta sulle operazioni imponibili ammessa in detrazione;
     * null per il regime dell'art. 34, con le percentuali di compensazione.
     */
    public function getQuotaForfetaria(): ?float
    {
        return match ($this) {
            self::agricolo, self::agricoloEsonerato => null,
            self::associazioniAgricole => 1 / 3,
            default => 0.5,
        };
    }

    /**
     * Detrazione con le percentuali di compensazione (art. 34).
     */
    public function isCompensazione(): bool
    {
        return $this->getQuotaForfetaria() === null;
    }
}
