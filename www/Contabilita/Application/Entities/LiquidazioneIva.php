<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Liquidazione periodica IVA dell'azienda per mese o trimestre: IVA
 * esigibile e detraibile del periodo dai registri, debito non versato e
 * credito del periodo precedente, credito annuale compensato, versamenti
 * dell'IVA sulle auto di provenienza UE (F24 elementi identificativi, D.L.
 * 262/2006) e crediti d'imposta particolari scomputati, acconto,
 * interessi dei trimestrali, importo dovuto o credito riportato. Il
 * versamento è registrato nell'archivio di Deleghe F24 (origine
 * "Contabilita", riferimento iva-<anno>-<periodo>); per le società che
 * partecipano all'IVA di gruppo il risultato è trasferito alla
 * controllante e non si versa. Con le attività separate somma le
 * liquidazioni delle attività (LiquidazioneAttivita).
 */
class LiquidazioneIva extends ReferencedEntity
{

    public const ORIGINE = 'Contabilita';

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $anno;
    protected int $periodo;
    protected PeriodicitaIva $periodicita;
    protected float $ivaEsigibile = 0.0;
    protected float $ivaDetraibile = 0.0;
    protected float $ivaVentilata = 0.0;
    protected float $debitoPrecedente = 0.0;
    protected float $creditoPrecedente = 0.0;
    protected float $creditoAnnoPrecedente = 0.0;
    protected float $versamentiAuto = 0.0;
    protected float $creditiImposta = 0.0;
    protected float $acconto = 0.0;
    protected float $interessi = 0.0;
    protected float $importoDovuto = 0.0;
    protected float $creditoFinale = 0.0;
    protected float $creditoTrimestrale = 0.0;
    protected float $debitoRinviato = 0.0;
    protected ?SismaDate $scadenza = null;
    protected SismaDateTime $dataElaborazione;
    protected ?string $avvisi = null;
    protected ?string $differenze = null;
    protected ?RegistrazioneContabile $registrazioneContabile = null;
    protected ?int $trimestreAnticipato = null;
    protected ?GruppoIva $gruppoIva = null;
    protected bool $trimestreSeparato = false;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataElaborazione = new SismaDateTime();
    }

    public static function getRiferimento(int $anno, int $periodo): string
    {
        return 'iva-' . $anno . '-' . $periodo;
    }

    public function getDescrizionePeriodo(): string
    {
        return self::descriviPeriodo($this->periodicita, $this->anno, $this->periodo) . ($this->trimestreSeparato ? ' (attività trimestrali)' : '');
    }

    /**
     * Riferimento del versamento e del giroconto: distinto per il
     * trimestre separato delle attività trimestrali.
     */
    public function getRiferimentoVersamento(): string
    {
        return $this->trimestreSeparato ? 'iva-' . $this->anno . '-T' . $this->periodo : self::getRiferimento($this->anno, $this->periodo);
    }

    public static function descriviPeriodo(PeriodicitaIva $periodicita, int $anno, int $periodo): string
    {
        if ($periodicita === PeriodicitaIva::trimestrale) {
            return $periodo . '° trimestre ' . $anno;
        }
        $mesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        return $mesi[$periodo - 1] . ' ' . $anno;
    }

    /**
     * IVA del periodo: esigibile meno detraibile (negativa = credito).
     */
    public function getIvaPeriodo(): float
    {
        return round($this->ivaEsigibile - $this->ivaDetraibile, 2);
    }

    public function isTrasferita(): bool
    {
        return $this->gruppoIva !== null;
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
     * Debito o credito trasferito al gruppo (positivo = debito, compresi
     * gli interessi dei trimestrali).
     */
    public function getSaldoTrasferito(): float
    {
        return $this->isTrasferita() ? round($this->importoDovuto - $this->creditoFinale, 2) : 0.0;
    }
}
