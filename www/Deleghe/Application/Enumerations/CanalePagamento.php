<?php

namespace Deleghe\Application\Enumerations;

/**
 * Come il cliente ha pagato la delega che gli è stata consegnata (la
 * quietanza delle deleghe trasmesse dallo studio è dell'invio telematico).
 */
enum CanalePagamento: string
{

    case homeBanking = 'H';
    case banca = 'B';
    case posta = 'P';
    case altro = 'X';

    public function getLabel(): string
    {
        return match ($this) {
            self::homeBanking => 'Home banking',
            self::banca => 'Banca / sportello',
            self::posta => 'Posta',
            self::altro => 'Altro',
        };
    }
}
