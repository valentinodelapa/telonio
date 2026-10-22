<?php

namespace Deleghe\Application\Enumerations;

/**
 * Sezioni dell'elenco dei codici tributo pubblicato dall'Agenzia delle
 * Entrate (motore di ricerca dei codici tributo). Accise ed elementi
 * identificativi riguardano i modelli F24 Accise ed F24 ELIDE.
 */
enum SezioneCodiceTributo: string
{

    case erario = 'E';
    case regioni = 'R';
    case tributiLocali = 'L';
    case accise = 'A';
    case elementiIdentificativi = 'I';

    public function getLabel(): string
    {
        return match ($this) {
            self::erario => 'Erario',
            self::regioni => 'Regioni',
            self::tributiLocali => 'IMU e altri tributi locali',
            self::accise => 'Accise',
            self::elementiIdentificativi => 'Elementi identificativi (F24 ELIDE)',
        };
    }
}
