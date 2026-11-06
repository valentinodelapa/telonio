<?php

namespace Contabilita\Application\Enumerations;

/**
 * Qualifica delle operazioni attive che la dichiarazione IVA tratta a parte
 * nel calcolo del pro-rata di detrazione (art. 19, c. 3 e 5, e art.
 * 19-bis, c. 2, D.P.R. 633/1972; rigo VF34) o esclude dal volume d'affari
 * (art. 20; rigo VE40). Tra gli acquisti vale la sola connessa occasionale,
 * per gli acquisti afferenti alle operazioni dell'art. 34-bis (rigo VF18).
 */
enum QualificaOperazione: string
{

    case oroInvestimento = 'O';
    case esenteOccasionale = 'E';
    case esente27Quinquies = 'Q';
    case beneAmmortizzabile = 'A';
    case esenteEquiparata = 'P';
    case fuoriCampoSenzaDetrazione = 'F';
    case prestazioneNonStabiliti = 'S';
    case conferimentoAgricolo = 'C';
    case connessaOccasionale = 'K';

    public function getLabel(): string
    {
        return match ($this) {
            self::oroInvestimento => 'Oro da investimento di produttori e trasformatori (art. 10, n. 11; VF34 campo 1)',
            self::esenteOccasionale => 'Esente occasionale o accessoria (art. 10, nn. 1-9; VF34 campo 2)',
            self::esente27Quinquies => 'Esente art. 10, n. 27-quinquies (VF34 campo 3)',
            self::beneAmmortizzabile => 'Cessione di bene ammortizzabile o passaggio interno (VE40; esente: VF34 campo 4)',
            self::esenteEquiparata => 'Esente equiparata alle imponibili (art. 19, c. 3, lett. a-bis e d-bis; VF34 campo 7)',
            self::fuoriCampoSenzaDetrazione => 'Non soggetta senza diritto alla detrazione (art. 19, c. 3, lett. b; VF34 campo 8)',
            self::prestazioneNonStabiliti => 'Prestazione verso soggetti non stabiliti: lavorazioni, trasporti, intermediazioni e accessori, art. 19, c. 3, lett. a-bis (art. 30, c. 2, lett. d; modello IVA TR, TD5)',
            self::conferimentoAgricolo => 'Conferimento a cooperative o consorzi con la percentuale di compensazione (art. 34, c. 2; VE sezione 1)',
            self::connessaOccasionale => 'Attività agricola connessa occasionale (art. 34-bis; VF62, gli acquisti nel VF18)',
        };
    }

    public function getSigla(): string
    {
        return match ($this) {
            self::oroInvestimento => 'Oro',
            self::esenteOccasionale => 'Esente occ.',
            self::esente27Quinquies => '27-quinquies',
            self::beneAmmortizzabile => 'Bene amm.',
            self::esenteEquiparata => 'Equiparata',
            self::fuoriCampoSenzaDetrazione => 'Fuori campo',
            self::prestazioneNonStabiliti => 'Non stabiliti',
            self::conferimentoAgricolo => 'Conferimento',
            self::connessaOccasionale => 'Connessa occ.',
        };
    }

    /**
     * La qualifica è compatibile con la natura del codice IVA della riga
     * (null per le operazioni imponibili).
     */
    public function isAmmessa(?NaturaIva $natura): bool
    {
        return match ($this) {
            self::beneAmmortizzabile => true,
            self::fuoriCampoSenzaDetrazione => $natura === NaturaIva::nonSoggetteTerritorialita,
            self::prestazioneNonStabiliti => in_array($natura, [NaturaIva::nonSoggetteTerritorialita, NaturaIva::esenti], true),
            self::conferimentoAgricolo, self::connessaOccasionale => $natura === null,
            default => $natura === NaturaIva::esenti,
        };
    }
}
