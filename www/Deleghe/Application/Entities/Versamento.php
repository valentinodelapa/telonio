<?php

namespace Deleghe\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use Deleghe\Application\Enumerations\CodiceIdentificativo;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Versamento del contribuente: un rigo del modello F24 con la sua data di
 * versamento. Senza delega è "da versare" (archivio del contribuente); la
 * generazione delle deleghe lo colloca nella delega della sua scadenza.
 * Quando è in una delega la scadenza coincide con quella della delega.
 *
 * I campi valorizzati dipendono dalla sezione (SezioneF24::getCampi):
 * - codiceEnte: codice regione, codice catastale del comune o codice
 *   dell'ente previdenziale;
 * - codiceSede: sede INPS, INAIL o dell'altro ente;
 * - posizione: matricola INPS, codice ditta INAIL o codice posizione;
 * - rateazione: rateazione, regione/provincia o mese di riferimento;
 * - tipoElide, elementiIdentificativi: righi dell'F24 ELIDE;
 * - provincia, codiceIdentificativoEnte, meseRiferimento: sezione accise;
 * - numeroCertificazione: credito certificato verso la PA (Erario, PPAA);
 * - detrazioneImu: detrazione per l'abitazione principale già sottratta
 *   dall'importo (rigo IMU 3912 generato dal modulo IMU); la delega ne
 *   riporta la somma nel campo "detrazione" della sezione.
 *
 * L'utilizzo in compensazione di un credito dell'archivio ha il credito
 * collegato e l'importo a credito.
 *
 * I versamenti generati da altri moduli hanno origine e riferimentoOrigine:
 * il modulo li sostituisce a ogni ricalcolo finché non sono in una delega
 * confermata (Services\ArchivioVersamenti).
 *
 * Secondo codice fiscale: alcuni versamenti vanno in una delega con un
 * secondo soggetto fissato dalla norma (es. imposta di successione:
 * l'erede versa con il codice fiscale del defunto e codice identificativo
 * 08, risoluzione n. 2/E del 2025). Il versamento lo indica in
 * codiceFiscaleCoobbligato e codiceIdentificativo e va solo in deleghe con
 * lo stesso secondo soggetto (Services\GenerazioneDeleghe::isCompatibile).
 *
 * pianoRateazione è valorizzato sul versamento originario pagato a rate:
 * non è più da versare, al suo posto ci sono le rate generate dal piano.
 */
class Versamento extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected SismaDate $scadenza;
    protected ?Delega $delega = null;
    protected ?string $descrizione = null;
    protected SezioneF24 $sezione;
    protected int $ordine = 0;
    protected ?CodiceTributo $codiceTributo = null;
    protected ?string $codiceEnte = null;
    protected ?string $codiceSede = null;
    protected ?string $causale = null;
    protected ?string $posizione = null;
    protected ?string $cifraControllo = null;
    protected ?string $numeroRiferimento = null;
    protected ?string $rateazione = null;
    protected ?int $annoRiferimento = null;
    protected ?string $periodoDa = null;
    protected ?string $periodoA = null;
    protected bool $ravvedimento = false;
    protected bool $immobiliVariati = false;
    protected bool $acconto = false;
    protected bool $saldo = false;
    protected ?int $numeroImmobili = null;
    protected ?string $tipoElide = null;
    protected ?string $elementiIdentificativi = null;
    protected ?string $provincia = null;
    protected ?string $codiceIdentificativoEnte = null;
    protected ?int $meseRiferimento = null;
    protected ?string $numeroCertificazione = null;
    protected ?float $detrazioneImu = null;
    protected float $importoDebito = 0.0;
    protected float $importoCredito = 0.0;
    protected ?Credito $credito = null;
    protected ?string $origine = null;
    protected ?string $riferimentoOrigine = null;
    protected ?string $codiceFiscaleCoobbligato = null;
    protected ?CodiceIdentificativo $codiceIdentificativo = null;
    protected ?PianoRateazione $pianoRateazione = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isGenerata(): bool
    {
        return $this->origine !== null;
    }

    public function isDaVersare(): bool
    {
        return $this->delega === null;
    }

    /**
     * Modificabile se da versare o in una delega in bozza.
     */
    public function isModificabile(): bool
    {
        return ($this->delega === null) || $this->delega->isModificabile();
    }

    /**
     * Codice del rigo come appare nel modello: codice tributo o causale.
     */
    public function getCodice(): string
    {
        return $this->codiceTributo?->codice ?? $this->causale ?? '';
    }

    public function getDescrizione(): string
    {
        return $this->codiceTributo?->descrizione ?? '';
    }

    public function hasSecondoSoggettoRichiesto(): bool
    {
        return $this->codiceFiscaleCoobbligato !== null;
    }
}
