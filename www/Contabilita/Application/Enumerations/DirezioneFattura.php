<?php

namespace Contabilita\Application\Enumerations;

/**
 * Direzione della fattura elettronica rispetto all'azienda: emessa
 * (l'azienda è il cedente o prestatore) o ricevuta (cessionario o
 * committente).
 */
enum DirezioneFattura: string
{

    case emessa = 'E';
    case ricevuta = 'R';

    public function getLabel(): string
    {
        return match ($this) {
            self::emessa => 'Emessa',
            self::ricevuta => 'Ricevuta',
        };
    }
}
