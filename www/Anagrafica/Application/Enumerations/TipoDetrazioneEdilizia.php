<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Detrazioni per gli interventi sugli immobili, secondo le sezioni del
 * quadro RP del modello Redditi PF: recupero del patrimonio edilizio,
 * misure antisismiche, bonus verde, bonus facciate e superbonus
 * (sezione III A, righi da RP41 a RP47), infrastrutture di ricarica dei
 * veicoli elettrici (sezione III C, rigo RP56, codici da 2 a 5), arredo
 * degli immobili ristrutturati (rigo RP57) e risparmio energetico
 * (sezione IV, righi da RP61 a RP64).
 */
enum TipoDetrazioneEdilizia: string
{

    case ristrutturazione = 'RE';
    case colonnine = 'CR';
    case arredo = 'AR';
    case risparmioEnergetico = 'EN';

    public function getLabel(): string
    {
        return match ($this) {
            self::ristrutturazione => 'Recupero edilizio, sismabonus, bonus verde, facciate, superbonus (RP41-RP47)',
            self::colonnine => 'Colonnine di ricarica dei veicoli elettrici (RP56)',
            self::arredo => 'Mobili ed elettrodomestici per l\'immobile ristrutturato (RP57)',
            self::risparmioEnergetico => 'Risparmio energetico ed ecobonus (RP61-RP64)',
        };
    }

    public function getEtichettaBreve(): string
    {
        return match ($this) {
            self::ristrutturazione => 'Recupero edilizio',
            self::colonnine => 'Colonnine di ricarica',
            self::arredo => 'Bonus mobili',
            self::risparmioEnergetico => 'Risparmio energetico',
        };
    }

    /**
     * Sezione con la casella "Percentuale" (col. 7 di RP41, col. 4 di RP56,
     * col. 6 di RP61).
     */
    public function haCodicePercentuale(): bool
    {
        return $this !== self::arredo;
    }

    /**
     * Sezione con il codice dell'intervento (tipologia di col. 2 di RP41,
     * codice di col. 1 di RP56, tipo di col. 1 di RP61).
     */
    public function haTipologia(): bool
    {
        return $this !== self::arredo;
    }
}
