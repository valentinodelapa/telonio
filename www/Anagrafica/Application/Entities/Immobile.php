<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\TipoCatasto;
use Telonio\Application\Entities\Studio;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Unità immobiliare (fabbricato) o particella (terreno) dell'archivio
 * immobili dello studio, identificata dai dati catastali.
 *
 * L'immobile è unico nello studio anche se posseduto da più soggetti
 * (comproprietari, nudo proprietario e usufruttuario): i possessi lo
 * collegano ai soggetti, i dati catastali (classamento, rendita) sono
 * storicizzati in DatiCatastali.
 */
class Immobile extends ReferencedEntity
{

    public const PROVINCE_TAVOLARI = ['BZ', 'TN', 'GO', 'TS'];

    protected int $id;
    protected Studio $studio;
    protected TipoCatasto $tipoCatasto;
    protected Comune $comune;
    protected ?string $sezione = null;
    protected string $foglio;
    protected string $particella;
    protected ?string $subalterno = null;
    protected ?string $indirizzo = null;
    protected ?string $numeroCivico = null;
    protected ?string $piano = null;
    protected ?string $interno = null;
    protected ?string $descrizione = null;
    protected ?string $partitaTavolare = null;
    protected ?string $corpoTavolare = null;
    protected ?string $comuneCatastale = null;
    protected ?string $porzioneMateriale = null;
    protected ?string $note = null;
    protected SismaDateTime $dataInserimento;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInserimento = new SismaDateTime();
    }

    /**
     * Immobile del libro fondiario (catasto tavolare): tutti quelli delle
     * province di Bolzano, Trento, Gorizia e Trieste e quelli con la partita
     * tavolare in alcuni comuni di Belluno, Brescia, Udine e Vicenza.
     */
    public function isTavolare(): bool
    {
        return ($this->partitaTavolare !== null) || in_array($this->comune->siglaProvincia, self::PROVINCE_TAVOLARI, true);
    }

    /**
     * Es. "Sez. B Fg. 12 Part. 345 Sub. 6".
     */
    public function getIdentificativo(): string
    {
        return trim(($this->sezione !== null ? 'Sez. ' . $this->sezione . ' ' : '')
                . 'Fg. ' . $this->foglio . ' Part. ' . $this->particella
                . ($this->subalterno !== null ? ' Sub. ' . $this->subalterno : ''));
    }

    public function getUbicazione(): string
    {
        $indirizzo = trim(($this->indirizzo ?? '') . ($this->numeroCivico !== null ? ', ' . $this->numeroCivico : ''));
        $dettagli = trim(($this->piano !== null ? 'piano ' . $this->piano : '') . ($this->interno !== null ? ' int. ' . $this->interno : ''));
        return implode(' - ', array_filter([$indirizzo, $dettagli, $this->comune->getEtichetta()], fn($parte) => $parte !== ''));
    }

    /**
     * Chiave dell'identificativo catastale, usata per riconoscere un
     * immobile già presente nell'archivio dello studio.
     */
    public function getChiaveCatastale(): string
    {
        return implode('|', [$this->tipoCatasto->value, $this->comune->codiceCatastale, mb_strtoupper($this->sezione ?? ''), mb_strtoupper($this->foglio), mb_strtoupper($this->particella), mb_strtoupper($this->subalterno ?? '')]);
    }
}
