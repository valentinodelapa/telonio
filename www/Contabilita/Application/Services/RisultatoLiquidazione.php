<?php

namespace Contabilita\Application\Services;

/**
 * Risultato del calcolo dell'IVA di un periodo: totali, riepilogo per
 * registro e codice IVA, avvisi.
 */
class RisultatoLiquidazione
{

    public float $ivaEsigibile = 0.0;
    public float $ivaDetraibile = 0.0;

    /** IVA teorica delle cessioni agricole non imponibili, compresa nella detraibile (art. 34, c. 9) */
    public float $ivaTeorica = 0.0;

    /** Pro-rata provvisorio applicato all'IVA detraibile (100 senza pro-rata) */
    public int $percentualeDetrazione = 100;

    /** @var array<string, array{registro: string, tipo: \Contabilita\Application\Enumerations\TipoRegistroIva, codice: string, imponibile: float, imposta: float, esigibile: float, detraibile: float}> */
    public array $riepilogo = [];

    /** @var string[] */
    public array $avvisi = [];

    public function getIvaPeriodo(): float
    {
        return round($this->ivaEsigibile - $this->ivaDetraibile, 2);
    }
}
