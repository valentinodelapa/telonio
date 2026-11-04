<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Modulo del quadro VP della comunicazione: dati della liquidazione di un
 * mese o del trimestre, fissati alla preparazione (righi da VP2 a VP14).
 */
class ModuloLipe extends BaseEntity
{

    /** Valore del rigo VP1 per il quarto trimestre dei trimestrali (art. 7 D.P.R. 542/1999). */
    public const QUARTO_TRIMESTRE_TRIMESTRALI = 5;

    protected int $id;
    protected ComunicazioneLipe $comunicazioneLipe;
    protected int $numeroModulo;
    protected ?int $mese = null;
    protected ?int $trimestre = null;
    protected ?LiquidazioneIva $liquidazioneIva = null;
    protected ?LiquidazioneGruppoIva $liquidazioneGruppoIva = null;
    protected float $operazioniAttive = 0.0;
    protected float $operazioniPassive = 0.0;
    protected float $ivaEsigibile = 0.0;
    protected float $ivaDetratta = 0.0;
    protected float $debitoPrecedente = 0.0;
    protected float $creditoPrecedente = 0.0;
    protected float $creditoAnnoPrecedente = 0.0;
    protected float $versamentiAuto = 0.0;
    protected float $creditiImposta = 0.0;
    protected float $interessi = 0.0;
    protected ?string $metodoAcconto = null;
    protected float $acconto = 0.0;
    protected float $importoDaVersare = 0.0;
    protected float $importoACredito = 0.0;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getDescrizionePeriodo(): string
    {
        if ($this->mese !== null) {
            return LiquidazioneIva::descriviPeriodo(PeriodicitaIva::mensile, $this->comunicazioneLipe->anno, $this->mese) . ($this->trimestre === null ? '' : ' e ' . $this->trimestre . '° trimestre');
        }
        return LiquidazioneIva::descriviPeriodo(PeriodicitaIva::trimestrale, $this->comunicazioneLipe->anno, $this->isQuartoTrimestreTrimestrali() ? 4 : (int) $this->trimestre);
    }

    /**
     * Rigo VP6: IVA dovuta (positiva) o a credito (negativa).
     */
    public function getIvaPeriodo(): float
    {
        return round($this->ivaEsigibile - $this->ivaDetratta, 2);
    }

    /**
     * Quarto trimestre dei trimestrali: si versa con la dichiarazione
     * annuale, senza i righi VP11, VP12 e VP14.
     */
    public function isQuartoTrimestreTrimestrali(): bool
    {
        return $this->trimestre === self::QUARTO_TRIMESTRE_TRIMESTRALI;
    }
}
