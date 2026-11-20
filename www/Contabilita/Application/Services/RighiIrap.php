<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Entities\Conto;

/**
 * Variazioni del valore della produzione del quadro IC del modello IRAP
 * (società di capitali, art. 5 D.Lgs. 446/1997; istruzioni IRAP, righi da
 * IC43 a IC57 e paragrafo 2.1.7 per i codici delle altre variazioni) a cui
 * concorrono i costi e i ricavi del conto nell'anno: componenti contabilizzati
 * in voci rilevanti ai fini IRAP ma indeducibili (variazioni in aumento) o
 * non imponibili (variazioni in diminuzione). Per le altre variazioni il
 * codice segue il rigo ("IC51:99"). Vuoto: il conto non dà variazioni.
 */
class RighiIrap
{

    /**
     * Righi ammessi sul conto, con la descrizione.
     *
     * @return array<string, string>
     */
    public static function getRighi(): array
    {
        return [
            'IC43' => 'IC43 - Costi indeducibili dell\'art. 11, c. 1, lett. b) (collaboratori coordinati e continuativi, lavoro autonomo occasionale, utili agli associati in partecipazione)',
            'IC44' => 'IC44 - Quota interessi dei canoni di locazione finanziaria',
            'IC45' => 'IC45 - Perdite e svalutazioni su crediti',
            'IC46' => 'IC46 - IMU e imposte immobiliari locali (IMI, IMIS, ILIA)',
            'IC48' => 'IC48 - Ammortamento indeducibile di marchi e avviamento',
            'IC49' => 'IC49 - Interessi passivi indeducibili',
            'IC51:01' => 'IC51 codice 1 - Spese per il personale dipendente e assimilato in voci rilevanti',
            'IC51:03' => 'IC51 codice 3 - Contributi erogati per legge non contabilizzati in voci rilevanti',
            'IC51:04' => 'IC51 codice 4 - Ammortamento delle aree sottostanti i fabbricati strumentali',
            'IC51:05' => 'IC51 codice 5 - Costi per attività qualificabili come delitto non colposo',
            'IC51:06' => 'IC51 codice 6 - Oneri finanziari e personale delle immobilizzazioni in economia non transitati da A4',
            'IC51:14' => 'IC51 codice 14 - Componenti negativi dalla valutazione delle cripto-attività',
            'IC51:99' => 'IC51 codice 99 - Altre variazioni in aumento',
            'IC57:03' => 'IC57 codice 3 - Distacco di personale presso terzi e rimborsi del lavoro interinale',
            'IC57:04' => 'IC57 codice 4 - Sopravvenienze attive di componenti non rilevanti negli esercizi precedenti',
            'IC57:05' => 'IC57 codice 5 - Indennità di trasferta non imponibili degli autotrasportatori',
            'IC57:19' => 'IC57 codice 19 - Componenti positivi dalla valutazione delle cripto-attività',
            'IC57:99' => 'IC57 codice 99 - Altre variazioni in diminuzione',
        ];
    }

    public static function isValido(?string $rigo): bool
    {
        return ($rigo === null) || ($rigo === '') || isset(self::getRighi()[$rigo]);
    }

    /**
     * Rigo del conto, se indicato.
     */
    public static function getRigo(Conto $conto): ?string
    {
        return ($conto->rigoIrap === null) || ($conto->rigoIrap === '') ? null : $conto->rigoIrap;
    }
}
