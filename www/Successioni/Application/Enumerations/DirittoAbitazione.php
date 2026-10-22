<?php

namespace Successioni\Application\Enumerations;

/**
 * Diritto di abitazione del coniuge superstite e agevolazione prima casa
 * (quadro EC; tabella DirittoAbitazione_Type delle specifiche SUC13).
 */
enum DirittoAbitazione: string
{

    case primaCasaPrincipale = '1';
    case primaCasaContiguo = '2';
    case primaCasaPertinenza = '3';
    case senzaPrimaCasa = '4';
    case rinunciatarioPrincipale = '5';
    case rinunciatarioContiguo = '6';
    case rinunciatarioPertinenza = '7';
    case rinunciatarioSenzaPrimaCasa = '8';

    public function getLabel(): string
    {
        return match ($this) {
            self::primaCasaPrincipale => 'Il coniuge chiede la prima casa sull\'immobile principale (P)',
            self::primaCasaContiguo => 'Il coniuge chiede la prima casa sull\'immobile contiguo (Z)',
            self::primaCasaPertinenza => 'Il coniuge chiede la prima casa sulla pertinenza (X)',
            self::senzaPrimaCasa => 'Il coniuge non chiede la prima casa',
            self::rinunciatarioPrincipale => 'Il coniuge rinunciatario chiede la prima casa sull\'immobile principale (P)',
            self::rinunciatarioContiguo => 'Il coniuge rinunciatario chiede la prima casa sull\'immobile contiguo (Z)',
            self::rinunciatarioPertinenza => 'Il coniuge rinunciatario chiede la prima casa sulla pertinenza (X)',
            self::rinunciatarioSenzaPrimaCasa => 'Il coniuge rinunciatario non chiede la prima casa',
        };
    }

    /**
     * Agevolazione prima casa che il diritto comporta (null se non
     * richiesta).
     */
    public function getAgevolazione(): ?Agevolazione
    {
        return match ($this) {
            self::primaCasaPrincipale, self::rinunciatarioPrincipale => Agevolazione::primaCasa,
            self::primaCasaContiguo, self::rinunciatarioContiguo => Agevolazione::primaCasaContiguo,
            self::primaCasaPertinenza, self::rinunciatarioPertinenza => Agevolazione::pertinenzaPrimaCasa,
            default => null,
        };
    }

    public function isRinunciatario(): bool
    {
        return (int) $this->value >= 5;
    }
}
