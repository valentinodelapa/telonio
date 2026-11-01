<?php

namespace Contabilita\Application\Enumerations;

/**
 * Causali della prima nota. Sono un vocabolario chiuso perché ne fissano
 * il comportamento: il registro IVA in cui il documento si annota, il
 * segno del documento (le note di variazione in diminuzione stornano
 * imponibile e imposta), la controparte richiesta e lo schema delle righe
 * proposte in partita doppia. La descrizione della registrazione resta
 * libera.
 */
enum CausaleContabile: string
{

    case fatturaVendita = 'FV';
    case notaCreditoEmessa = 'NCE';
    case corrispettivi = 'COR';
    case fatturaAcquisto = 'FA';
    case notaCreditoRicevuta = 'NCR';
    case incasso = 'INC';
    case pagamento = 'PAG';
    case liquidazioneIva = 'LIQ';
    case versamentoIva = 'VIV';
    case giroconto = 'GIR';

    public function getLabel(): string
    {
        return match ($this) {
            self::fatturaVendita => 'Fattura di vendita',
            self::notaCreditoEmessa => 'Nota di credito emessa',
            self::corrispettivi => 'Corrispettivi',
            self::fatturaAcquisto => 'Fattura di acquisto',
            self::notaCreditoRicevuta => 'Nota di credito ricevuta',
            self::incasso => 'Incasso da cliente',
            self::pagamento => 'Pagamento a fornitore',
            self::liquidazioneIva => 'Liquidazione IVA',
            self::versamentoIva => 'Versamento IVA',
            self::giroconto => 'Giroconto / operazione generica',
        };
    }

    /**
     * Tipo di registro IVA in cui si annota il documento (null = nessuno).
     */
    public function getTipoRegistroIva(): ?TipoRegistroIva
    {
        return match ($this) {
            self::fatturaVendita, self::notaCreditoEmessa => TipoRegistroIva::vendite,
            self::corrispettivi => TipoRegistroIva::corrispettivi,
            self::fatturaAcquisto, self::notaCreditoRicevuta => TipoRegistroIva::acquisti,
            default => null,
        };
    }

    public function isIva(): bool
    {
        return $this->getTipoRegistroIva() !== null;
    }

    /**
     * Segno del documento: -1 per le note di variazione in diminuzione
     * (art. 26 D.P.R. 633/1972), che riducono imponibile e imposta del
     * registro.
     */
    public function getSegno(): int
    {
        return match ($this) {
            self::notaCreditoEmessa, self::notaCreditoRicevuta => -1,
            default => 1,
        };
    }

    /**
     * Il documento richiede il cliente o il fornitore.
     */
    public function isControparteRichiesta(): bool
    {
        return in_array($this, [self::fatturaVendita, self::notaCreditoEmessa, self::fatturaAcquisto, self::notaCreditoRicevuta, self::incasso, self::pagamento], true);
    }

    /**
     * Ruolo della controparte richiesta: cliente (true) o fornitore
     * (false); null se la causale non ne richiede.
     */
    public function isControparteCliente(): ?bool
    {
        return match ($this) {
            self::fatturaVendita, self::notaCreditoEmessa, self::incasso => true,
            self::fatturaAcquisto, self::notaCreditoRicevuta, self::pagamento => false,
            default => null,
        };
    }

    /**
     * Il documento richiede numero e data (fatture e note).
     */
    public function isDocumentoRichiesto(): bool
    {
        return in_array($this, [self::fatturaVendita, self::notaCreditoEmessa, self::fatturaAcquisto, self::notaCreditoRicevuta], true);
    }

    /**
     * Causali inserite dall'utente (la liquidazione si genera dalla
     * liquidazione periodica).
     *
     * @return self[]
     */
    public static function getManuali(): array
    {
        return array_values(array_filter(self::cases(), fn(self $causale) => $causale !== self::liquidazioneIva));
    }
}
