<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Enumerations\TipoGaranzia;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Entities\Studio;

/**
 * Garanzia prestata per un rimborso IVA (art. 38-bis, c. 5, D.P.R. 633/1972):
 * dura tre anni dall'esecuzione del rimborso o, se inferiore, il periodo
 * mancante alla decadenza dell'accertamento (art. 57: 31 dicembre del quinto
 * anno successivo alla presentazione della dichiarazione dell'anno).
 */
class GaranziaRimborso extends BaseEntity
{

    /** Anni dall'esecuzione del rimborso */
    public const DURATA_ANNI = 3;

    /** Anni dall'anno d'imposta alla decadenza: presentazione nell'anno successivo più cinque */
    public const ANNI_DECADENZA = 6;

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $annoImposta;
    protected TipoGaranzia $tipo;
    protected ?string $garante = null;
    protected ?string $estremi = null;
    protected float $importo = 0.0;
    protected SismaDate $dataRilascio;
    protected ?SismaDate $dataRimborso = null;
    protected ?SismaDate $dataScadenza = null;
    protected ?SismaDate $dataSvincolo = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Scadenza di legge, nota dall'esecuzione del rimborso.
     */
    public function getScadenzaProposta(): ?SismaDate
    {
        if ($this->dataRimborso === null) {
            return null;
        }
        $durata = (new \DateTimeImmutable($this->dataRimborso->format('Y-m-d')))->modify('+' . self::DURATA_ANNI . ' years')->format('Y-m-d');
        return new SismaDate(min($durata, ($this->annoImposta + self::ANNI_DECADENZA) . '-12-31'));
    }

    public function isSvincolata(): bool
    {
        return $this->dataSvincolo !== null;
    }
}
