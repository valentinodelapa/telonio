<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Enumerations\SezioneF24;

/**
 * Totali e verifiche di una delega (vedi ControlloDelega). Gli importi sono
 * in centesimi per evitare errori di arrotondamento nelle somme.
 */
class EsitoControllo
{

    /** @var string[] impediscono la conferma */
    public array $errori = [];
    /** @var string[] da valutare, non bloccanti */
    public array $avvisi = [];
    /** @var array<string, array{debito: int, credito: int}> per valore di SezioneF24 */
    public array $totaliSezione = [];
    public int $debito = 0;
    public int $credito = 0;
    /** @var ModelloCompilato[] ripartizione sui modelli F24 */
    public array $modelli = [];

    public function __construct()
    {
        foreach (SezioneF24::cases() as $sezione) {
            $this->totaliSezione[$sezione->value] = ['debito' => 0, 'credito' => 0];
        }
    }

    public function getSaldo(): int
    {
        return $this->debito - $this->credito;
    }

    public function isConfermabile(): bool
    {
        return count($this->errori) === 0;
    }

    public static function euro(int $centesimi): string
    {
        return number_format($centesimi / 100, 2, ',', '.');
    }

    public static function centesimi(float $importo): int
    {
        return (int) round($importo * 100);
    }
}
