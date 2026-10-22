<?php

namespace Successioni\Application\Services;

/**
 * Frazione ridotta ai minimi termini, per le quote di devoluzione (il
 * modello richiede numeratore e denominatore interi).
 */
final class Frazione
{

    public readonly int $numeratore;
    public readonly int $denominatore;

    public function __construct(int $numeratore, int $denominatore = 1)
    {
        if ($denominatore === 0) {
            throw new \DivisionByZeroError('Denominatore nullo.');
        }
        if ($denominatore < 0) {
            [$numeratore, $denominatore] = [-$numeratore, -$denominatore];
        }
        $divisore = self::mcd(abs($numeratore), $denominatore);
        $this->numeratore = intdiv($numeratore, max(1, $divisore));
        $this->denominatore = intdiv($denominatore, max(1, $divisore));
    }

    /**
     * Da una percentuale con al massimo due decimali (es. 33,33).
     */
    public static function daPercentuale(float $percentuale): self
    {
        return new self((int) round($percentuale * 100), 10000);
    }

    public function per(self|int $altra): self
    {
        $altra = is_int($altra) ? new self($altra) : $altra;
        return new self($this->numeratore * $altra->numeratore, $this->denominatore * $altra->denominatore);
    }

    public function diviso(int $divisore): self
    {
        return new self($this->numeratore, $this->denominatore * $divisore);
    }

    public function piu(self $altra): self
    {
        return new self($this->numeratore * $altra->denominatore + $altra->numeratore * $this->denominatore, $this->denominatore * $altra->denominatore);
    }

    public function meno(self $altra): self
    {
        return $this->piu(new self(-$altra->numeratore, $altra->denominatore));
    }

    public function toFloat(): float
    {
        return $this->numeratore / $this->denominatore;
    }

    public function isUguale(self $altra): bool
    {
        return ($this->numeratore === $altra->numeratore) && ($this->denominatore === $altra->denominatore);
    }

    public function __toString(): string
    {
        return $this->numeratore . '/' . $this->denominatore;
    }

    private static function mcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return $a;
    }
}
