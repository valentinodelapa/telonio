<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\CodiceAteco;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Attività dell'azienda con contabilità separata (art. 36 D.P.R.
 * 633/1972): i registri e i sezionali IVA dell'attività ne separano le
 * operazioni, le liquidazioni periodiche sono distinte (compensate nel
 * periodo) e la dichiarazione IVA ha un modulo per attività, nell'ordine
 * del numero. La periodicità propria si indica quando la separazione è
 * obbligatoria e il volume d'affari dell'attività la determina.
 */
class AttivitaIva extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $numero = 1;
    protected string $descrizione;
    protected ?CodiceAteco $codiceAteco = null;
    protected ?PeriodicitaIva $periodicita = null;
    protected ?RegimeDetrazioneIva $regimeDetrazioneIva = null;
    protected ?RegimeDetrazioneIva $opzioneOrdinaria = null;
    protected ?int $opzioneDal = null;
    protected ?int $opzioneAl = null;
    protected int $dalAnno;
    protected ?int $alAnno = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isOpzioneAttivaNel(int $anno): bool
    {
        return ($this->opzioneOrdinaria !== null) && ($this->opzioneDal <= $anno) && (($this->opzioneAl === null) || ($this->opzioneAl >= $anno));
    }

    public function isAttivaNel(int $anno): bool
    {
        return ($this->dalAnno <= $anno) && (($this->alAnno === null) || ($this->alAnno >= $anno));
    }

    public function getEtichetta(): string
    {
        return $this->numero . ' - ' . $this->descrizione;
    }
}
