<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Acconto IMU già versato senza passare dalla procedura (dal contribuente,
 * da un altro professionista o, per il 2020, pari alla metà di IMU e TASI
 * 2019, comma 762) per comune e codice tributo: il calcolo non registra
 * l'acconto e determina il saldo su questo importo.
 */
class AccontoVersatoImu extends BaseEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected int $anno;
    protected string $codiceComune;
    protected string $codiceTributo;
    protected float $importo;
    protected ?SismaDate $dataVersamento = null;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getChiave(): string
    {
        return $this->codiceComune . '|' . $this->codiceTributo;
    }
}
