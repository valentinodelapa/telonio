<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Services\QuadriDichiarazione;

/**
 * Colonne calcolate del quadro RM sui righi inseriti a mano (o proposti) e
 * righi da RX7 a RX19, con le regole delle specifiche tecniche del modello
 * Redditi PF 2026 (campi del quadro RM, paragrafi 35.1 e 35.2, righi RX):
 *
 * - RM21: redditi della sezione I a tassazione separata (RM5 codici 8 e 9
 *   al netto delle deduzioni del 25 e del 15 per cento e dell'imposta di
 *   successione) e acconto del 20 per cento al netto di quanto trattenuto e
 *   delle ritenute sui pignoramenti (rigo RX12); RM22: redditi e ritenute
 *   con l'opzione per la tassazione ordinaria (rigo RN1 e RN33);
 * - imposte dei righi RM31 (col. 3 x col. 4 - col. 5), RM32, RM33 (20 per
 *   cento), RM34 (addizionale del 10 per cento meno quanto trattenuto),
 *   RM35 (20 per cento), RM36 (23 per cento), RM37 (lezioni private: 15
 *   per cento, saldo e acconti come per l'IRPEF), RM38 (frontalieri
 *   svizzeri: 25 per cento dell'imposta svizzera meno il contributo), RM39,
 *   RM61-RM62 (imprese estere partecipate) e RM82 (pignoramento);
 * - righi RX7-RX19 con i debiti e i crediti e i crediti in compensazione.
 *
 * Il rigo RM91 (TFR e indennità percepiti dagli eredi) e le opzioni dei
 * righi RM51-RM54 restano a mano.
 */
final class CalcoloQuadroRm
{

    public static function proponi(QuadriDichiarazione $quadri, bool $isa): void
    {
        $moduli = $quadri->getModuli();
        $q = fn(string $codice, ?int $modulo = 1) => $quadri->getImporto($codice, $modulo);
        $vuoto = fn(string $codice, int $modulo) => $quadri->get($codice, $modulo) === null;
        $separata = $ordinaria = $ritenuteOrdinarie = 0;
        $rm31 = $rm32 = $rm61 = $rm82 = $pignoramento = 0;
        foreach ($moduli as $modulo) {
            $quadri->usaModulo($modulo);
            // sezione I-A: tassazione separata o ordinaria (paragrafi 35.1 e 35.2)
            if ($vuoto('RM001007', $modulo)) {
                $separata += $vuoto('RM001006', $modulo) ? $q('RM001003', $modulo) : 0;
            } else {
                $ordinaria += $q('RM001003', $modulo);
                $ritenuteOrdinarie += $q('RM001005', $modulo);
            }
            if ($vuoto('RM002005', $modulo)) {
                $separata += $vuoto('RM002004', $modulo) ? $q('RM002003', $modulo) : 0;
            } else {
                $ordinaria += $q('RM002003', $modulo);
                $ritenuteOrdinarie += $q('RM002004', $modulo);
            }
            if ($vuoto('RM003004', $modulo)) {
                $separata += $q('RM003003', $modulo);
            } else {
                $ordinaria += $q('RM003003', $modulo);
            }
            if ($vuoto('RM004002', $modulo)) {
                $separata += $q('RM004001', $modulo);
            } else {
                $ordinaria += $q('RM004001', $modulo);
            }
            if ($vuoto('RM005006', $modulo)) {
                if ($vuoto('RM005005', $modulo)) {
                    $tipo = $q('RM005001', $modulo);
                    $reddito = $q('RM005003', $modulo);
                    $successione = $q('RM005004', $modulo);
                    $separata += match ($tipo) {
                        8 => max(0, (int) round($reddito * 0.75) - $successione),
                        9 => max(0, (int) round($reddito * 0.85) - $successione),
                        default => $reddito - $successione,
                    };
                }
            } else {
                $ordinaria += $q('RM005003', $modulo);
                $ritenuteOrdinarie += $q('RM005005', $modulo);
            }
            $separata += $q('RM006001', $modulo) + $q('RM007002', $modulo);
            if (($q('RM081B01', $modulo) >= 1) && ($q('RM081B01', $modulo) <= 7) && ($quadri->get('RM081A01', $modulo) === 'RM')) {
                $pignoramento += $q('RM081004', $modulo);
            }
            // sezione II-A, righi ripetibili nei moduli
            if ($quadri->get('RM031001', $modulo) !== null) {
                $imposta = (int) round($q('RM031003', $modulo) * $quadri->getDecimale('RM031004', $modulo) / 100) - $q('RM031005', $modulo);
                $quadri->proponi('RM031008', $vuoto('RM031007', $modulo) ? (max(0, $imposta) ?: null) : null);
                $rm31 += $vuoto('RM031007', $modulo) ? $q('RM031008', $modulo) : 0;
            }
            if ($q('RM032001', $modulo) > 0) {
                $quadri->proponi('RM032003', (int) round($q('RM032001', $modulo) * $quadri->getDecimale('RM032002', $modulo) / 100) ?: null);
                $rm32 += $q('RM032003', $modulo);
            }
            if ($q('RM061002', $modulo) > 0) {
                $quadri->proponi('RM061006', (int) round($q('RM061002', $modulo) * $quadri->getDecimale('RM061005', $modulo) / 100) ?: null);
                $quadri->proponi('RM061007', max(0, $q('RM061006', $modulo) - $q('RM061003', $modulo)) ?: null);
                $rm61 += $q('RM061007', $modulo);
            }
            if ($quadri->get('RM082001', $modulo) !== null) {
                $quadri->proponi('RM082005', (int) round($q('RM082002', $modulo) * $quadri->getDecimale('RM082004', $modulo) / 100) ?: null);
                $differenza = $q('RM082005', $modulo) - $q('RM082006', $modulo);
                $quadri->proponi('RM082007', $differenza > 0 ? $differenza : null);
                $quadri->proponi('RM082008', $differenza < 0 ? -$differenza : null);
                $rm82 += $q('RM082007', $modulo) - ($q('RM082003', $modulo) !== 3 ? $q('RM082008', $modulo) : 0);
            }
        }
        $quadri->usaModulo(1);
        $quadri->proponi('RM021001', $separata ?: null);
        $quadri->proponi('RM021003', max(0, (int) round($separata * 0.20) - $q('RM021002') - $pignoramento) ?: null);
        $quadri->proponi('RM022001', $ordinaria ?: null);
        $quadri->proponi('RM022002', $ritenuteOrdinarie ?: null);
        // imposte dei righi del primo modulo
        $quadri->proponi('RM033002', (int) round($q('RM033001') * 0.20) ?: null);
        $quadri->proponi('RM034004', (int) round($q('RM034002') * 0.10) ?: null);
        $quadri->proponi('RM034005', max(0, $q('RM034004') - $q('RM034003')) ?: null);
        $quadri->proponi('RM035003', (int) round($q('RM035001') * 0.20) ?: null);
        $quadri->proponi('RM036002', (int) round($q('RM036001') * 0.23) ?: null);
        if ($quadri->get('RM037006', 1) === null) {
            $lezioni = (int) round($q('RM037001') * 0.15);
            $quadri->proponi('RM037007', $lezioni ?: null);
            $saldo = $lezioni - $q('RM037002') + $q('RM037003') - $q('RM037004');
            $quadri->proponi('RM037008', $saldo > 0 ? $saldo : null);
            $quadri->proponi('RM037009', $saldo < 0 ? -$saldo : null);
            [$primo, $secondo] = VersamentiRedditi::acconti((float) $lezioni, $isa);
            $quadri->proponi('RM037010', (int) $primo ?: null);
            $quadri->proponi('RM037011', (int) $secondo ?: null);
        }
        $quadri->proponi('RM038004', max(0, (int) round($q('RM038002') * 0.25) - $q('RM038003')) ?: null);
        $quadri->proponi('RM039006', ($q('RM039003') + $q('RM039005')) ?: null);
        $quadri->proponi('RM062004', $rm61 ?: null);
        $cfc = $rm61 - $q('RM062001') + $q('RM062002') - $q('RM062003');
        $quadri->proponi('RM062005', $cfc > 0 ? $cfc : null);
        $quadri->proponi('RM062006', $cfc < 0 ? -$cfc : null);
        // quadro RX: righi da RX7 a RX19 (debito, credito e credito in compensazione)
        $righi = [7 => $rm31, 8 => $rm32, 9 => $q('RM033002'), 10 => array_sum(array_map(fn(int $modulo) => $q('RM091040', $modulo), $moduli)),
            11 => $q('RM038004'), 12 => $q('RM021003') + array_sum(array_map(fn(int $modulo) => $q('RM091021', $modulo) + $q('RM091038', $modulo), $moduli)),
            13 => $q('RM039006'), 14 => $q('RM034005'), 15 => $q('RM062005') - $q('RM062006'),
            16 => $rm82 + $q('RM036002'), 17 => $q('RM035003'), 18 => $q('RM037008') - $q('RM037009'), 19 => $q('RM054004')];
        foreach ($righi as $rigo => $imposta) {
            $rx = fn(int $colonna) => sprintf('RX%03d%03d', $rigo, $colonna);
            $quadri->proponi($rx(1), $imposta > 0 ? $imposta : null);
            if (in_array($rigo, [15, 16, 18], true)) {
                $quadri->proponi($rx(2), $imposta < 0 ? -$imposta : null);
                $quadri->proponi($rx(5), max(0, max(0, -$imposta) + $q($rx(3)) - $q($rx(4))) ?: null);
            }
        }
    }
}
