<?php

namespace Imu\Application\Entities;

use Telonio\Application\Entities\Studio;
use Imu\Application\Enumerations\FattispecieImu;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Aliquote IMU di un comune per un anno.
 *
 * Senza studio è il prospetto pubblicato dal Dipartimento delle Finanze
 * (art. 1, commi 756-757 e 767, L. 160/2019), comune a tutti gli studi e
 * importato con il comando aliquote-imu; con lo studio sono le aliquote
 * inserite dallo studio (anni anteriori al 2025, senza prospetto
 * obbligatorio, o correzioni), che prevalgono su quelle pubblicate.
 *
 * Il comune è identificato dal codice catastale (stabile tra le versioni
 * del comune dell'Anagrafica). Le aliquote sono percentuali.
 */
class ProspettoAliquote extends ReferencedEntity
{

    protected int $id;
    protected ?Studio $studio = null;
    protected string $codiceCatastale;
    protected string $denominazioneComune;
    protected int $anno;
    protected ?string $exComune = null;
    protected ?int $idProspetto = null;
    protected ?string $numeroDelibera = null;
    protected ?SismaDate $dataDelibera = null;
    protected ?SismaDate $dataPubblicazione = null;
    protected ?string $fileProspetto = null;
    protected ?string $urlProspetto = null;
    protected ?float $aliquotaAbitazionePrincipale = null;
    protected ?float $aliquotaRuraliStrumentali = null;
    protected ?float $aliquotaGruppoD = null;
    protected ?float $aliquotaTerreniAgricoli = null;
    protected ?float $aliquotaAreeFabbricabili = null;
    protected ?float $aliquotaAltriFabbricati = null;
    protected ?float $aliquotaBeniMerce = null;
    protected bool $assimilazioneAnziani = false;
    protected float $detrazioneAbitazionePrincipale = 200.0;
    protected float $importoMinimo = 12.0;
    protected ?string $esenzioni = null;
    protected ?string $note = null;
    protected SismaDateTime $dataImportazione;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataImportazione = new SismaDateTime();
    }

    public function isPubblicato(): bool
    {
        return $this->studio === null;
    }

    /**
     * Aliquota della fattispecie di base; null se il prospetto non la
     * indica.
     */
    public function getAliquota(FattispecieImu $fattispecie): ?float
    {
        return $this->{$fattispecie->getProprietaProspetto()};
    }

    public function getEtichetta(): string
    {
        return $this->denominazioneComune . ($this->exComune !== null ? ' (ex ' . $this->exComune . ')' : '') . ' ' . $this->anno;
    }

    public function getOrigine(): string
    {
        return $this->isPubblicato() ? 'prospetto ' . ($this->idProspetto !== null ? 'n. ' . $this->idProspetto . ' ' : '') . 'del Dipartimento delle Finanze' : 'aliquote inserite dallo studio';
    }
}
