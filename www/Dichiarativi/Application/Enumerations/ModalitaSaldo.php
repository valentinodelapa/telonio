<?php

namespace Dichiarativi\Application\Enumerations;

/**
 * Modalità di versamento del saldo IVA: in unica soluzione entro il 16
 * marzo, a rate mensili dal 16 marzo (art. 20 D.Lgs. 241/1997), o differito
 * al termine del saldo delle imposte sui redditi con la maggiorazione dello
 * 0,40% per mese o frazione dal 16 marzo (art. 6, c. 1, D.P.R. 542/1999;
 * art. 17, c. 1 e 2, D.P.R. 435/2001), anche a rate.
 */
enum ModalitaSaldo: string
{

    case unicaSoluzione = 'U';
    case rate = 'R';
    case differito = 'D';

    public function getLabel(): string
    {
        return match ($this) {
            self::unicaSoluzione => 'Unica soluzione entro il 16 marzo',
            self::rate => 'A rate mensili dal 16 marzo',
            self::differito => 'Differito al termine delle imposte sui redditi (+0,40% per mese o frazione)',
        };
    }

    /**
     * Modalità per saldo e primo acconto del modello Redditi: entro il 30
     * giugno, a rate mensili fino a dicembre (art. 20 D.Lgs. 241/1997) o nei
     * 30 giorni successivi con la maggiorazione dello 0,40% (art. 17, c. 2,
     * D.P.R. 435/2001), anche a rate.
     */
    public function getLabelRedditi(): string
    {
        return match ($this) {
            self::unicaSoluzione => 'Unica soluzione entro il 30 giugno',
            self::rate => 'A rate mensili dal 30 giugno',
            self::differito => 'Entro il 30 luglio con la maggiorazione dello 0,40% (anche a rate)',
        };
    }
}
