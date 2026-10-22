<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Imu\Application\Enumerations\FattispecieImu;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Rigo del prospetto di calcolo: un immobile del contribuente in un
 * periodo dell'anno con possesso, classamento e caratteristiche costanti.
 *
 * imposta = base imponibile × quota × riduzione della base × mesi/12 ×
 * aliquota × riduzione dell'imposta; l'acconto applica ai mesi del primo
 * semestre l'aliquota dell'anno precedente (comma 762). La detrazione è
 * quella dell'abitazione principale (comma 749). Senza fattispecie il rigo
 * è esente o non imponibile, con il motivo.
 */
class RigoCalcoloImu extends BaseEntity
{

    protected int $id;
    protected CalcoloImu $calcoloImu;
    protected Immobile $immobile;
    protected SismaDate $dal;
    protected SismaDate $al;
    protected int $mesi = 0;
    protected int $mesiAcconto = 0;
    protected TitoloPossesso $titolo;
    protected float $quota = 100.0;
    protected ?UtilizzoImmobile $utilizzo = null;
    protected ?CategoriaCatastale $categoria = null;
    protected ?FattispecieImu $fattispecie = null;
    protected ?string $esenzione = null;
    protected string $codiceComune;
    protected float $baseImponibile = 0.0;
    protected float $riduzioneBase = 1.0;
    protected float $riduzioneImposta = 1.0;
    protected ?float $aliquota = null;
    protected ?float $aliquotaAcconto = null;
    protected ?string $origineAliquota = null;
    protected float $impostaAnnua = 0.0;
    protected float $impostaAcconto = 0.0;
    protected float $quotaStatoAnnua = 0.0;
    protected float $quotaStatoAcconto = 0.0;
    protected float $detrazioneAnnua = 0.0;
    protected float $detrazioneAcconto = 0.0;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isImponibile(): bool
    {
        return $this->fattispecie !== null;
    }

    /**
     * Base imponibile del contribuente: per quota e riduzioni.
     */
    public function getBaseImponibileQuota(): float
    {
        return round($this->baseImponibile * $this->quota / 100 * $this->riduzioneBase, 2);
    }
}
