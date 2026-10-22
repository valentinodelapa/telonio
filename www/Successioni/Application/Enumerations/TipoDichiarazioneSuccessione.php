<?php

namespace Successioni\Application\Enumerations;

/**
 * Prima dichiarazione o dichiarazione sostitutiva/integrativa (campo
 * "Dichiarazione sostitutiva" del frontespizio, specifiche SUC13).
 */
enum TipoDichiarazioneSuccessione: string
{

    case prima = '0';
    case sostitutivaConTrascrizione = '1';
    case sostitutivaSenzaTrascrizione = '2';
    case integrativaDocumentazione = '3';

    public function getLabel(): string
    {
        return match ($this) {
            self::prima => 'Prima dichiarazione',
            self::sostitutivaConTrascrizione => 'Sostitutiva con nuova trascrizione e voltura sostitutiva',
            self::sostitutivaSenzaTrascrizione => 'Sostitutiva senza nuova trascrizione',
            self::integrativaDocumentazione => 'Integrativa con sola presentazione di documentazione',
        };
    }

    public function isSostitutiva(): bool
    {
        return $this !== self::prima;
    }

    /**
     * La dichiarazione integrativa con sola documentazione contiene solo il
     * frontespizio e gli allegati (quadro EG).
     */
    public function hasQuadri(): bool
    {
        return $this !== self::integrativaDocumentazione;
    }
}
