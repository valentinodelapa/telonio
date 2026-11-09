<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Tipo del contratto di locazione o di affitto, come lo distinguono i
 * quadri RA (titolo, col. 2) e RB (utilizzo, col. 2) del modello Redditi:
 * per i fabbricati il canone libero (anche "patti in deroga"), l'equo
 * canone, il canone concordato nei comuni ad alta tensione abitativa, la
 * locazione agevolata in Abruzzo dopo il sisma del 2009 e la locazione
 * breve (fino a 30 giorni, art. 4 D.L. 50/2017); per i terreni l'affitto
 * in regime vincolistico o di libero mercato.
 */
enum TipoContrattoLocazione: string
{

    case canoneLibero = 'LL';
    case equoCanone = 'EC';
    case canoneConcordato = 'CC';
    case abruzzo = 'AB';
    case locazioneBreve = 'BR';
    case affittoVincolistico = 'AV';
    case affittoLibero = 'AL';

    public function getLabel(): string
    {
        return match ($this) {
            self::canoneLibero => 'Canone libero',
            self::equoCanone => 'Equo canone',
            self::canoneConcordato => 'Canone concordato (comune ad alta tensione abitativa)',
            self::abruzzo => 'Locazione agevolata in Abruzzo (sisma 2009)',
            self::locazioneBreve => 'Locazione breve (fino a 30 giorni)',
            self::affittoVincolistico => 'Affitto di terreno in regime vincolistico',
            self::affittoLibero => 'Affitto di terreno a libero mercato',
        };
    }

    public function isTerreno(): bool
    {
        return in_array($this, [self::affittoVincolistico, self::affittoLibero], true);
    }

    /**
     * La cedolare secca riguarda solo le locazioni di fabbricati.
     */
    public function ammetteCedolare(): bool
    {
        return $this->isTerreno() === false;
    }

    /**
     * Fabbricati: in tassazione ordinaria il canone concordato e la
     * locazione in Abruzzo riducono il reddito del 30 per cento.
     */
    public function isRiduzioneTrentaPerCento(): bool
    {
        return in_array($this, [self::canoneConcordato, self::abruzzo], true);
    }
}
