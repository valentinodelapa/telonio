<?php

namespace Contabilita\Application\Enumerations;

/**
 * Causale dei compensi di lavoro autonomo, delle provvigioni e dei redditi
 * diversi pagati dall'azienda come sostituto d'imposta (punto 1 della
 * certificazione di lavoro autonomo della Certificazione Unica, istruzioni
 * CU 2026; è anche la CausalePagamento dei DatiRitenuta della fattura
 * elettronica): con l'aliquota della ritenuta d'acconto e la quota del
 * compenso che vi è soggetta, per risalire dall'importo della ritenuta
 * all'imponibile e al compenso lordo (artt. 25 e 25-bis D.P.R. 600/1973).
 *
 * Provvigioni: ritenuta del 23 per cento sul 50 per cento (sul 20 per
 * cento se l'agente si avvale di dipendenti o terzi: la quota si corregge
 * a mano nella certificazione).
 */
enum CausaleCu: string
{

    case professionista = 'A';
    case dirittiAutore = 'B';
    case associazionePartecipazione = 'C';
    case occasionale = 'M';
    case obblighiFareNonFare = 'M1';
    case agenteMonomandatario = 'Q';
    case agentePlurimandatario = 'R';
    case commissionario = 'S';
    case mediatore = 'T';
    case procacciatore = 'U';
    case venditaDomicilio = 'V';
    case provvigioniOccasionali = 'V1';
    case appalto = 'W';

    public function getLabel(): string
    {
        return $this->value . ' - ' . match ($this) {
            self::professionista => 'Lavoro autonomo abituale (arte o professione)',
            self::dirittiAutore => 'Diritti d\'autore e di inventore',
            self::associazionePartecipazione => 'Utili da associazione in partecipazione con apporto di lavoro',
            self::occasionale => 'Lavoro autonomo occasionale',
            self::obblighiFareNonFare => 'Obblighi di fare, non fare o permettere',
            self::agenteMonomandatario => 'Provvigioni ad agente monomandatario',
            self::agentePlurimandatario => 'Provvigioni ad agente plurimandatario',
            self::commissionario => 'Provvigioni a commissionario',
            self::mediatore => 'Provvigioni a mediatore',
            self::procacciatore => 'Provvigioni a procacciatore d\'affari',
            self::venditaDomicilio => 'Provvigioni a incaricato delle vendite a domicilio',
            self::provvigioniOccasionali => 'Provvigioni per attività commerciali occasionali',
            self::appalto => 'Corrispettivi di contratti d\'appalto (art. 25-ter)',
        };
    }

    /**
     * Aliquota della ritenuta d'acconto (per cento).
     */
    public function getAliquota(): float
    {
        return match ($this) {
            self::agenteMonomandatario, self::agentePlurimandatario, self::commissionario, self::mediatore, self::procacciatore, self::venditaDomicilio => 23.0,
            self::appalto => 4.0,
            default => 20.0,
        };
    }

    /**
     * Parte del compenso soggetta a ritenuta (per cento): il resto va tra le
     * somme non soggette (punto 7 della certificazione).
     */
    public function getQuotaImponibile(): float
    {
        return match ($this) {
            self::agenteMonomandatario, self::agentePlurimandatario, self::commissionario, self::mediatore, self::procacciatore => 50.0,
            // vendite a domicilio: ritenuta sul 78 per cento (deduzione forfetaria del 22 per cento)
            self::venditaDomicilio => 78.0,
            self::dirittiAutore => 75.0,
            default => 100.0,
        };
    }
}
