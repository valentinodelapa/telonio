<?php

namespace Successioni\Application\Enumerations;

use Anagrafica\Application\Enumerations\TitoloPossesso;

/**
 * Codice diritto (allegato 4 alle istruzioni, tabelle CodiciDiritto delle
 * specifiche SUC13), con il raccordo ai titoli di possesso dell'Anagrafica
 * per proporre l'attivo e le volture.
 */
enum CodiceDiritto: string
{

    case proprieta = '1';
    case proprietaSuperficiaria = '1S';
    case proprietaArea = '1T';
    case nudaProprieta = '2';
    case nudaProprietaSuperficiaria = '2S';
    case nudaProprietaArea = '2T';
    case abitazione = '3';
    case abitazioneSuperficiaria = '3S';
    case concedente = '4';
    case enfiteuta = '5';
    case superficie = '6';
    case uso = '7';
    case usoSuperficiaria = '7S';
    case usufrutto = '8';
    case usufruttoSuperficiaria = '8S';
    case usufruttoArea = '8T';
    case servitu = '9';
    case oneriReali = '10';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::proprieta => 'Proprietà',
            self::proprietaSuperficiaria => 'Proprietà superficiaria',
            self::proprietaArea => 'Proprietà per l\'area',
            self::nudaProprieta => 'Nuda proprietà',
            self::nudaProprietaSuperficiaria => 'Nuda proprietà superficiaria',
            self::nudaProprietaArea => 'Nuda proprietà per l\'area',
            self::abitazione => 'Abitazione',
            self::abitazioneSuperficiaria => 'Abitazione su proprietà superficiaria',
            self::concedente => 'Diritto del concedente',
            self::enfiteuta => 'Diritto dell\'enfiteuta',
            self::superficie => 'Superficie',
            self::uso => 'Uso',
            self::usoSuperficiaria => 'Uso su proprietà superficiaria',
            self::usufrutto => 'Usufrutto',
            self::usufruttoSuperficiaria => 'Usufrutto su proprietà superficiaria',
            self::usufruttoArea => 'Usufrutto su proprietà per l\'area',
            self::servitu => 'Servitù',
            self::oneriReali => 'Oneri reali',
        };
    }

    /**
     * Titolo di possesso dell'Anagrafica corrispondente (per le volture);
     * null se il diritto non ha un titolo corrispondente.
     */
    public function getTitoloPossesso(): ?TitoloPossesso
    {
        return match ($this) {
            self::proprieta, self::proprietaSuperficiaria, self::proprietaArea => TitoloPossesso::proprieta,
            self::nudaProprieta, self::nudaProprietaSuperficiaria, self::nudaProprietaArea => TitoloPossesso::nudaProprieta,
            self::abitazione, self::abitazioneSuperficiaria => TitoloPossesso::abitazione,
            self::uso, self::usoSuperficiaria => TitoloPossesso::uso,
            self::usufrutto, self::usufruttoSuperficiaria, self::usufruttoArea => TitoloPossesso::usufrutto,
            self::superficie => TitoloPossesso::superficie,
            self::enfiteuta => TitoloPossesso::enfiteusi,
            default => null,
        };
    }

    /**
     * Codice del diritto del defunto dal titolo di possesso; null per i
     * diritti che si estinguono con la morte (usufrutto, uso, abitazione:
     * si consolidano con la nuda proprietà) e per la locazione finanziaria.
     */
    public static function daTitoloPossesso(TitoloPossesso $titolo): ?self
    {
        return match ($titolo) {
            TitoloPossesso::proprieta => self::proprieta,
            TitoloPossesso::nudaProprieta => self::nudaProprieta,
            TitoloPossesso::superficie => self::superficie,
            TitoloPossesso::enfiteusi => self::enfiteuta,
            default => null,
        };
    }

    /**
     * Diritto "con asterisco" (allegato 4): diritti parziali di godimento.
     */
    public function isDirittoGodimento(): bool
    {
        return in_array($this, [self::abitazione, self::abitazioneSuperficiaria, self::uso, self::usoSuperficiaria, self::usufrutto, self::usufruttoSuperficiaria,
            self::usufruttoArea, self::oneriReali], true);
    }
}
