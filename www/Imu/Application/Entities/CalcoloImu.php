<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use CommSuite\Application\Entities\Studio;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Calcolo IMU del contribuente per un anno: l'ultima elaborazione, con il
 * prospetto per immobile (RigoCalcoloImu) e i totali dei versamenti
 * registrati nell'archivio di Deleghe (origine "Imu", riferimento
 * "imu-<anno>").
 */
class CalcoloImu extends ReferencedEntity
{

    public const ORIGINE = 'Imu';

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $anno;
    protected bool $unicaSoluzione = false;
    protected float $impostaAnnua = 0.0;
    protected float $acconto = 0.0;
    protected float $saldo = 0.0;
    protected ?string $avvisi = null;
    protected ?string $differenze = null;
    protected SismaDateTime $dataElaborazione;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataElaborazione = new SismaDateTime();
    }

    public static function getRiferimento(int $anno): string
    {
        return 'imu-' . $anno;
    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return self::righe($this->avvisi);
    }

    /**
     * @return string[]
     */
    public function getDifferenze(): array
    {
        return self::righe($this->differenze);
    }

    private static function righe(?string $testo): array
    {
        return $testo === null ? [] : array_values(array_filter(explode("\n", $testo), fn(string $riga) => $riga !== ''));
    }
}
