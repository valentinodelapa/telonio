<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use Deleghe\Application\Entities\Intermediario;
use Imu\Application\Enumerations\CodiceCarica;
use Imu\Application\Enumerations\ModelloDichiarazione;
use Imu\Application\Enumerations\StatoDichiarazione;
use Imu\Application\Enumerations\TipologiaDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Dichiarazione IMU del contribuente per un anno e un comune (art. 1,
 * comma 768-bis, L. 160/2019), da presentare in via telematica entro il 30
 * giugno dell'anno successivo: per le persone fisiche e gli enti
 * commerciali quando sono intervenute variazioni non conoscibili dal
 * comune; per gli enti non commerciali ogni anno, con la determinazione
 * dell'imposta (quadri C e D).
 *
 * I quadri descrittivi degli immobili sono in RigoDichiarazioneImu.
 */
class DichiarazioneImu extends ReferencedEntity
{

    public const MESE_SCADENZA = '06-30';

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $anno;
    protected string $codiceComune;
    protected string $denominazioneComune;
    protected ModelloDichiarazione $modello = ModelloDichiarazione::ordinario;
    protected TipologiaDichiarazione $tipologia = TipologiaDichiarazione::nuova;
    protected StatoDichiarazione $stato = StatoDichiarazione::bozza;
    protected ?Soggetto $dichiarante = null;
    protected ?CodiceCarica $codiceCarica = null;
    protected ?Intermediario $intermediario = null;
    protected ?SismaDate $dataImpegno = null;
    protected ?SismaDate $dataDichiarazione = null;
    protected ?SismaDate $dataPresentazione = null;
    protected ?string $protocollo = null;
    protected ?string $annotazioni = null;
    protected ?float $impostaDovuta = null;
    protected ?float $eccedenzaPrecedente = null;
    protected ?float $eccedenzaCompensata = null;
    protected ?float $rateVersate = null;
    protected ?float $creditoRimborso = null;
    protected ?float $creditoCompensazione = null;
    protected SismaDateTime $dataInserimento;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInserimento = new SismaDateTime();
    }

    public function isModificabile(): bool
    {
        return $this->stato === StatoDichiarazione::bozza;
    }

    public function isEnc(): bool
    {
        return $this->modello === ModelloDichiarazione::entiNonCommerciali;
    }

    public function getScadenza(): SismaDate
    {
        return new SismaDate(($this->anno + 1) . '-' . self::MESE_SCADENZA);
    }

    /**
     * Saldo del quadro C (ENC): positivo a debito, negativo a credito.
     */
    public function getSaldoQuadroC(): float
    {
        return round((float) $this->impostaDovuta - (float) $this->eccedenzaPrecedente + (float) $this->eccedenzaCompensata - (float) $this->rateVersate, 2);
    }
}
