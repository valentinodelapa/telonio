<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Casella "Canone RAI" del frontespizio dei modelli Redditi SP e SC (campo
 * 69 del record B, obbligatorio): apparecchi atti alla ricezione di
 * trasmissioni radio (1) o radiotelevisive (2) in esercizi pubblici, in
 * locali aperti al pubblico o impiegati a scopo di lucro, oppure nessun
 * apparecchio (3). Con i codici 1 e 2 si compila il prospetto del canone
 * nel quadro RS (righi RS47 di SP, RS111 e RS112 di SC).
 */
enum CanoneRai: string
{

    case radio = '1';
    case televisione = '2';
    case nessunApparecchio = '3';

    public function getLabel(): string
    {
        return match ($this) {
            self::radio => '1 - Apparecchi radio in esercizi o locali aperti al pubblico',
            self::televisione => '2 - Apparecchi radiotelevisivi in esercizi o locali aperti al pubblico',
            self::nessunApparecchio => '3 - Nessun apparecchio',
        };
    }
}
