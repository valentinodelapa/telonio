<?php

namespace Anagrafica\Application\Enumerations;

/**
 * Opzioni per la tassazione di gruppo delle società di capitali (Tuir):
 * consolidato nazionale (artt. 117-129: ciascuna società trasferisce il
 * reddito alla consolidante, che liquida l'IRES del gruppo) e trasparenza
 * fiscale (art. 115, soci società di capitali; art. 116, società a
 * responsabilità limitata con soci persone fisiche): il reddito della
 * partecipata è imputato ai soci. Le opzioni valgono per tre esercizi.
 */
enum TipoOpzioneTassazione: string
{

    case consolidatoNazionale = 'CN';
    case trasparenza = 'T115';
    case trasparenzaRistretta = 'T116';

    public function getLabel(): string
    {
        return match ($this) {
            self::consolidatoNazionale => 'Consolidato nazionale (artt. 117-129 Tuir)',
            self::trasparenza => 'Trasparenza fiscale (art. 115 Tuir)',
            self::trasparenzaRistretta => 'Trasparenza fiscale delle s.r.l. con soci persone fisiche (art. 116 Tuir)',
        };
    }

    /**
     * Codice della casella "Tipo trasparenza" del quadro TN di Redditi SC
     * (1 art. 115, 2 art. 116); null per il consolidato.
     */
    public function getTipoTrasparenza(): ?int
    {
        return match ($this) {
            self::trasparenza => 1,
            self::trasparenzaRistretta => 2,
            self::consolidatoNazionale => null,
        };
    }
}
