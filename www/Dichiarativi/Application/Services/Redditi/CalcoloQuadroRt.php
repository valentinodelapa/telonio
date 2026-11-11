<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Entities\CessioneFinanziaria;

/**
 * Quadro RT, sezioni II (plusvalenze al 26 per cento) e V (cripto-
 * attività), con le regole delle specifiche tecniche del modello Redditi
 * PF 2026 (righi RT52, RT57, sezioni II B e V B, sezione VII e paragrafo
 * 37):
 *
 * - righi RT11 e RT41: totali delle cessioni senza costo rideterminato;
 *   righi RT12 e RT42: una cessione con il costo rideterminato per rigo
 *   (una per modulo), che conta solo se in plusvalenza;
 * - cripto-attività detenute dal 2024 o prima (colonne 1 e 2): risultato
 *   ridotto di 2.000 euro; dal 2025 (colonne 3 e 4) per intero;
 * - minusvalenze degli anni precedenti (righi RT102 e RT105 della
 *   dichiarazione dell'anno prima, entro il quarto anno) usate dalla più
 *   vecchia fino alla plusvalenza (righi RT13 e RT43), con il residuo per
 *   anno nella sezione VII;
 * - imposta del 26 per cento (righi RT73 e RT89) al netto delle eccedenze
 *   d'imposta della dichiarazione precedente, fino all'imposta (righi RT15
 *   e RT45).
 *
 * Le sezioni I, III e IV (plusvalenze al 20 per cento, partecipazioni
 * qualificate dei regimi transitori, OICR) e le minusvalenze certificate
 * dagli intermediari (RT14 e RT44) restano a mano.
 */
final class CalcoloQuadroRt
{

    public const ALIQUOTA = 0.26;
    public const FRANCHIGIA_CRIPTO_ANTE_2025 = 2000;

    /**
     * @param CessioneFinanziaria[] $cessioni
     * @param array<int, int> $minusPrecedentiTitoli residui per anno della sezione II (colonne da 1 a 4 del rigo RT102, dalla più vecchia)
     * @param array<int, int> $minusPrecedentiCripto residui della sezione V (colonne 3 e 4 del rigo RT105)
     * @return array{campi: array<string, int|bool>, righe12: list<array<int, int|bool>>, righe42: list<array<int, int>>}
     */
    public static function calcola(array $cessioni, array $minusPrecedentiTitoli = [], array $minusPrecedentiCripto = [], int $eccedenzaTitoli = 0, int $eccedenzaCripto = 0): array
    {
        $campi = [];
        $righe12 = $righe42 = [];
        $titoli = ['corrispettivi' => 0.0, 'costi' => 0.0, 'plus' => 0.0, 'privilegiate' => 0.0];
        $cripto = ['ante' => [0.0, 0.0, 0.0], 'nuove' => [0.0, 0.0, 0.0]];
        foreach ($cessioni as $cessione) {
            if ($cessione->tipo === CessioneFinanziaria::TITOLI) {
                if ($cessione->costoRideterminato) {
                    $righe12[] = [1 => true, 3 => (int) round($cessione->corrispettivo), 4 => (int) round($cessione->costo)];
                    $titoli['plus'] += max(0, round($cessione->corrispettivo) - round($cessione->costo));
                } else {
                    $titoli['corrispettivi'] += $cessione->corrispettivo;
                    $titoli['costi'] += $cessione->costo;
                }
                if ($cessione->paesePrivilegiato && ($cessione->getDifferenza() > 0)) {
                    $titoli['privilegiate'] += $cessione->getDifferenza();
                }
                continue;
            }
            $periodo = $cessione->anteriore2025 ? 'ante' : 'nuove';
            if ($cessione->costoRideterminato) {
                $offset = $cessione->anteriore2025 ? 0 : 2;
                $righe42[] = [1 + $offset => (int) round($cessione->corrispettivo), 2 + $offset => (int) round($cessione->costo)];
                $cripto[$periodo][2] += max(0, round($cessione->corrispettivo) - round($cessione->costo));
            } else {
                $cripto[$periodo][0] += $cessione->corrispettivo;
                $cripto[$periodo][1] += $cessione->costo;
            }
        }
        // sezione II A e II B
        if (($titoli['corrispettivi'] > 0) || ($titoli['costi'] > 0)) {
            $campi['RT011001'] = (int) round($titoli['corrispettivi']);
            $campi['RT011002'] = (int) round($titoli['costi']);
        }
        if (($titoli['corrispettivi'] > 0) || (count($righe12) > 0)) {
            $risultato = (int) (($campi['RT011001'] ?? 0) - ($campi['RT011002'] ?? 0) + $titoli['plus']);
            $campi['RT016001'] = (int) round($titoli['privilegiate']);
            $campi[$risultato > 0 ? 'RT052002' : 'RT052001'] = abs($risultato);
            $plus = max(0, $risultato);
            [$usate, $residui] = self::usaMinusvalenze($minusPrecedentiTitoli, $plus);
            $campi['RT013001'] = $usate;
            $campi['RT071003'] = $usate;
            $campi['RT072001'] = max(0, $plus - $usate);
            $campi['RT073001'] = (int) round($campi['RT072001'] * self::ALIQUOTA);
            $campi['RT015001'] = min($eccedenzaTitoli, $campi['RT073001']);
            $campi['RT074001'] = $campi['RT073001'] - $campi['RT015001'];
            foreach ($residui as $colonna => $residuo) {
                $campi[sprintf('RT102%03d', $colonna)] = $residuo;
            }
            $campi['RT102005'] = $risultato < 0 ? abs($risultato) : 0;
        } else {
            foreach (self::usaMinusvalenze($minusPrecedentiTitoli, 0)[1] as $colonna => $residuo) {
                $campi[sprintf('RT102%03d', $colonna)] = $residuo;
            }
        }
        // sezione V A e V B (paragrafo 37 delle specifiche)
        $haCripto = array_sum($cripto['ante']) + array_sum($cripto['nuove']) > 0;
        if ($haCripto) {
            foreach (['ante' => [1, 2], 'nuove' => [3, 4]] as $periodo => [$colCorrispettivi, $colCosti]) {
                if (($cripto[$periodo][0] > 0) || ($cripto[$periodo][1] > 0)) {
                    $campi[sprintf('RT041%03d', $colCorrispettivi)] = (int) round($cripto[$periodo][0]);
                    $campi[sprintf('RT041%03d', $colCosti)] = (int) round($cripto[$periodo][1]);
                }
            }
            $ante = (int) round($cripto['ante'][0]) - (int) round($cripto['ante'][1]) + (int) $cripto['ante'][2];
            $plusAnte = $ante >= 0 ? max(0, $ante - self::FRANCHIGIA_CRIPTO_ANTE_2025) : 0;
            $minusAnte = $ante < 0 ? max(0, abs($ante) - self::FRANCHIGIA_CRIPTO_ANTE_2025) : 0;
            $nuove = (int) round($cripto['nuove'][0]) - (int) round($cripto['nuove'][1]) + (int) $cripto['nuove'][2];
            $totale = max(0, $nuove) + $plusAnte - max(0, -$nuove) - $minusAnte;
            $campi[$totale >= 0 ? 'RT057002' : 'RT057001'] = abs($totale);
            $plus = max(0, $totale);
            [$usate, $residui] = self::usaMinusvalenze($minusPrecedentiCripto, $plus);
            $campi['RT043001'] = $usate;
            $campi['RT088001'] = max(0, $plus - $usate);
            $campi['RT089001'] = (int) round($campi['RT088001'] * self::ALIQUOTA);
            $campi['RT045001'] = min($eccedenzaCripto, $campi['RT089001']);
            $campi['RT090001'] = $campi['RT089001'] - $campi['RT045001'];
            foreach ($residui as $colonna => $residuo) {
                $campi[sprintf('RT105%03d', $colonna)] = $residuo;
            }
            $campi['RT105005'] = $totale < 0 ? abs($totale) : 0;
        } else {
            foreach (self::usaMinusvalenze($minusPrecedentiCripto, 0)[1] as $colonna => $residuo) {
                $campi[sprintf('RT105%03d', $colonna)] = $residuo;
            }
        }
        return ['campi' => array_filter($campi, fn($valore) => ($valore !== 0) && ($valore !== null)), 'righe12' => $righe12, 'righe42' => $righe42];
    }

    /**
     * Minusvalenze degli anni precedenti usate dalla più vecchia fino alla
     * plusvalenza.
     *
     * @param array<int, int> $residui colonna => importo, dalla più vecchia
     * @return array{0: int, 1: array<int, int>}
     */
    public static function usaMinusvalenze(array $residui, int $plusvalenza): array
    {
        ksort($residui);
        $usate = 0;
        foreach ($residui as $colonna => $importo) {
            $quota = min($importo, $plusvalenza - $usate);
            $usate += $quota;
            $residui[$colonna] = $importo - $quota;
        }
        return [$usate, $residui];
    }
}
