<?php

namespace Successioni\Application\Enumerations;

/**
 * Impegno a trasmettere dell'intermediario (tabella ImpegnoPresentazione
 * delle specifiche).
 */
enum ImpegnoTrasmissione: string
{

    case predispostaContribuente = '1';
    case predispostaIntermediario = '2';

    public function getLabel(): string
    {
        return match ($this) {
            self::predispostaContribuente => 'Predisposta dal contribuente',
            self::predispostaIntermediario => 'Predisposta da chi effettua l\'invio',
        };
    }
}
