<?php

namespace Dichiarativi\Application\Services\Redditi;

/**
 * Reddito imponibile dei fabbricati del quadro RB di Redditi ENC 2026
 * (istruzioni, par. 5.2, colonna 10), sul primo rigo di ciascun fabbricato.
 *
 * - Senza canoni: rendita rivalutata del 5 per cento per i giorni e la
 *   percentuale di possesso, aumentata di un terzo per le unità tenute a
 *   disposizione (utilizzo 2, non per gli immobili storici), ridotta del 30
 *   per cento per i codici 8, 14 e 15, nulla per le unità distrutte o
 *   inagibili (utilizzo 9 con il caso particolare 1).
 * - Con almeno un canone: confronto tra il totale delle quote di rendita e
 *   quello delle quote di canone al netto delle spese di manutenzione
 *   ordinaria (colonna 7): con i codici 8 o 14 la base più alta, ridotta
 *   del 30 per cento per i righi con quei codici; con il codice 3 il
 *   maggiore dei due totali; con il solo equo canone (codice 4) i canoni.
 *   Canoni non percepiti (casi 4 e 7): il canone indicato, o la rendita se
 *   nessun canone è indicato.
 */
class CalcoloFabbricatiEnc
{

    /**
     * @param RigoFabbricato[] $righi
     */
    public function calcola(array $righi): void
    {
        $fabbricati = [];
        foreach ($righi as $rigo) {
            $fabbricati[(string) $rigo->fabbricato][] = $rigo;
        }
        foreach ($fabbricati as $righiFabbricato) {
            $reddito = (int) round($this->getReddito($righiFabbricato));
            foreach ($righiFabbricato as $indice => $rigo) {
                $rigo->redditi = ($indice === 0) && ($reddito > 0) ? [10 => $reddito] : [];
            }
        }
    }

    /**
     * Rigo RB35: totale della colonna 10.
     *
     * @param RigoFabbricato[] $righi
     */
    public function totale(array $righi): int
    {
        return array_sum(array_map(fn(RigoFabbricato $rigo) => $rigo->redditi[10] ?? 0, $righi));
    }

    /**
     * @param RigoFabbricato[] $righi
     */
    private function getReddito(array $righi): float
    {
        $validi = array_filter($righi, fn(RigoFabbricato $rigo) => !(($rigo->utilizzo === 9) && ($rigo->casiParticolari === 1)));
        $conCanone = array_filter($validi, fn(RigoFabbricato $rigo) => $rigo->canone !== null);
        if (count($conCanone) === 0) {
            return array_sum(array_map(fn(RigoFabbricato $rigo) => $this->getQuotaSenzaCanone($rigo), $validi));
        }
        $rendite = array_sum(array_map(fn(RigoFabbricato $rigo) => $this->getQuotaRendita($rigo), $validi));
        $canoni = array_sum(array_map(fn(RigoFabbricato $rigo) => $this->getQuotaCanone($rigo), $conCanone));
        $utilizzi = array_map(fn(RigoFabbricato $rigo) => $rigo->utilizzo, $validi);
        if (count(array_filter($validi, fn(RigoFabbricato $rigo) => in_array($rigo->casiParticolari, [4, 7], true))) > 0) {
            return $canoni;
        }
        if (count(array_intersect($utilizzi, [8, 14])) > 0) {
            $rendita = $rendite >= $canoni;
            return array_sum(array_map(fn(RigoFabbricato $rigo) => ($rendita ? $this->getQuotaRendita($rigo) : ($rigo->canone === null ? 0.0 : $this->getQuotaCanone($rigo)))
                            * (in_array($rigo->utilizzo, [8, 14], true) ? 0.7 : 1.0), $validi));
        }
        if (in_array(3, $utilizzi, true)) {
            return max($rendite, $canoni);
        }
        return $canoni;
    }

    /**
     * Quota della rendita rivalutata, aumentata di un terzo per le unità a
     * disposizione che non sono immobili storici.
     */
    private function getQuotaRendita(RigoFabbricato $rigo): float
    {
        return $rigo->getQuotaRendita() * (($rigo->utilizzo === 2) && ($rigo->storico === false) ? 4 / 3 : 1.0);
    }

    private function getQuotaSenzaCanone(RigoFabbricato $rigo): float
    {
        return $this->getQuotaRendita($rigo) * (in_array($rigo->utilizzo, [8, 14, 15], true) ? 0.7 : 1.0);
    }

    /**
     * Quota del canone (per intero con i casi 5 e 8) al netto delle spese di
     * manutenzione ordinaria.
     */
    private function getQuotaCanone(RigoFabbricato $rigo): float
    {
        return $rigo->getQuotaCanone() - $rigo->spese;
    }
}
