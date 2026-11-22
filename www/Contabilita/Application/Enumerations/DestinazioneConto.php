<?php

namespace Contabilita\Application\Enumerations;

/**
 * Destinazione del conto economico negli enti non commerciali, che tengono
 * un'unica contabilità con l'attività commerciale separata per conti (art.
 * 144, c. 2, TUIR): commerciale (concorre al reddito d'impresa e alla base
 * IRAP dell'attività commerciale), istituzionale (escluso dal reddito
 * d'impresa) o promiscuo (costi comuni alle due attività, deducibili per la
 * parte corrispondente al rapporto tra i ricavi e proventi commerciali e
 * l'ammontare complessivo dei ricavi e proventi, art. 144, c. 4, TUIR). Il
 * conto senza destinazione è commerciale: le imprese non la indicano.
 */
enum DestinazioneConto: string
{

    case commerciale = 'C';
    case istituzionale = 'I';
    case promiscuo = 'P';

    public function getLabel(): string
    {
        return match ($this) {
            self::commerciale => 'Attività commerciale',
            self::istituzionale => 'Attività istituzionale',
            self::promiscuo => 'Promiscuo (costi comuni, deducibili pro quota)',
        };
    }
}
