<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Redditi dei terreni del quadro RA di Redditi ENC 2026 (istruzioni, par.
 * 4.1, colonne 9 e 10): a differenza del Redditi PF il reddito dominicale
 * dei terreni non affittati concorre sempre (l'IMU sostituisce l'IRPEF, non
 * l'IRES) e non ci sono le colonne dei coltivatori diretti e IAP.
 *
 * - Colonna 9: quote del reddito dominicale rivalutato dei righi con il
 *   titolo 1 (il titolo 4, conduttore, non ha reddito dominicale) e, per i
 *   righi affittati (titoli 2 e 3), il canone in regime vincolistico se
 *   inferiore all'80 per cento del dominicale, con la doppia
 *   determinazione per la perdita di prodotto (CalcoloTerreni::affittati).
 * - Colonna 10: quote del reddito agrario rivalutato.
 *
 * Gli importi vanno sul primo rigo di ciascun terreno.
 */
class CalcoloTerreniEnc
{

    /**
     * @param RigoTerreno[] $righi
     */
    public function calcola(array $righi): void
    {
        $terreni = [];
        foreach ($righi as $rigo) {
            $rigo->iap = false;
            $terreni[(string) $rigo->terreno][] = $rigo;
        }
        $calcolo = new CalcoloTerreni();
        foreach ($terreni as $righiTerreno) {
            $dominicale = 0.0;
            $agrario = 0.0;
            foreach ($righiTerreno as $rigo) {
                if ($rigo->titolo === 1) {
                    $dominicale += $rigo->getQuotaDominicale();
                }
                $agrario += $rigo->getQuotaAgrario();
            }
            $affittati = array_filter($righiTerreno, fn(RigoTerreno $rigo) => $rigo->isAffittato());
            if (count($affittati) > 0) {
                $dominicale += $calcolo->affittati($affittati);
            }
            foreach ($righiTerreno as $indice => $rigo) {
                $rigo->redditi = $indice === 0 ? array_filter([9 => (int) round($dominicale), 10 => (int) round($agrario)]) : [];
            }
        }
    }

    /**
     * Rigo RA52: totali delle colonne 9 e 10.
     *
     * @param RigoTerreno[] $righi
     * @return array<int, int>
     */
    public function totali(array $righi): array
    {
        $totali = [9 => 0, 10 => 0];
        foreach ($righi as $rigo) {
            foreach ($rigo->redditi as $colonna => $importo) {
                $totali[$colonna] += $importo;
            }
        }
        return $totali;
    }
}
