<?php

namespace Successioni\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Nazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\DirittoAbitazione;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipoDocumentoTitolo;
use Successioni\Application\Enumerations\TipologiaTerreno;
use Successioni\Application\Enumerations\TipoTitolo;
use Successioni\Application\Enumerations\TipoUnita;

/**
 * Bene dell'attivo ereditario con il diritto e la quota del defunto e il
 * valore della quota (base imponibile in euro interi).
 *
 * Terreni e fabbricati (EB, EC, e del catasto tavolare EL, EM) sono
 * immobili dell'archivio dell'Anagrafica: ubicazione e classamento si
 * leggono dall'immobile e dai dati catastali vigenti alla data del
 * decesso; un immobile aziendale indica l'azienda del quadro EN che lo
 * comprende (il suo valore conta nell'azienda). Aziende (EN), titoli e
 * quote (EO), aeromobili (EP), navi (EQ), rendite, crediti e altri beni
 * (ER, compresi conti e depositi) sono descritti qui.
 *
 * Le quote devolute ai beneficiari sono in DevoluzioneSuccessione.
 */
class BeneSuccessione extends ReferencedEntity
{

    protected int $id;
    protected DichiarazioneSuccessione $dichiarazioneSuccessione;
    protected QuadroBene $quadro;
    protected int $ordine = 1;
    protected ?Immobile $immobile = null;
    protected CodiceDiritto $codiceDiritto = CodiceDiritto::proprieta;
    protected float $quotaNumeratore = 1.0;
    protected int $quotaDenominatore = 1;
    protected ?int $valore = null;
    protected ?int $valoreEsente = null;
    protected ?int $valorePrecedente = null;
    protected ?string $natura = null;
    protected ?TipologiaTerreno $tipologiaTerreno = null;
    protected ?DirittoAbitazione $dirittoAbitazione = null;
    protected bool $beneEstero = false;
    protected ?int $impostaEstero = null;
    protected ?string $descrizione = null;
    protected ?TipoCespite $tipoCespite = null;
    protected ?TipoTitolo $tipoTitolo = null;
    protected ?string $codiceFiscaleEmittente = null;
    protected ?int $quantita = null;
    protected ?string $codiceTitolo = null;
    protected ?TipoDocumentoTitolo $tipoDocumento = null;
    protected ?SismaDate $dataDocumento = null;
    protected ?BeneSuccessione $azienda = null;
    protected bool $discordanzaIntestatario = false;
    protected bool $passaggiSenzaAtti = false;
    protected ?SismaDate $dataInventario = null;
    protected ?SismaDate $dataDeposito = null;
    protected ?string $cameraCommercio = null;
    protected ?string $marcaModello = null;
    protected ?string $nazionalita = null;
    protected ?int $annoCostruzione = null;
    protected ?int $annoIscrizione = null;
    protected ?string $numeroIscrizione = null;
    protected ?string $ufficioIscrizione = null;
    protected ?TipoUnita $tipoUnita = null;
    protected ?int $dimensione = null;
    protected ?Nazione $statoEstero = null;
    protected ?string $codiceStatoEstero = null;
    protected ?string $localitaEstero = null;
    protected ?string $indirizzoEstero = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getQuota(): float
    {
        return $this->quotaDenominatore > 0 ? $this->quotaNumeratore / $this->quotaDenominatore : 0.0;
    }

    /**
     * Valore complessivo della quota del defunto (per il quadro EO anche il
     * valore esente): le quote devolute devono sommare a questo importo.
     */
    public function getValoreTotale(): int
    {
        return (int) $this->valore + (int) $this->valoreEsente;
    }

    public function getDescrizioneBreve(): string
    {
        if ($this->immobile !== null) {
            return $this->immobile->getIdentificativo() . ' - ' . $this->immobile->comune->denominazione;
        }
        if ($this->isImmobileEstero()) {
            return trim(($this->indirizzoEstero ?? '') . ' ' . ($this->localitaEstero ?? '')) . ' - ' . $this->statoEstero?->denominazione;
        }
        return match ($this->quadro) {
            QuadroBene::aeromobili => trim($this->marcaModello . ' ' . $this->numeroIscrizione),
            QuadroBene::navi => trim(($this->descrizione ?? $this->tipoUnita?->getLabel() ?? '') . ' ' . $this->ufficioIscrizione . ' ' . $this->numeroIscrizione),
            default => (string) $this->descrizione,
        };
    }

    /**
     * Terreno o fabbricato situato all'estero (provincia "EE"): non è
     * nell'archivio immobili dell'Anagrafica, che riguarda il catasto
     * italiano, e non sconta le imposte ipotecaria e catastale.
     */
    public function isImmobileEstero(): bool
    {
        return in_array($this->quadro, [QuadroBene::terreni, QuadroBene::fabbricati], true) && $this->beneEstero;
    }

    public function isAziendale(): bool
    {
        return $this->azienda !== null;
    }
}
