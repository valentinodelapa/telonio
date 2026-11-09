<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Rigo del quadro RA (righi da RA1 a RA22), colonne con il numero delle
 * istruzioni. Le colonne da 11 a 15 (redditi) si compilano sul primo rigo
 * di ciascun terreno (CalcoloTerreni).
 */
class RigoTerreno
{

    public bool $continuazione = false;
    public ?int $canone = null;
    public ?int $casiParticolari = null;
    public bool $imuNonDovuta = false;
    public bool $iap = false;
    public ?int $produzioneVegetali = null;

    /** @var array<int, int> colonne da 11 a 15 */
    public array $redditi = [];

    /**
     * @param int|string $terreno chiave del terreno (righi con la stessa chiave = stesso terreno)
     */
    public function __construct(public int|string $terreno, public int $dominicale, public int $titolo, public int $agrario, public int $giorni, public float $percentuale)
    {

    }

    public function isAffittato(): bool
    {
        return in_array($this->titolo, [2, 3], true);
    }

    public function isPerditaEventi(): bool
    {
        return in_array($this->casiParticolari, [2, 6], true);
    }

    /**
     * Rivalutazioni del reddito dominicale (specifiche 6.2.1.1): 80 per
     * cento (no con l'affitto a giovani agricoltori, casi 4 e 6) e 30 per
     * cento (no per coltivatori diretti e IAP), o con la produzione di
     * vegetali secondo il codice di colonna 16.
     *
     * @return array{0: float, 1: float}
     */
    public function getRivalutazioniDominicale(): array
    {
        return match ($this->produzioneVegetali) {
            1 => [1.80, 1.30],
            2 => [1.05, 1.0],
            default => [in_array($this->casiParticolari, [4, 6], true) ? 1.0 : 1.80, $this->iap ? 1.0 : 1.30],
        };
    }

    /**
     * Rivalutazioni del reddito agrario (specifiche 6.2.2.1): 70 e 30 per
     * cento con le stesse esclusioni.
     *
     * @return array{0: float, 1: float}
     */
    public function getRivalutazioniAgrario(): array
    {
        return $this->produzioneVegetali !== null ? [1.70, 1.30] : [in_array($this->casiParticolari, [4, 6], true) ? 1.0 : 1.70, $this->iap ? 1.0 : 1.30];
    }

    /**
     * Quota del reddito dominicale senza l'agevolazione per la perdita di
     * prodotto (casi particolari 2 e 6).
     */
    public function getQuotaDominicaleNonAgevolata(): float
    {
        if ($this->titolo === 4) {
            return 0.0;
        }
        [$prima, $seconda] = $this->getRivalutazioniDominicale();
        return $this->dominicale * $prima * $seconda * $this->giorni / 365 * $this->percentuale / 100;
    }

    public function getQuotaDominicale(): float
    {
        return $this->isPerditaEventi() ? 0.0 : $this->getQuotaDominicaleNonAgevolata();
    }

    public function getQuotaAgrario(): float
    {
        if ($this->isAffittato() || $this->isPerditaEventi()) {
            return 0.0;
        }
        [$prima, $seconda] = $this->getRivalutazioniAgrario();
        // conduzione associata: la percentuale e il periodo sono già nel reddito agrario dell'atto
        return $this->casiParticolari === 3 ? $this->agrario * $prima * $seconda : $this->agrario * $prima * $seconda * $this->giorni / 365 * $this->percentuale / 100;
    }
}
