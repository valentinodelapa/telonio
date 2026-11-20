<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\ContrattoLeasing;

/**
 * Calcoli sui contratti di locazione finanziaria.
 *
 * Quota interessi dei canoni con il metodo forfetario (art. 1 D.M. 24
 * aprile 1998, richiamato per l'IRAP dall'art. 5 D.Lgs. 446/1997 e dalle
 * istruzioni del rigo IC44): (canoni complessivi + prezzo di riscatto -
 * costo sostenuto dalla concedente) diviso i giorni di durata del
 * contratto, per i giorni del contratto nell'anno.
 */
class Leasing
{

    /**
     * Giorni del contratto nell'anno (dall'inizio al riscatto o alla
     * scadenza).
     */
    public static function getGiorniNellAnno(ContrattoLeasing $contratto, int $anno): int
    {
        $dal = max(new \DateTimeImmutable($anno . '-01-01'), new \DateTimeImmutable($contratto->dataInizio->format('Y-m-d')));
        $al = min(new \DateTimeImmutable($anno . '-12-31'), new \DateTimeImmutable($contratto->getDataTermine()->format('Y-m-d')));
        return $al < $dal ? 0 : $dal->diff($al)->days + 1;
    }

    public static function getDurata(ContrattoLeasing $contratto): int
    {
        $inizio = new \DateTimeImmutable($contratto->dataInizio->format('Y-m-d'));
        return $inizio->diff(new \DateTimeImmutable($contratto->dataFine->format('Y-m-d')))->days + 1;
    }

    /**
     * Quota interessi dei canoni di competenza dell'anno (metodo forfetario).
     */
    public static function getQuotaInteressi(ContrattoLeasing $contratto, int $anno): float
    {
        $interessi = $contratto->totaleCanoni + $contratto->prezzoRiscatto - $contratto->costoConcedente;
        $durata = self::getDurata($contratto);
        if (($interessi <= 0) || ($durata <= 0)) {
            return 0.0;
        }
        return round($interessi / $durata * self::getGiorniNellAnno($contratto, $anno), 2);
    }

    /**
     * Parte dell'anno in cui il bene è in leasing.
     */
    public static function getQuotaPossesso(ContrattoLeasing $contratto, int $anno): float
    {
        $giorni = (new \DateTimeImmutable($anno . '-01-01'))->diff(new \DateTimeImmutable($anno . '-12-31'))->days + 1;
        return self::getGiorniNellAnno($contratto, $anno) / $giorni;
    }
}
