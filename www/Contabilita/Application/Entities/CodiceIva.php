<?php

namespace Contabilita\Application\Entities;

use Contabilita\Application\Enumerations\NaturaIva;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Codice IVA: aliquota o natura dell'operazione senza imposta, con la
 * detraibilità proposta sugli acquisti e l'inversione contabile (acquisti
 * in cui il cessionario integra l'imposta) o la ventilazione (corrispettivi
 * senza aliquota, ripartiti nella liquidazione in proporzione agli acquisti
 * per rivendita). Senza studio: codici della
 * suite, aggiornati da "sisma tabelle-contabilita" (id sotto
 * PRIMO_ID_STUDIO).
 */
class CodiceIva extends ReferencedEntity
{

    public const PRIMO_ID_STUDIO = 1000;

    protected int $id;
    protected ?Studio $studio = null;
    protected string $codice;
    protected string $descrizione;
    protected float $aliquota = 0.0;
    protected ?NaturaIva $natura = null;
    protected float $detraibilita = 100.0;
    protected bool $inversioneContabile = false;
    protected bool $ventilazione = false;
    protected ?string $norma = null;
    protected bool $attivo = true;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getEtichetta(): string
    {
        return $this->codice . ' - ' . $this->descrizione;
    }

    /**
     * Imposta sull'imponibile, arrotondata al centesimo.
     */
    public function calcolaImposta(float $imponibile): float
    {
        return round($imponibile * $this->aliquota / 100, 2);
    }
}
