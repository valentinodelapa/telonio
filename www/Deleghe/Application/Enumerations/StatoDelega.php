<?php

namespace Deleghe\Application\Enumerations;

/**
 * Ciclo di vita della delega. Solo la bozza è modificabile e può essere
 * aggiornata dai moduli che generano versamenti; dalla conferma in poi gli
 * importi sono fissati.
 */
enum StatoDelega: string
{

    case bozza = 'B';
    case confermata = 'C';
    case trasmessa = 'T';
    case quietanzata = 'Q';

    public function getLabel(): string
    {
        return match ($this) {
            self::bozza => 'Bozza',
            self::confermata => 'Confermata',
            self::trasmessa => 'Trasmessa',
            self::quietanzata => 'Quietanzata',
        };
    }

    public function getClasseBadge(): string
    {
        return match ($this) {
            self::bozza => 'text-bg-secondary',
            self::confermata => 'text-bg-primary',
            self::trasmessa => 'text-bg-info',
            self::quietanzata => 'text-bg-success',
        };
    }

    public function isModificabile(): bool
    {
        return $this === self::bozza;
    }

    /**
     * Passaggi consentiti dallo stato: avanti di un passo, o indietro per
     * correggere (riaprire una delega confermata, annullare l'invio o la
     * registrazione della quietanza).
     *
     * @return StatoDelega[]
     */
    public function getPassaggi(): array
    {
        return match ($this) {
            self::bozza => [self::confermata],
            self::confermata => [self::bozza, self::trasmessa],
            self::trasmessa => [self::confermata, self::quietanzata],
            self::quietanzata => [self::trasmessa],
        };
    }

    public function getAzione(StatoDelega $da): string
    {
        $avanti = array_search($this, self::cases(), true) > array_search($da, self::cases(), true);
        return match ($this) {
            self::bozza => 'Riapri',
            self::confermata => $avanti ? 'Conferma' : 'Annulla invio',
            self::trasmessa => $avanti ? 'Segna come trasmessa' : 'Annulla quietanza',
            self::quietanzata => 'Registra quietanza',
        };
    }
}
