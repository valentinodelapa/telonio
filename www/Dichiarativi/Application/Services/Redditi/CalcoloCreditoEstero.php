<?php

namespace Dichiarativi\Application\Services\Redditi;

use Dichiarativi\Application\Services\QuadriDichiarazione;

/**
 * Credito per le imposte pagate all'estero (art. 165, comma 1, Tuir),
 * quadro CE sezione I, con le regole del paragrafo 38 delle specifiche
 * tecniche del modello Redditi PF 2026:
 *
 * - righi da CE1 a CE3 di tutti i moduli (proposti o inseriti a mano): per
 *   l'anno della dichiarazione reddito complessivo (RN1 col. 2 + col. 5 -
 *   col. 3), imposta lorda (RN5) e netta (RN26); quota di imposta lorda
 *   (col. 10) = reddito estero / reddito complessivo (al massimo 1) per
 *   l'imposta lorda; credito (col. 11) = minore tra l'imposta estera e la
 *   quota meno il credito già usato per lo Stato (col. 9); col. 13 per il
 *   credito indiretto (col. 15);
 * - righi CE4 e CE5, uno per anno di produzione: totale delle col. 11 o
 *   13, capienza nell'imposta netta (col. 7 - col. 8) e credito usato,
 *   il minore dei due;
 * - righi CE23 e CE26 (con CE24 e CE25 a mano) e credito del rigo RN29,
 *   col. 2.
 *
 * L'imposta estera oltre il 43 per cento del reddito estero (aliquota
 * marginale più elevata) è segnalata. La sezione II (imprese, art. 165,
 * comma 6) resta a mano.
 */
final class CalcoloCreditoEstero
{

    public const ALIQUOTA_MASSIMA = 0.43;

    /**
     * @return string[] avvisi
     */
    public static function proponi(QuadriDichiarazione $quadri, int $anno): array
    {
        $avvisi = [];
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $perAnno = [];
        foreach ($quadri->getModuli() as $modulo) {
            for ($rigo = 1; $rigo <= 3; $rigo++) {
                $c = fn(int $colonna) => sprintf('CE%03d%03d', $rigo, $colonna);
                if ($quadri->get($c(1), $modulo) === null) {
                    continue;
                }
                $quadri->usaModulo($modulo);
                $annoReddito = $quadri->getImporto($c(2), $modulo);
                if ($annoReddito === $anno) {
                    $quadri->proponi($c(5), ($q('RN001002') + $q('RN001005') - $q('RN001003')) ?: null);
                    $quadri->proponi($c(6), $q('RN005001') ?: null);
                    $quadri->proponi($c(7), $q('RN026001') ?: null);
                }
                $reddito = $quadri->getImporto($c(3), $modulo);
                $imposta = $quadri->getImporto($c(4), $modulo);
                $complessivo = $quadri->getImporto($c(5), $modulo);
                if ($imposta > round($reddito * self::ALIQUOTA_MASSIMA)) {
                    $avvisi[] = 'Quadro CE: imposta estera oltre il 43 per cento del reddito estero (rigo CE' . $rigo . ', modulo ' . $modulo . '): non può superare l\'imposta italiana all\'aliquota marginale più elevata.';
                }
                $rapporto = $complessivo > 0 ? min(1.0, $reddito / $complessivo) : 0.0;
                $quota = (int) round($rapporto * $quadri->getImporto($c(6), $modulo));
                $quadri->proponi($c(10), $quota ?: null);
                $credito = min($imposta, max(0, $quota - $quadri->getImporto($c(9), $modulo)));
                $quadri->proponi($c(11), $credito ?: null);
                $indiretto = $quadri->get($c(15), $modulo) !== null;
                if ($indiretto) {
                    $quadri->proponi($c(13), max(0, $credito - $quadri->getImporto($c(12), $modulo)) ?: null);
                }
                $perAnno[$annoReddito] ??= ['credito' => 0, 'capienza' => $quadri->getImporto($c(7), $modulo) - $quadri->getImporto($c(8), $modulo)];
                $perAnno[$annoReddito]['credito'] += $indiretto ? $quadri->getImporto($c(13), $modulo) : $credito;
            }
        }
        $quadri->usaModulo(1);
        if (count($perAnno) === 0) {
            return $avvisi;
        }
        if (count($perAnno) > 2) {
            $avvisi[] = 'Quadro CE: redditi esteri di più di due anni, i righi CE4 e CE5 degli anni oltre il secondo vanno nei moduli successivi, a mano.';
        }
        ksort($perAnno);
        $usato = 0;
        $numero = 4;
        foreach (array_slice($perAnno, 0, 2, true) as $annoReddito => $dati) {
            $c = fn(int $colonna) => sprintf('CE%03d%03d', $numero, $colonna);
            $quadri->proponi($c(1), $annoReddito);
            $quadri->proponi($c(2), $dati['credito'] ?: null);
            $quadri->proponi($c(3), max(0, $dati['capienza']) ?: null);
            $credito = max(0, min($dati['credito'], $dati['capienza']));
            $quadri->proponi($c(4), $credito ?: null);
            $usato += $credito;
            $numero++;
        }
        $quadri->proponi('CE023001', $usato ?: null);
        $totale = $q('CE023001') + $q('CE024001') - $q('CE025001');
        $quadri->proponi('CE026001', $totale > 0 ? $totale : null);
        $estero = $q('CE026001') + $q('RF102003') + $q('RG037003') + $q('RH021001') + $q('RL004004') - $q('LM040002');
        $quadri->proponi('RN029002', $estero > 0 ? $estero : null);
        return $avvisi;
    }
}
