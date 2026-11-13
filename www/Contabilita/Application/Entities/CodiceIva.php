<?php

namespace Contabilita\Application\Entities;

use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\HelperClasses\RiferimentiIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
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
 *
 * La norma è quella fino al 31 dicembre 2026 (D.P.R. 633/1972 e leggi
 * speciali) e, dal 2027, quella del Testo unico IVA (D.Lgs. 10/2026). Un
 * codice può valere solo per le operazioni fino a una data: l'inversione
 * contabile per cellulari, prodotti elettronici, quote di emissione, gas
 * ed energia si applica fino al 31 dicembre 2026 (art. 17, c. 8, D.P.R.
 * 633/1972; art. 64, c. 8, D.Lgs. 10/2026).
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
    protected ?string $normaTestoUnico = null;
    protected ?SismaDate $validoFino = null;
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
     * Norma in vigore alla data indicata (di default oggi): dal 2027 quella
     * del Testo unico, se indicata.
     */
    public function getNorma(?\DateTimeInterface $data = null): ?string
    {
        return RiferimentiIva::isTestiUnici($data) && ($this->normaTestoUnico !== null) ? $this->normaTestoUnico : $this->norma;
    }

    /**
     * Il codice si applica alle operazioni effettuate alla data.
     */
    public function isValidoAl(\DateTimeInterface $data): bool
    {
        return ($this->validoFino === null) || ($data->format('Y-m-d') <= $this->validoFino->format('Y-m-d'));
    }

    /**
     * Imposta sull'imponibile, arrotondata al centesimo.
     */
    public function calcolaImposta(float $imponibile): float
    {
        return round($imponibile * $this->aliquota / 100, 2);
    }
}
