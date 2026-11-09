<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Redditi dei terreni (quadro RA, colonne da 11 a 15 e rigo RA23) secondo
 * le specifiche tecniche del modello Redditi PF 2026, par. 6.2 e 6.3.
 *
 * - Reddito dominicale dei terreni non affittati: sostituito dall'IMU
 *   (col. 13) salvo che l'IMU non sia dovuta (col. 11, o col. 14 per
 *   coltivatori diretti e IAP); dei terreni affittati il canone in regime
 *   vincolistico se inferiore all'80 per cento del dominicale (col. 11 o
 *   14), con la doppia determinazione per la perdita di prodotto.
 * - Reddito agrario: col. 12 (o 15 per CD e IAP).
 * - Rigo RA23: i redditi dei CD e IAP concorrono per lo 0 per cento fino a
 *   10.000 euro, per il 50 per cento fino a 15.000, per intero oltre (art.
 *   1, c. 44, L. 232/2016), ripartiti tra dominicale e agrario.
 */
class CalcoloTerreni
{

    /**
     * @param RigoTerreno[] $righi
     */
    public function calcola(array $righi): void
    {
        $terreni = [];
        foreach ($righi as $rigo) {
            $terreni[(string) $rigo->terreno][] = $rigo;
        }
        foreach ($terreni as $righiTerreno) {
            $redditi = array_fill_keys(range(11, 15), 0.0);
            $imuNonDovuta = $righiTerreno[0]->imuNonDovuta;
            foreach ($righiTerreno as $rigo) {
                if (in_array($rigo->titolo, [1, 7, 8, 9], true)) {
                    $redditi[$imuNonDovuta ? ($rigo->iap ? 14 : 11) : 13] += $rigo->getQuotaDominicale();
                }
                $redditi[$rigo->iap ? 15 : 12] += $rigo->getQuotaAgrario();
            }
            foreach ([false => 11, true => 14] as $iap => $colonna) {
                $affittati = array_filter($righiTerreno, fn(RigoTerreno $rigo) => $rigo->isAffittato() && ($rigo->iap === (bool) $iap));
                if (count($affittati) > 0) {
                    $redditi[$colonna] += $this->affittati($affittati);
                }
            }
            foreach ($righiTerreno as $indice => $rigo) {
                $rigo->redditi = $indice === 0 ? array_filter(array_map(fn(float $importo) => (int) round($importo), $redditi)) : [];
            }
        }
    }

    /**
     * Terreni affittati (specifiche 6.2.1.1.2, 6.2.1.2.4 e 6.2.1.2.5).
     *
     * @param RigoTerreno[] $righi
     */
    private function affittati(array $righi): float
    {
        $imponibileA = array_sum(array_map(fn(RigoTerreno $rigo) => $rigo->getQuotaDominicale(), $righi));
        if (count(array_filter($righi, fn(RigoTerreno $rigo) => $rigo->titolo === 2)) === 0) {
            return $imponibileA;
        }
        $canoni = array_sum(array_map(fn(RigoTerreno $rigo) => $rigo->titolo === 2 ? $rigo->canone * $rigo->percentuale / 100 : $rigo->getQuotaDominicale(), $righi));
        if (count(array_filter($righi, fn(RigoTerreno $rigo) => $rigo->isPerditaEventi())) === 0) {
            return $canoni < 0.8 * $imponibileA ? $canoni : $imponibileA;
        }
        $nonAgevolato = array_sum(array_map(fn(RigoTerreno $rigo) => $rigo->getQuotaDominicaleNonAgevolata(), $righi));
        $imponibileB = $canoni < 0.8 * $nonAgevolato ? $canoni : $nonAgevolato;
        return min($imponibileA, $imponibileB);
    }

    /**
     * Rigo RA23 (specifiche 6.3), senza redditi esenti attribuiti per
     * trasparenza da società semplici (rigo RH26).
     *
     * @param RigoTerreno[] $righi
     * @return array<int, int> colonne da 11 a 15
     */
    public function totali(array $righi, int $esenteTrasparenza = 0): array
    {
        $somme = array_fill_keys(range(11, 15), 0);
        foreach ($righi as $rigo) {
            foreach ($rigo->redditi as $colonna => $importo) {
                $somme[$colonna] += $importo;
            }
        }
        $totaleIap = $somme[14] + $somme[15];
        $imponibile = match (true) {
            $totaleIap <= 10000 => 0,
            $totaleIap <= 15000 => (int) round(($totaleIap - 10000) * 0.5),
            default => 2500 + ($totaleIap - 15000),
        };
        $esente = $totaleIap - $imponibile;
        $percentuale = $totaleIap > 0 ? $somme[14] / $totaleIap : 0.0;
        $dominicaleImponibile = (int) round($imponibile * $percentuale);
        $dominicaleEsente = (int) round($esente * $percentuale);
        $agrarioImponibile = $imponibile - $dominicaleImponibile;
        $agrarioEsente = $esente - $dominicaleEsente;
        $eccesso = $dominicaleEsente + $agrarioEsente + min(12500, $esenteTrasparenza) - 12500;
        if ($eccesso > 0) {
            $correttivoDominicale = (int) round($eccesso * $percentuale);
            $dominicaleImponibile += $correttivoDominicale;
            $dominicaleEsente -= $correttivoDominicale;
            $agrarioImponibile += $eccesso - $correttivoDominicale;
            $agrarioEsente -= $eccesso - $correttivoDominicale;
        }
        return [
            11 => $somme[11] + $dominicaleImponibile,
            12 => $somme[12] + $agrarioImponibile,
            13 => $somme[13] + $dominicaleEsente + $agrarioEsente,
            14 => $somme[14],
            15 => $somme[15],
        ];
    }
}
