<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Liquidazione periodica del gruppo IVA tenuta dalla controllante
 * (registro riassuntivo, art. 4 D.M. 13 dicembre 1979): debiti (con gli
 * interessi dei trimestrali) e crediti trasferiti dalle società
 * partecipanti, riporto del credito e del debito non versato del gruppo,
 * credito di gruppo dell'anno precedente compensato, acconto del gruppo e
 * versamento della controllante nell'archivio di Deleghe F24 (riferimento
 * iva-gruppo-<anno>-<periodo>). I saldi delle società trimestrali entrano
 * nel mese di fine trimestre, salvo il quarto trimestre, che si chiude con
 * la dichiarazione annuale.
 */
class LiquidazioneGruppoIva extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected GruppoIva $gruppoIva;
    protected int $anno;
    protected int $periodo;
    protected PeriodicitaIva $periodicita;
    protected ?int $trimestreAnticipato = null;
    protected bool $trimestreSeparato = false;
    protected float $debitiTrasferiti = 0.0;
    protected float $creditiTrasferiti = 0.0;
    protected float $interessiTrasferiti = 0.0;
    protected float $debitoPrecedente = 0.0;
    protected float $creditoPrecedente = 0.0;
    protected float $creditoAnnoPrecedente = 0.0;
    protected float $acconto = 0.0;
    protected float $importoDovuto = 0.0;
    protected float $creditoFinale = 0.0;
    protected float $creditoTrimestrale = 0.0;
    protected float $debitoRinviato = 0.0;
    protected ?SismaDate $scadenza = null;
    protected SismaDateTime $dataElaborazione;
    protected ?string $avvisi = null;
    protected ?string $differenze = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataElaborazione = new SismaDateTime();
    }

    public function getDescrizionePeriodo(): string
    {
        return LiquidazioneIva::descriviPeriodo($this->periodicita, $this->anno, $this->periodo) . ($this->trimestreAnticipato === null ? '' : ' e ' . $this->trimestreAnticipato . '° trimestre')
                . ($this->trimestreSeparato ? ' (società trimestrali)' : '');
    }

    public function getRiferimentoVersamento(): string
    {
        return $this->trimestreSeparato ? 'iva-gruppo-' . $this->anno . '-T' . $this->periodo : GruppoIva::getRiferimento($this->anno, $this->periodo);
    }

    /**
     * Credito riportato al periodo successivo: il credito finale meno il
     * credito del trimestre chiesto con il modello IVA TR.
     */
    public function getCreditoRiportato(): float
    {
        return round(max(0.0, $this->creditoFinale - $this->creditoTrimestrale), 2);
    }

    /**
     * Saldo dei trasferimenti del periodo (negativo = credito).
     */
    public function getSaldoTrasferito(): float
    {
        return round($this->debitiTrasferiti - $this->creditiTrasferiti, 2);
    }
}
