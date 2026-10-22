<?php

namespace Deleghe\Application\Entities;

use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ObbligoCampo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Codice tributo delle tabelle dell'Agenzia delle Entrate, comune a tutti
 * gli studi e importato con il comando tabelle-f24, con le regole di
 * compilazione ("formalismi") della tabella:
 * - modalita: solo debito, solo credito o entrambi;
 * - formatoRateazione: 0000 (non compilare), 00MM (mese), NNRR (rata),
 *   FFFF (rata facoltativa), 00T0/00T1/00T2/00T3/00T4 (codice della
 *   regione, dell'ente, della provincia o del comune);
 * - anno di riferimento richiesto e anno massimo rispetto al versamento;
 * - codice ufficio, codice atto, identificativo operazione;
 * - per la sezione IMU, le tabelle del codice ente; per l'F24 ELIDE, il
 *   formalismo degli elementi identificativi;
 * - per la sezione accise, ente, provincia, codice identificativo richiesto
 *   dall'ente e mese del periodo;
 * - se il tributo è pagabile con i crediti certificati verso la PA.
 *
 * La chiave è sezione + codice. I codici non più presenti nelle tabelle
 * restano (le deleghe passate li referenziano) con inUso false.
 */
class CodiceTributo extends ReferencedEntity
{

    public const ELEMENTI_OBBLIGATORI = ['1', '4', '6'];
    public const ELEMENTI_NON_COMPILARE = '3';
    /**
     * Credito verso la PA certificato, compensabile con i tributi
     * dell'elenco del D.M. 14/1/2014 (art. 28-quinquies DPR 602/1973): il
     * rigo riporta il numero di certificazione.
     */
    public const CODICE_CREDITI_PA = 'PPAA';

    protected int $id;
    protected SezioneCodiceTributo $sezione;
    protected string $codice;
    protected string $descrizione;
    protected ?string $tipoTributo = null;
    protected ModalitaUtilizzo $modalita = ModalitaUtilizzo::debito;
    protected bool $soloAgenteRiscossione = false;
    protected string $formatoRateazione = '0000';
    protected bool $annoRichiesto = true;
    protected int $annoMassimo = 0;
    protected ObbligoCampo $codiceUfficio = ObbligoCampo::nonPrevisto;
    protected ObbligoCampo $codiceAtto = ObbligoCampo::nonPrevisto;
    protected ObbligoCampo $identificativoOperazione = ObbligoCampo::nonPrevisto;
    protected ?string $elementiIdentificativi = null;
    protected ?string $tabelleEnte = null;
    protected ?string $enteAccise = null;
    protected bool $provinciaRichiesta = false;
    protected bool $meseRichiesto = false;
    protected ?string $codiceIdentificativoEnte = null;
    protected bool $compensabileCreditiPa = false;
    protected bool $inUso = true;

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

    public function isCreditoPa(): bool
    {
        return $this->codice === self::CODICE_CREDITI_PA;
    }

    public function isImu(): bool
    {
        return $this->tipoTributo === 'IMU';
    }

    /**
     * Tabelle ammesse per il codice ente della sezione IMU (es. ['T4']).
     *
     * @return string[]
     */
    public function getTabelleEnte(): array
    {
        return $this->tabelleEnte === null ? [] : explode(',', $this->tabelleEnte);
    }

    /**
     * Descrizione del formato atteso nel campo rateazione, per gli aiuti.
     */
    public function getDescrizioneRateazione(): string
    {
        return match ($this->formatoRateazione) {
            '0000' => 'non va compilato',
            '00MM' => 'mese di riferimento (00MM)',
            'NNRR' => 'rata e numero di rate (NNRR)',
            'FFFF' => 'rata e numero di rate (NNRR), facoltativo',
            '00T0' => 'codice della regione (00RR)',
            '00T1' => 'codice dell\'ente locale (tabella T1)',
            '00T2' => 'sigla della provincia (00PP)',
            '00T3' => 'codice dell\'ente territoriale (tabella T3)',
            '00T4' => 'codice catastale del comune',
            default => $this->formatoRateazione,
        };
    }
}
