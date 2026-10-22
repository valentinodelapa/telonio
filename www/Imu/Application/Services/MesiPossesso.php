<?php

namespace Imu\Application\Services;

/**
 * Mesi di possesso nell'anno (art. 1, comma 761, L. 160/2019): si computa
 * per intero il mese in cui il possesso si è protratto per più della metà
 * dei giorni; il giorno del trasferimento si computa in capo
 * all'acquirente, al quale resta il mese se i giorni di possesso sono pari
 * a quelli del cedente.
 *
 * I periodi sono quelli di uno stesso possesso (stesso immobile e titolo)
 * con dati costanti: un mese che spetta al possessore si attribuisce al
 * periodo con più giorni nel mese (a parità, al successivo), così le
 * variazioni a metà mese (quota, rendita, utilizzo) seguono la stessa
 * regola dei trasferimenti.
 */
class MesiPossesso
{

    /**
     * @param array<int|string, array{0: \DateTimeInterface, 1: \DateTimeInterface}> $periodi
     *        dal-al inclusi, nell'anno, senza sovrapposizioni
     * @return array<int|string, int[]> numeri dei mesi attribuiti a ciascun periodo
     */
    public static function attribuisci(int $anno, array $periodi): array
    {
        $mesi = array_fill_keys(array_keys($periodi), []);
        for ($mese = 1; $mese <= 12; $mese++) {
            $inizio = new \DateTimeImmutable(sprintf('%d-%02d-01', $anno, $mese));
            $fine = $inizio->modify('last day of this month');
            $giorniMese = (int) $fine->format('j');
            $totale = 0;
            $finoAFineMese = false;
            $prevalente = null;
            $giorniPrevalente = 0;
            foreach ($periodi as $chiave => [$dal, $al]) {
                $giorni = self::giorniNelMese($dal, $al, $inizio, $fine);
                if ($giorni === 0) {
                    continue;
                }
                $totale += $giorni;
                $finoAFineMese = $finoAFineMese || ($al->format('Y-m-d') >= $fine->format('Y-m-d'));
                if ($giorni >= $giorniPrevalente) {
                    $prevalente = $chiave;
                    $giorniPrevalente = $giorni;
                }
            }
            if (($prevalente !== null) && ((2 * $totale > $giorniMese) || ((2 * $totale === $giorniMese) && $finoAFineMese))) {
                $mesi[$prevalente][] = $mese;
            }
        }
        return $mesi;
    }

    private static function giorniNelMese(\DateTimeInterface $dal, \DateTimeInterface $al, \DateTimeImmutable $inizio, \DateTimeImmutable $fine): int
    {
        $da = max($dal->format('Y-m-d'), $inizio->format('Y-m-d'));
        $a = min($al->format('Y-m-d'), $fine->format('Y-m-d'));
        if ($da > $a) {
            return 0;
        }
        return (int) (new \DateTimeImmutable($da))->diff(new \DateTimeImmutable($a))->days + 1;
    }
}
