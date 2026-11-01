<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use Telonio\Application\Entities\Studio;

/**
 * Liquidazione periodica IVA dell'azienda per mese o trimestre: IVA
 * esigibile e detraibile del periodo dai registri, debito non versato e
 * credito del periodo precedente, credito annuale compensato, acconto,
 * interessi dei trimestrali, importo dovuto o credito riportato. Il
 * versamento è registrato nell'archivio di Deleghe F24 (origine
 * "Contabilita", riferimento iva-<anno>-<periodo>).
 */
class LiquidazioneIva extends BaseEntity
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
    protected float $acconto = 0.0;
    protected float $interessi = 0.0;
    protected float $importoDovuto = 0.0;
    protected float $creditoFinale = 0.0;
    protected float $debitoRinviato = 0.0;
    protected ?SismaDate $scadenza = null;
    protected SismaDateTime $dataElaborazione;
    protected ?string $avvisi = null;
    protected ?string $differenze = null;
    protected ?RegistrazioneContabile $registrazioneContabile = null;

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
        return self::descriviPeriodo($this->periodicita, $this->anno, $this->periodo);
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
}
