<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\StatoCivile;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Application\Entities\Studio;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Soggetto dell'anagrafica unica dello studio: persona fisica, società o
 * ente. È il punto di aggancio di tutti i moduli della suite.
 *
 * I dati identificativi stabili (codice fiscale, dati di nascita) sono qui;
 * quelli che variano nel tempo (denominazione, partita IVA, sedi, cariche)
 * sono storicizzati in DatiFiscali, Indirizzo e Legame. Nominativo,
 * partita IVA e tipo di attività sono copie dei valori correnti, per
 * ricerche ed elenchi.
 */
class Soggetto extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected TipoSoggetto $tipoSoggetto;
    protected ?string $codice = null;
    protected string $codiceFiscale;
    protected string $nominativo;
    protected ?string $partitaIva = null;
    protected ?TipoAttivita $tipoAttivita = null;
    protected bool $clienteStudio = true;
    protected ?string $cognome = null;
    protected ?string $nome = null;
    protected ?Sesso $sesso = null;
    protected ?SismaDate $dataNascita = null;
    protected ?Comune $comuneNascita = null;
    protected ?Nazione $nazioneNascita = null;
    protected ?Nazione $cittadinanza = null;
    protected ?StatoCivile $statoCivile = null;
    protected ?SismaDate $dataDecesso = null;
    protected ?SismaDate $dataCostituzione = null;
    protected ?SismaDate $dataCessazione = null;
    protected ?string $note = null;
    protected SismaDateTime $dataInserimento;
    protected ?SismaDateTime $dataModifica = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInserimento = new SismaDateTime();
    }

    public function isPersonaFisica(): bool
    {
        return $this->tipoSoggetto->isPersonaFisica();
    }

    /**
     * Descrizione completa del tipo, es. "Persona fisica · Impresa individuale".
     */
    public function getDescrizioneTipo(): string
    {
        return $this->tipoSoggetto->getLabel() . ($this->tipoAttivita !== null ? ' · ' . $this->tipoAttivita->getLabel() : '');
    }

    /**
     * Persona fisica deceduta alla data indicata (di default oggi): la data
     * di decesso apre la successione (modulo Successioni) e chiude i dati
     * del soggetto.
     */
    public function isDeceduto(?\DateTimeInterface $data = null): bool
    {
        $data ??= new SismaDate();
        return ($this->dataDecesso !== null) && ($this->dataDecesso->format('Y-m-d') <= $data->format('Y-m-d'));
    }

    public function isCessato(?\DateTimeInterface $data = null): bool
    {
        $data ??= new SismaDate();
        return ($this->dataCessazione !== null) && ($this->dataCessazione->format('Y-m-d') <= $data->format('Y-m-d'));
    }

    /**
     * Data dalla quale il soggetto esiste: inizio della validità della
     * prima versione dei dati storicizzati, se non diversamente indicato.
     */
    public function getDataInizio(): ?SismaDate
    {
        return $this->isPersonaFisica() ? $this->dataNascita : $this->dataCostituzione;
    }
}
