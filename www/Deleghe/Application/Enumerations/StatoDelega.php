<?php

namespace Deleghe\Application\Enumerations;

/**
 * Ciclo di vita della delega. Solo la bozza è modificabile e può essere
 * aggiornata dai moduli che generano versamenti; dalla conferma in poi gli
 * importi sono fissati.
 *
 * Dalla conferma il percorso è uno dei due, lineari:
 * - trasmessa dallo studio con Entratel, poi quietanzata;
 * - consegnata al cliente, che la paga da sé, poi quietanzata con la
 *   ricevuta del cliente.
 */
enum StatoDelega: string
{

    case bozza = 'B';
    case confermata = 'C';
    case trasmessa = 'T';
    case consegnata = 'K';
    case quietanzata = 'Q';

    public function getLabel(): string
    {
        return match ($this) {
            self::bozza => 'Bozza',
            self::confermata => 'Confermata',
            self::trasmessa => 'Trasmessa',
            self::consegnata => 'Consegnata',
            self::quietanzata => 'Quietanzata',
        };
    }

    public function getClasseBadge(): string
    {
        return match ($this) {
            self::bozza => 'text-bg-secondary',
            self::confermata => 'text-bg-primary',
            self::trasmessa => 'text-bg-info',
            self::consegnata => 'text-bg-warning',
            self::quietanzata => 'text-bg-success',
        };
    }

    public function isModificabile(): bool
    {
        return $this === self::bozza;
    }

    /**
     * Passaggi consentiti dallo stato: avanti di un passo, o indietro per
     * correggere (riaprire una delega confermata, annullare l'invio, la
     * consegna o la registrazione della quietanza). La quietanza torna allo
     * stato da cui viene: consegnata se la delega è stata consegnata al
     * cliente, altrimenti trasmessa.
     *
     * @return StatoDelega[]
     */
    public function getPassaggi(bool $consegnata = false): array
    {
        return match ($this) {
            self::bozza => [self::confermata],
            self::confermata => [self::bozza, self::trasmessa, self::consegnata],
            self::trasmessa => [self::confermata, self::quietanzata],
            self::consegnata => [self::confermata, self::quietanzata],
            self::quietanzata => [$consegnata ? self::consegnata : self::trasmessa],
        };
    }

    public function isAvanti(StatoDelega $da): bool
    {
        return array_search($this, self::cases(), true) > array_search($da, self::cases(), true);
    }

    public function getAzione(StatoDelega $da): string
    {
        $avanti = $this->isAvanti($da);
        return match ($this) {
            self::bozza => 'Riapri',
            self::confermata => match ($da) {
                self::bozza => 'Conferma',
                self::consegnata => 'Annulla consegna',
                default => 'Annulla invio',
            },
            self::trasmessa => $avanti ? 'Segna come trasmessa' : 'Annulla quietanza',
            self::consegnata => $avanti ? 'Consegna al cliente' : 'Annulla quietanza',
            self::quietanzata => 'Registra quietanza',
        };
    }
}
