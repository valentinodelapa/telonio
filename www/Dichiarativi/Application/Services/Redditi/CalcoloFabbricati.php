<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Redditi dei fabbricati (quadro RB, colonne da 13 a 19) secondo le
 * specifiche tecniche del modello Redditi PF 2026, par. 7.2.3 (un rigo) e
 * 7.2.4 (più righi dello stesso fabbricato: gli importi vanno sul primo).
 *
 * - 13 tassazione ordinaria, 14/15/16 cedolare secca al 21/10/26 per
 *   cento, 17 abitazione principale soggetta a IMU, 18 immobili non locati
 *   (IRPEF sostituita dall'IMU), 19 abitazione principale non soggetta a
 *   IMU (deduzione del rigo RN2).
 * - Utilizzo 2: rendita aumentata di un terzo; utilizzo 15: al 70 per
 *   cento; canone concordato e Abruzzo (8, 12, 14) senza cedolare al 70
 *   per cento; equo canone senza cedolare sempre sul canone; negli altri
 *   casi il maggiore tra rendita e canone (a parità, la rendita).
 */
class CalcoloFabbricati
{

    /**
     * @param RigoFabbricato[] $righi tutti i righi, nell'ordine del quadro
     */
    public function calcola(array $righi): void
    {
        $fabbricati = [];
        foreach ($righi as $rigo) {
            $fabbricati[(string) $rigo->fabbricato][] = $rigo;
        }
        foreach ($fabbricati as $righiFabbricato) {
            $redditi = array_fill_keys(range(13, 19), 0.0);
            $this->abitazionePrincipale($righiFabbricato, $redditi);
            $this->nonLocati($righiFabbricato, $redditi);
            $this->locati($righiFabbricato, $redditi);
            $this->abitazioneParzialmenteLocata($righiFabbricato, $redditi);
            foreach ($righiFabbricato as $indice => $rigo) {
                $rigo->redditi = $indice === 0 ? array_filter(array_map(fn(float $importo) => (int) round($importo), $redditi)) : [];
            }
        }
    }

    /**
     * Totali del rigo RB10 (colonne da 13 a 19) e imposte del rigo RB11.
     *
     * @param RigoFabbricato[] $righi
     * @return array{totali: array<int, int>, imposte: array<int, int>}
     */
    public function totali(array $righi): array
    {
        $totali = array_fill_keys(range(13, 19), 0);
        foreach ($righi as $rigo) {
            foreach ($rigo->redditi as $colonna => $importo) {
                $totali[$colonna] += $importo;
            }
        }
        $imposte = [1 => (int) round($totali[14] * 0.21), 2 => (int) round($totali[15] * 0.10), 3 => (int) round($totali[16] * 0.26)];
        $imposte[4] = $imposte[1] + $imposte[2] + $imposte[3];
        return ['totali' => $totali, 'imposte' => $imposte];
    }

    /**
     * Utilizzi 1, 5 e 6 (par. 7.2.4.1-7.2.4.4).
     */
    private function abitazionePrincipale(array $righi, array &$redditi): void
    {
        foreach ($righi as $rigo) {
            $quota = $rigo->getQuotaRendita();
            $imu = $rigo->casiImu === 2;
            if (in_array($rigo->utilizzo, [1, 5], true)) {
                $redditi[$imu ? 17 : 19] += $quota;
            } elseif ($rigo->utilizzo === 6) {
                $redditi[$imu ? 17 : 13] += $quota / 2;
                $redditi[$imu ? 18 : 19] += $quota / 2;
            }
        }
    }

    /**
     * Utilizzi 2, 9, 10 e 15 (par. 7.2.3.2, 7.2.3.7, 7.2.3.11, 7.2.4.7-7.2.4.9).
     */
    private function nonLocati(array $righi, array &$redditi): void
    {
        foreach ($righi as $rigo) {
            if (in_array($rigo->utilizzo, RigoFabbricato::UTILIZZI_NON_LOCATI, true) === false) {
                continue;
            }
            $quota = match ($rigo->utilizzo) {
                2 => $rigo->getQuotaRendita() * 4 / 3,
                15 => $rigo->getQuotaRendita() * 0.7,
                default => $rigo->casiParticolari === 1 ? 0.0 : $rigo->getQuotaRendita(),
            };
            match ($rigo->casiImu) {
                1 => $redditi[13] += $quota,
                3 => [$redditi[13] += $quota / 2, $redditi[18] += $quota / 2],
                default => $redditi[18] += $quota,
            };
        }
    }

    /**
     * Utilizzi 3, 4, 8 e 14 (par. 7.2.3.3-7.2.3.10, 7.2.4.10 e 7.2.4.11).
     */
    private function locati(array $righi, array &$redditi): void
    {
        $locati = array_filter($righi, fn(RigoFabbricato $rigo) => in_array($rigo->utilizzo, RigoFabbricato::UTILIZZI_LOCATI, true));
        // tassazione ordinaria
        $ordinari = array_filter($locati, fn(RigoFabbricato $rigo) => $rigo->cedolare === null);
        if (count($ordinari) > 0) {
            $redditi[13] += $this->locatiOrdinari($ordinari);
        }
        // cedolare secca: per aliquota il maggiore tra rendite e canoni
        $gruppi = [14 => [], 15 => [], 16 => []];
        foreach ($locati as $rigo) {
            if ($rigo->cedolare !== null) {
                $gruppi[match (true) { $rigo->utilizzo === 8 => 15, ($rigo->utilizzo === 3) && ($rigo->cedolare === 3) => 16, default => 14 }][] = $rigo;
            }
        }
        foreach ($gruppi as $colonna => $gruppo) {
            if (count($gruppo) > 0) {
                $redditi[$colonna] += max(self::sommaRendite($gruppo), self::sommaCanoni($gruppo));
            }
        }
    }

    private function locatiOrdinari(array $righi): float
    {
        $rendite = self::sommaRendite($righi);
        $canoni = self::sommaCanoni($righi);
        $utilizzi = array_map(fn(RigoFabbricato $rigo) => $rigo->utilizzo, $righi);
        if (count(array_filter($righi, fn(RigoFabbricato $rigo) => in_array($rigo->casiParticolari, [4, 7], true))) > 0) {
            // canoni non percepiti: rendita se nessun canone indicato, altrimenti il canone
            $conCanone = count(array_filter($righi, fn(RigoFabbricato $rigo) => $rigo->canone !== null)) > 0;
            return $conCanone ? $canoni : $rendite;
        }
        if (count(array_intersect($utilizzi, [8, 14])) > 0) {
            $base = $rendite >= $canoni ? 'getQuotaRendita' : 'getQuotaCanone';
            return array_sum(array_map(fn(RigoFabbricato $rigo) => $rigo->$base() * (in_array($rigo->utilizzo, [8, 14], true) ? 0.7 : 1.0), $righi));
        }
        if (in_array(3, $utilizzi, true)) {
            return max($rendite, $canoni);
        }
        // solo equo canone: sempre il canone
        return $canoni;
    }

    /**
     * Utilizzi 11 e 12 (par. 7.2.3.8, 7.2.3.9, 7.2.4.5 e 7.2.4.6).
     */
    private function abitazioneParzialmenteLocata(array $righi, array &$redditi): void
    {
        $parziali = array_filter($righi, fn(RigoFabbricato $rigo) => in_array($rigo->utilizzo, [11, 12], true));
        if (count($parziali) === 0) {
            return;
        }
        $imu = count(array_filter($parziali, fn(RigoFabbricato $rigo) => $rigo->casiImu !== 2)) === 0;
        $ordinari = array_filter($parziali, fn(RigoFabbricato $rigo) => $rigo->cedolare === null);
        if (count($ordinari) > 0) {
            $rendite = self::sommaRendite($ordinari);
            $canoni = self::sommaCanoni($ordinari);
            $concordato = in_array(12, array_map(fn(RigoFabbricato $rigo) => $rigo->utilizzo, $ordinari), true);
            $base = $rendite >= $canoni ? 'getQuotaRendita' : 'getQuotaCanone';
            $reddito = array_sum(array_map(fn(RigoFabbricato $rigo) => $rigo->$base() * ($concordato && ($rigo->utilizzo === 12) ? 0.7 : 1.0), $ordinari));
            $redditi[$rendite >= $canoni ? ($imu ? 17 : 19) : 13] += $reddito;
        }
        $gruppi = [14 => [], 15 => [], 16 => []];
        foreach ($parziali as $rigo) {
            if ($rigo->cedolare !== null) {
                $gruppi[match (true) { $rigo->utilizzo === 12 => 15, $rigo->cedolare === 3 => 16, default => 14 }][] = $rigo;
            }
        }
        foreach ($gruppi as $colonna => $gruppo) {
            if (count($gruppo) === 0) {
                continue;
            }
            $rendite = self::sommaRendite($gruppo);
            $canoni = self::sommaCanoni($gruppo);
            if ($rendite >= $canoni) {
                // tassazione sulla rendita, con la deduzione dell'abitazione principale (al 70 per cento per il concordato)
                $redditi[$imu ? 17 : 19] += $rendite * ($colonna === 15 ? 0.7 : 1.0);
            } else {
                $redditi[$colonna] += $canoni;
            }
        }
    }

    private static function sommaRendite(array $righi): float
    {
        return array_sum(array_map(fn(RigoFabbricato $rigo) => $rigo->getQuotaRendita(), $righi));
    }

    private static function sommaCanoni(array $righi): float
    {
        return array_sum(array_map(fn(RigoFabbricato $rigo) => $rigo->isCanoneNonPercepito() ? 0.0 : $rigo->getQuotaCanone(), $righi));
    }
}
