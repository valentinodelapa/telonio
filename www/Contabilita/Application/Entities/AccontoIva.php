<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use Telonio\Application\Entities\Studio;

/**
 * Acconto IVA dell'anno (art. 6, c. 2, L. 405/1990), versato entro il 27
 * dicembre con codice 6013 (mensili) o 6035 (trimestrali) e scomputato
 * dalla liquidazione di dicembre o del quarto trimestre. Il versamento è
 * registrato nell'archivio di Deleghe F24 (riferimento
 * acconto-iva-<anno>).
 */
class AccontoIva extends BaseEntity
{

    /** Acconto non dovuto se inferiore (art. 6, c. 2, L. 405/1990). */
    public const IMPORTO_MINIMO = 103.29;
    public const ORIGINE = LiquidazioneIva::ORIGINE;

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $anno;
    protected PeriodicitaIva $periodicita;
    protected MetodoAccontoIva $metodo = MetodoAccontoIva::storico;
    protected float $base = 0.0;
    protected float $importo = 0.0;
    protected SismaDateTime $dataElaborazione;
    protected ?string $note = null;
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

    public static function getRiferimento(int $anno): string
    {
        return 'acconto-iva-' . $anno;
    }

    public function isDovuto(): bool
    {
        return $this->importo >= self::IMPORTO_MINIMO;
    }
}
