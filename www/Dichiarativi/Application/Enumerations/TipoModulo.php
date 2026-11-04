<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Tipo dei moduli della dichiarazione IVA (paragrafo 2.6 delle specifiche
 * tecniche): il primo modulo del contribuente, un'attività con contabilità
 * separata (art. 36 D.P.R. 633/1972), un soggetto partecipante a
 * un'operazione straordinaria (partita IVA nel rigo VA1), la prima frazione
 * d'anno di una procedura concorsuale (rigo VA3); nel modello IVA TR della
 * controllante il modulo di una società del gruppo IVA con i requisiti del
 * rimborso (rigo TD9).
 */
enum TipoModulo: string
{

    case principale = 'P';
    case attivitaSeparata = 'A';
    case soggettoTrasformato = 'T';
    case primaFrazione = 'F';
    case societaGruppo = 'G';

    public function getLabel(): string
    {
        return match ($this) {
            self::principale => 'Contribuente',
            self::attivitaSeparata => 'Attività con contabilità separata',
            self::soggettoTrasformato => 'Soggetto partecipante a operazione straordinaria (VA1)',
            self::primaFrazione => 'Prima frazione d\'anno della procedura concorsuale (VA3)',
        };
    }

    /**
     * Il modulo si propone dalla contabilità di un soggetto della suite.
     */
    public function hasFonte(): bool
    {
        return in_array($this, [self::principale, self::soggettoTrasformato, self::societaGruppo], true);
    }
}
