<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Enumerations\VoceCee;

/**
 * Righi dei quadri RG (impresa in contabilità semplificata) e RE (arti e
 * professioni) del modello Redditi in cui confluiscono i conti economici:
 * "RG15", o "RG22:3" per i righi con il codice della voce (RG10 altri
 * componenti positivi, RG22 altri componenti negativi; codici dalle
 * istruzioni Redditi PF 2026, fascicolo 3).
 *
 * Il rigo si indica sul conto; in mancanza vale quello proposto dalla voce
 * dello schema di conto economico (art. 2425 c.c.). "NO" esclude il conto
 * (componente non deducibile o non imponibile).
 */
final class RighiRedditi
{

    public const ESCLUSO = 'NO';

    /** Righi del quadro RF per la parte indeducibile dei costi (variazioni in aumento) */
    public const VARIAZIONI = [
        'RF16' => 'RF16 - Imposte indeducibili',
        'RF18' => 'RF18 - Mezzi di trasporto (art. 164 Tuir)',
        'RF31:99' => 'RF31 codice 99 - Altre variazioni in aumento (es. telefonia, art. 102, comma 9)',
    ];

    /**
     * @return array<string, string> rigo => descrizione
     */
    public static function getRighi(): array
    {
        return [
            'RG2' => 'RG2 - Ricavi (artt. 57 e 85 Tuir)',
            'RG3' => 'RG3 - Altri proventi considerati ricavi',
            'RG6' => 'RG6 - Plusvalenze patrimoniali',
            'RG7' => 'RG7 - Sopravvenienze attive',
            'RG10:1' => 'RG10, cod. 1 - Dividendi e interessi attivi',
            'RG10:4' => 'RG10, cod. 4 - Canoni di immobili strumentali',
            'RG10:99' => 'RG10, cod. 99 - Altri componenti positivi',
            'RG15' => 'RG15 - Acquisto di materie prime e merci',
            'RG16' => 'RG16 - Lavoro dipendente, assimilato e autonomo',
            'RG18' => 'RG18 - Quote di ammortamento',
            'RG19' => 'RG19 - Beni strumentali fino a 516,46 euro',
            'RG20' => 'RG20 - Canoni di locazione finanziaria di beni mobili',
            'RG22:3' => 'RG22, cod. 3 - Canoni di locazione e noleggio',
            'RG22:4' => 'RG22, cod. 4 - Interessi passivi',
            'RG22:5' => 'RG22, cod. 5 - Minusvalenze, sopravvenienze passive e perdite',
            'RG22:6' => 'RG22, cod. 6 - Imposte deducibili e contributi associativi',
            'RG22:8' => 'RG22, cod. 8 - Spese di rappresentanza',
            'RG22:9' => 'RG22, cod. 9 - Alberghi e ristoranti',
            'RG22:13' => 'RG22, cod. 13 - Manutenzioni',
            'RG22:99' => 'RG22, cod. 99 - Altri componenti negativi',
            'RE2' => 'RE2 - Compensi dell\'attività professionale',
            'RE3' => 'RE3 - Altri proventi',
            'RE4' => 'RE4 - Plusvalenze patrimoniali',
            'RE7' => 'RE7 - Ammortamenti e beni fino a 516,46 euro',
            'RE8' => 'RE8 - Canoni di locazione finanziaria di beni mobili',
            'RE9' => 'RE9 - Canoni di locazione e noleggio',
            'RE10' => 'RE10 - Spese per immobili',
            'RE11' => 'RE11 - Lavoro dipendente e assimilato',
            'RE12' => 'RE12 - Compensi a terzi per l\'attività',
            'RE13' => 'RE13 - Interessi passivi',
            'RE14' => 'RE14 - Consumi',
            'RE15' => 'RE15 - Alberghi e ristoranti',
            'RE16' => 'RE16 - Spese di rappresentanza',
            'RE18' => 'RE18 - Minusvalenze patrimoniali',
            'RE19' => 'RE19 - Altre spese documentate',
            self::ESCLUSO => 'Non rilevante per il reddito',
        ];
    }

    public static function isValido(?string $rigo): bool
    {
        return ($rigo === null) || isset(self::getRighi()[$rigo]);
    }

    /**
     * Rigo proposto dalla voce di conto economico: quadro RG per le
     * imprese, RE per gli esercenti arti e professioni; null per le voci
     * patrimoniali e quelle senza un rigo naturale (imposte sul reddito).
     */
    public static function getPredefinito(?VoceCee $voce, bool $professionista): ?string
    {
        if ($voce === null) {
            return null;
        }
        $codice = $voce->value;
        $mappa = $professionista ? [
            'E.A.1' => 'RE2', 'E.A.5' => 'RE3', 'E.B.6' => 'RE14', 'E.B.7' => 'RE19', 'E.B.8' => 'RE9', 'E.B.9' => 'RE11',
            'E.B.10.a' => 'RE7', 'E.B.10.b' => 'RE7', 'E.B.14' => 'RE19', 'E.C.16' => 'RE3', 'E.C.17' => 'RE13',
        ] : [
            'E.A.1' => 'RG2', 'E.A.5' => 'RG10:99', 'E.B.6' => 'RG15', 'E.B.7' => 'RG22:99', 'E.B.8' => 'RG22:3', 'E.B.9' => 'RG16',
            'E.B.10.a' => 'RG18', 'E.B.10.b' => 'RG18', 'E.B.14' => 'RG22:99', 'E.C.16' => 'RG10:1', 'E.C.17' => 'RG22:4',
        ];
        foreach ($mappa as $prefisso => $rigo) {
            if (($codice === $prefisso) || str_starts_with($codice, $prefisso . '.')) {
                return $rigo;
            }
        }
        return null;
    }

    /**
     * Rigo del quadro (es. "RG22") e codice della voce (es. 3).
     *
     * @return array{0: string, 1: ?int}
     */
    public static function scomponi(string $rigo): array
    {
        [$quadroRigo, $codice] = array_pad(explode(':', $rigo, 2), 2, null);
        return [$quadroRigo, $codice === null ? null : (int) $codice];
    }
}
