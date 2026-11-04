<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Liquidazione di un'attività con contabilità separata (art. 36) compresa
 * nella liquidazione periodica dell'azienda: le attività trimestrali sono
 * liquidate nel mese di fine trimestre (liquidazione anticipata ai fini
 * della compensazione), con gli interessi dell'1% sull'IVA dovuta del
 * trimestre.
 */
class LiquidazioneAttivita extends BaseEntity
{

    protected int $id;
    protected LiquidazioneIva $liquidazioneIva;
    protected AttivitaIva $attivitaIva;
    protected PeriodicitaIva $periodicita;
    protected int $periodo;
    protected float $ivaEsigibile = 0.0;
    protected float $ivaDetraibile = 0.0;
    protected float $ivaVentilata = 0.0;
    protected int $percentualeDetrazione = 100;
    protected float $interessi = 0.0;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getIvaPeriodo(): float
    {
        return round($this->ivaEsigibile - $this->ivaDetraibile, 2);
    }

    public function getDescrizionePeriodo(int $anno): string
    {
        return LiquidazioneIva::descriviPeriodo($this->periodicita, $anno, $this->periodo);
    }
}
