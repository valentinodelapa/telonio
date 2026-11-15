<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Entities\CessioneFinanziaria;
use Dichiarativi\Application\Services\QuadriDichiarazione;

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
     * Righi calcolati del quadro RT su tutti i righi compilati di tutti i
     * moduli, proposti dalle cessioni o inseriti a mano (specifiche: righi
     * da RT51 a RT57, sezioni I B, II B, III B, IV B e V B, colonna 5 della
     * sezione VII e rigo RT113):
     *
     * - plusvalenza o minusvalenza di ogni sezione: totali dei corrispettivi
     *   meno i costi più le plusvalenze dei righi con il costo rideterminato
     *   o affrancato, meno le minusvalenze dei righi con il costo affrancato
     *   (sezioni I e II); per le partecipazioni qualificate dei regimi
     *   transitori (sezione III) un risultato per periodo, con le quote
     *   imponibili del 40, 49,72 e 58,14 per cento nel rigo RT82;
     * - compensazione tra le sezioni I e II (righi RT61 e RT71, minusvalenze
     *   della sezione I per 0,7692 e della sezione III) e imposte del 20 e
     *   del 26 per cento (righi RT63, RT64, RT73, RT74, RT89 e RT90);
     * - minusvalenze dell'anno da riportare (col. 5 dei righi da RT101 a
     *   RT105) e imposta sostitutiva versata sulle partecipazioni
     *   qualificate (RT113, nel rigo RN33).
     */
    public static function proponiTotali(QuadriDichiarazione $quadri): void
    {
        $moduli = $quadri->getModuli();
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $quadri->usaModulo(1);
        // righi ripetibili: plusvalenze (e minusvalenze con il costo affrancato) dei righi con il costo rideterminato
        $righi = function (string $rigo, ?string $affrancato, int $colCorrispettivo = 1, int $colCosto = 2) use ($quadri, $moduli): array {
            $plus = $minus = 0;
            $compilato = false;
            foreach ($moduli as $modulo) {
                $corrispettivo = $quadri->getImporto(sprintf('%s%03d', $rigo, $colCorrispettivo), $modulo);
                $costo = $quadri->getImporto(sprintf('%s%03d', $rigo, $colCosto), $modulo);
                if (($corrispettivo === 0) && ($costo === 0)) {
                    continue;
                }
                $compilato = true;
                $differenza = $corrispettivo - $costo;
                if ($differenza > 0) {
                    $plus += $differenza;
                } elseif (($affrancato !== null) && ($quadri->get($affrancato, $modulo) !== null)) {
                    $minus += -$differenza;
                }
            }
            return [$plus, $minus, $compilato];
        };
        $risultato = function (int $valore, bool $compilato, string $minusCodice, string $plusCodice) use ($quadri): void {
            $quadri->proponi($plusCodice, ($compilato && $valore > 0) ? $valore : null);
            $quadri->proponi($minusCodice, ($compilato && $valore <= 0) ? (-$valore ?: null) : null);
        };
        // sezione I (20 per cento) e II (26 per cento)
        [$plus, $minus, $righiI] = $righi('RT002', 'RT002002', 3, 4);
        $risultato($q('RT001001') - $q('RT001002') + $plus - $minus, $righiI || ($q('RT001001') + $q('RT001002') > 0), 'RT051001', 'RT051002');
        [$plus, $minus, $righiII] = $righi('RT012', 'RT012002', 3, 4);
        $risultato($q('RT011001') - $q('RT011002') + $plus - $minus, $righiII || ($q('RT011001') + $q('RT011002') > 0), 'RT052001', 'RT052002');
        // sezione III: un risultato per periodo di realizzo
        foreach ([[21, 22, 53], [24, 25, 54], [27, 28, 55]] as [$totali, $rigo, $risultatoRigo]) {
            [$plus, , $compilati] = $righi(sprintf('RT%03d', $rigo), null);
            $totale = sprintf('RT%03d', $totali);
            $risultato($q($totale . '001') - $q($totale . '002') + $plus, $compilati || ($q($totale . '001') + $q($totale . '002') > 0),
                    sprintf('RT%03d001', $risultatoRigo), sprintf('RT%03d002', $risultatoRigo));
        }
        // sezione IV
        [$plus, , $righiIV] = $righi('RT032', null);
        $risultato($q('RT031001') - $q('RT031002') + $q('RT033001') - $q('RT033002') + $plus,
                $righiIV || ($q('RT031001') + $q('RT031002') + $q('RT033001') + $q('RT033002') > 0), 'RT056001', 'RT056002');
        // sezione V (paragrafo 37): cripto-attività detenute prima del 2025 con la franchigia di 2.000 euro
        [$plusAnte, , $righiAnte] = $righi('RT042', null, 1, 2);
        [$plusNuove, , $righiNuove] = $righi('RT042', null, 3, 4);
        if ($righiAnte || $righiNuove || ($q('RT041001') + $q('RT041002') + $q('RT041003') + $q('RT041004') > 0)) {
            $ante = $q('RT041001') - $q('RT041002') + $plusAnte;
            $nuove = $q('RT041003') - $q('RT041004') + $plusNuove;
            $totale = ($ante >= 0 ? max(0, $ante - self::FRANCHIGIA_CRIPTO_ANTE_2025) : 0) + max(0, $nuove)
                    - ($ante < 0 ? max(0, -$ante - self::FRANCHIGIA_CRIPTO_ANTE_2025) : 0) - max(0, -$nuove);
            $risultato($totale, true, 'RT057001', 'RT057002');
        }
        // sezione I B: compensazione con la minusvalenza della sezione II e imposta del 20 per cento
        $compensata = $q('RT052001') > 0 ? min($q('RT051002'), $q('RT052001')) : 0;
        $quadri->proponi('RT061001', $compensata ?: null);
        $quadri->proponi('RT061002', ($compensata + $q('RT003001') + $q('RT004002')) ?: null);
        $quadri->proponi('RT062001', max(0, $q('RT051002') - $q('RT061002')) ?: null);
        $quadri->proponi('RT063001', (int) round($q('RT062001') * 0.20) ?: null);
        $quadri->proponi('RT064001', max(0, $q('RT063001') - $q('RT005001')) ?: null);
        // sezione II B: compensazione con le minusvalenze delle sezioni I (per 0,7692) e III, imposta del 26 per cento
        $massimoI = (int) round($q('RT051001') * 0.7692);
        $quadri->proponi('RT071001', $massimoI > 0 ? (min($q('RT052002'), $massimoI) ?: null) : null);
        $massimoIII = $q('RT053001') + $q('RT054001') + $q('RT055001');
        $quadri->proponi('RT071002', $massimoIII > 0 ? (min($q('RT052002') - $q('RT071001'), $massimoIII) ?: null) : null);
        $quadri->proponi('RT071003', ($q('RT071001') + $q('RT071002') + $q('RT013001') + $q('RT014002')) ?: null);
        $quadri->proponi('RT072001', max(0, $q('RT052002') - $q('RT071003')) ?: null);
        $quadri->proponi('RT073001', (int) round($q('RT072001') * self::ALIQUOTA) ?: null);
        $quadri->proponi('RT074001', max(0, $q('RT073001') - $q('RT015001')) ?: null);
        // sezione III B: parte imponibile delle plusvalenze delle partecipazioni qualificate per periodo
        foreach ([[53, 23, 1, 0.40], [54, 26, 2, 0.4972], [55, 29, 3, 0.5814]] as [$rigo, $pregresse, $colonna, $quota]) {
            $differenza = max(0, $q(sprintf('RT%03d002', $rigo)) - $q(sprintf('RT%03d001', $pregresse)));
            $quadri->proponi(sprintf('RT081%03d', $colonna), $differenza ?: null);
            $quadri->proponi(sprintf('RT082%03d', $colonna), (int) round($differenza * $quota) ?: null);
        }
        // sezioni IV B e V B
        $quadri->proponi('RT086001', max(0, $q('RT056002') - $q('RT034001')) ?: null);
        $quadri->proponi('RT088001', max(0, $q('RT057002') - $q('RT043001') - $q('RT044002')) ?: null);
        $quadri->proponi('RT089001', (int) round($q('RT088001') * self::ALIQUOTA) ?: null);
        $quadri->proponi('RT090001', max(0, $q('RT089001') - $q('RT045001')) ?: null);
        // sezione VII, minusvalenze dell'anno, e imposta versata sulle partecipazioni qualificate
        $quadri->proponi('RT101005', max(0, $q('RT051001') - (int) round($q('RT071001') * 1.3)) ?: null);
        $quadri->proponi('RT102005', max(0, $q('RT052001') - $q('RT061001')) ?: null);
        $quadri->proponi('RT103005', max(0, $massimoIII - $q('RT071002')) ?: null);
        $quadri->proponi('RT104005', $q('RT056001') ?: null);
        $quadri->proponi('RT105005', $q('RT057001') ?: null);
        $quadri->proponi('RT113001', ($q('RT023002') + $q('RT026002') + $q('RT029002') + $q('RT035001')) ?: null);
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
