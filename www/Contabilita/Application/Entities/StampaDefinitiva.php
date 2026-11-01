<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use Telonio\Application\Entities\Studio;

/**
 * Stampa definitiva di un periodo del libro giornale (per esercizio) o di
 * un registro IVA (per anno solare), con le pagine numerate
 * progressivamente per anno (art. 2215 c.c., art. 39 D.P.R. 633/1972;
 * dal 2001 senza bollatura né vidimazione, art. 8 L. 383/2001).
 *
 * Le stampe di un libro si susseguono senza salti di periodo né di pagina;
 * le registrazioni dei periodi stampati non si modificano più. Il
 * contenuto si rigenera identico dai dati (stesse righe per pagina), per
 * la ristampa; si annulla solo l'ultima stampa del libro.
 */
class StampaDefinitiva extends BaseEntity
{

    /** Righe di dettaglio per pagina (formato A4 orizzontale). */
    public const RIGHE_PER_PAGINA = 34;

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected TipoStampaDefinitiva $tipo;
    protected ?EsercizioContabile $esercizioContabile = null;
    protected ?RegistroIva $registroIva = null;
    protected int $anno;
    protected SismaDate $dal;
    protected SismaDate $al;
    protected int $primaPagina;
    protected int $ultimaPagina;
    protected int $righePerPagina = self::RIGHE_PER_PAGINA;
    protected SismaDateTime $dataStampa;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataStampa = new SismaDateTime();
    }

    public function getDescrizione(): string
    {
        return ($this->tipo === TipoStampaDefinitiva::giornale ? 'Libro giornale' : $this->registroIva->getEtichetta())
                . ' ' . $this->dal->format('d/m/Y') . ' - ' . $this->al->format('d/m/Y');
    }

    public function getPagine(): string
    {
        return $this->anno . '/' . $this->primaPagina . ($this->ultimaPagina > $this->primaPagina ? ' - ' . $this->anno . '/' . $this->ultimaPagina : '');
    }
}
