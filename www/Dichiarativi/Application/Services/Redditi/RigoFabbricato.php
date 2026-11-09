<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Rigo del quadro RB (righi da RB1 a RB9), colonne con il numero delle
 * istruzioni. Le colonne da 13 a 19 (redditi) si compilano solo sul primo
 * rigo di ciascun fabbricato (CalcoloFabbricati).
 */
class RigoFabbricato
{

    public const UTILIZZI_ABITAZIONE_PRINCIPALE = [1, 5, 6, 11, 12];
    public const UTILIZZI_NON_LOCATI = [2, 9, 10, 15];
    public const UTILIZZI_LOCATI = [3, 4, 8, 14];

    public bool $continuazione = false;
    public ?int $codiceCanone = null;
    public ?int $canone = null;
    public ?int $casiParticolari = null;
    public ?string $codiceComune = null;
    public ?int $cedolare = null;
    public ?int $casiImu = null;
    public ?int $altriDati = null;

    /** @var array<int, int> colonne da 13 a 19 */
    public array $redditi = [];

    /** Dati per le sezioni II e III (estremi del contratto, CIN) */
    public ?object $contratto = null;

    /**
     * @param int|string $fabbricato chiave del fabbricato (righi con la stessa chiave = stesso fabbricato)
     */
    public function __construct(public int|string $fabbricato, public int $rendita, public int $utilizzo, public int $giorni, public float $percentuale)
    {

    }

    /**
     * 1,05 x rendita x giorni / 365 x percentuale / 100 (specifiche, par. 7.2.3).
     */
    public function getQuotaRendita(): float
    {
        return 1.05 * $this->rendita * $this->giorni / 365 * $this->percentuale / 100;
    }

    /**
     * Canone per la percentuale di possesso, per intero con i casi
     * particolari 5 e 8 (locazione di una sola quota).
     */
    public function getQuotaCanone(): float
    {
        return in_array($this->casiParticolari, [5, 8], true) ? (float) $this->canone : (float) $this->canone * $this->percentuale / 100;
    }

    /**
     * Canoni non percepiti senza canone: tassazione sulla rendita.
     */
    public function isCanoneNonPercepito(): bool
    {
        return in_array($this->casiParticolari, [4, 7], true) && ($this->canone === null);
    }
}
