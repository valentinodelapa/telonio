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

    /**
     * Caselle del quadro VO dell'opzione per i modi ordinari e della sua
     * revoca; l'esonero non ha un'opzione ma la rinuncia del rigo VO3.
     *
     * @return array{opzione: string, revoca: string}|null
     */
    public function getCaselleOpzione(): ?array
    {
        return match ($this) {
            self::agricolo => ['opzione' => 'VO003003', 'revoca' => 'VO003004'],
            self::attivitaConnesse => ['opzione' => 'VO003005', 'revoca' => 'VO003006'],
            self::associazioniAgricole => ['opzione' => 'VO031001', 'revoca' => 'VO031002'],
            self::agriturismo => ['opzione' => 'VO032001', 'revoca' => 'VO032002'],
            self::enoturismo => ['opzione' => 'VO035001', 'revoca' => 'VO035002'],
            self::oleoturismo => ['opzione' => 'VO036001', 'revoca' => 'VO036002'],
            self::agricoloEsonerato => null,
        };
    }

    /**
     * Regimi ai quali si può rinunciare per i modi ordinari.
     *
     * @return self[]
     */
    public static function getOpzionabili(): array
    {
        return array_values(array_filter(self::cases(), fn(self $regime) => $regime->getCaselleOpzione() !== null));
    }

    /**
     * @return self[]
     */
    public static function leggiOpzioni(?string $opzioni): array
    {
        return array_values(array_filter(array_map(fn(string $codice) => self::tryFrom($codice), explode(';', (string) $opzioni)), fn(?self $regime) => ($regime !== null) && ($regime->getCaselleOpzione() !== null)));
    }

    /**
     * @param string[] $codici
     */
    public static function scriviOpzioni(array $codici): ?string
    {
        $opzioni = array_map(fn(self $regime) => $regime->value, self::leggiOpzioni(implode(';', array_filter($codici, 'is_string'))));
        return count($opzioni) === 0 ? null : implode(';', array_unique($opzioni));
    }
}
