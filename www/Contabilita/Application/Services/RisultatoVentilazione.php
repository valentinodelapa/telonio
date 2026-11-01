<?php

namespace Contabilita\Application\Services;

/**
 * Prospetto della ventilazione di un periodo: corrispettivi da ventilare,
 * acquisti per rivendita progressivi e ripartizione per aliquota con
 * l'imposta scorporata.
 */
class RisultatoVentilazione
{

    public float $corrispettivi = 0.0;
    public float $acquisti = 0.0;
    public float $imposta = 0.0;

    /** @var array<int, array{codice: \Contabilita\Application\Entities\CodiceIva, acquisti: float, percentuale: float, corrispettivi: float, imponibile: float, imposta: float}> */
    public array $righe = [];
}
