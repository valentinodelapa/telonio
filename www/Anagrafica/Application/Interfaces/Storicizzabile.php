<?php

namespace Anagrafica\Application\Interfaces;

/**
 * Dato anagrafico con validità temporale: ogni versione vale dalla data
 * validoDal alla data validoAl inclusa (null = tuttora vigente).
 *
 * Le entità che lo implementano dichiarano le proprietà
 * SismaDate $validoDal e ?SismaDate $validoAl e usano StoricizzabileTrait.
 */
interface Storicizzabile
{

    public function isVigenteAl(\DateTimeInterface $data): bool;

    public function isAperto(): bool;
}
