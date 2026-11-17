<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Enumerations\VoceCee;

/**
 * Righi del quadro F dei modelli ISA (dati contabili d'impresa, istruzioni
 * del quadro F) a cui concorrono i costi e i ricavi dei conti: dalla voce
 * del conto economico CEE del conto o, sul conto, un rigo diverso o un
 * campo "di cui" del rigo (es. "F14:2", compensi dei professionisti
 * esterni: per gli ISA sono spese per prestazioni di lavoro, rigo F14, e
 * non costi per servizi come nello schema di bilancio). "NO" esclude il
 * conto dal quadro F.
 */
class RighiIsa
{

    public const ESCLUSO = 'NO';

    /**
     * Righi e campi "di cui" ammessi sul conto, con la descrizione.
     *
     * @return array<string, string>
     */
    public static function getRighi(): array
    {
        return [
            'F01' => 'F01 - Ricavi',
            'F02' => 'F02 - Altri proventi considerati ricavi',
            'F05' => 'F05 - Altri proventi e componenti positivi',
            'F10' => 'F10 - Costi per l\'acquisto di materie prime, sussidiarie, semilavorati e merci',
            'F12' => 'F12 - Costo per servizi',
            'F12:2' => 'F12 - di cui compensi ai soci per l\'attività di amministratore (società ed enti soggetti all\'IRES)',
            'F13' => 'F13 - Costo per il godimento di beni di terzi',
            'F13:2' => 'F13 - di cui canoni relativi a beni immobili',
            'F13:3' => 'F13 - di cui locazione non finanziaria e noleggio di beni mobili',
            'F13:4' => 'F13 - di cui locazione finanziaria di beni mobili',
            'F14' => 'F14 - Spese per lavoro dipendente e altre prestazioni di lavoro',
            'F14:2' => 'F14 - di cui prestazioni di professionisti esterni',
            'F14:3' => 'F14 - di cui collaboratori coordinati e continuativi',
            'F14:4' => 'F14 - di cui compensi ai soci per l\'attività di amministratore (società di persone)',
            'F15' => 'F15 - Ammortamenti',
            'F16' => 'F16 - Accantonamenti',
            'F17' => 'F17 - Oneri diversi di gestione e altre componenti negative',
            'F17:2' => 'F17 - di cui perdite su crediti',
            'F17:8' => 'F17 - di cui oneri per imposte e tasse',
            'F18' => 'F18 - Risultato della gestione finanziaria',
            'F19' => 'F19 - Interessi e altri oneri finanziari',
            self::ESCLUSO => 'Non concorre al quadro F',
        ];
    }

    public static function isValido(?string $rigo): bool
    {
        return ($rigo === null) || ($rigo === '') || isset(self::getRighi()[$rigo]);
    }

    /**
     * Rigo predefinito per la voce del conto economico CEE.
     */
    public static function getPredefinito(?VoceCee $voce): ?string
    {
        if ($voce === null) {
            return null;
        }
        $mappa = ['E.A.1' => 'F01', 'E.A.5' => 'F05', 'E.B.6' => 'F10', 'E.B.7' => 'F12', 'E.B.8' => 'F13', 'E.B.9' => 'F14', 'E.B.10.a' => 'F15', 'E.B.10.b' => 'F15',
            'E.B.12' => 'F16', 'E.B.13' => 'F16', 'E.B.14' => 'F17', 'E.C.15' => 'F18', 'E.C.16' => 'F18', 'E.C.17' => 'F19', 'E.C.17-bis' => 'F19'];
        foreach ($mappa as $prefisso => $rigo) {
            if (($voce->value === $prefisso) || str_starts_with($voce->value, $prefisso . '.')) {
                return $rigo;
            }
        }
        return null;
    }

    /**
     * Rigo del conto: quello indicato sul conto o il predefinito della voce.
     */
    public static function getRigo(Conto $conto): ?string
    {
        return ($conto->rigoIsa === null) || ($conto->rigoIsa === '') ? self::getPredefinito($conto->voceCee) : $conto->rigoIsa;
    }

    /**
     * Chiavi a cui concorre l'importo del conto: il rigo e, per i campi "di
     * cui", anche il campo (es. "F14" e "F14:2"); nessuna per i conti
     * esclusi o senza rigo.
     *
     * @return string[]
     */
    public static function getChiavi(Conto $conto): array
    {
        $rigo = self::getRigo($conto);
        if (($rigo === null) || ($rigo === self::ESCLUSO)) {
            return [];
        }
        return str_contains($rigo, ':') ? [explode(':', $rigo)[0], $rigo] : [$rigo];
    }
}
