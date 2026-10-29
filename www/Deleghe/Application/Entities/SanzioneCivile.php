<?php

namespace Deleghe\Application\Entities;

use Deleghe\Application\Enumerations\RegimeSanzioneCivile;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Sanzioni civili per contributi INPS o premi INAIL versati in ritardo con
 * la delega (data di versamento = scadenza della delega), calcolate da
 * Services\CalcoloSanzioniCivili.
 *
 * Di norma l'ente calcola e richiede le sanzioni civili (avviso, nota di
 * rettifica): il calcolo è allora un prospetto. Con una causale di
 * versamento (es. SPCF per piccoli coloni e compartecipanti familiari,
 * SLAA/SLAS per lavoratori autonomi e aziende agricole, dalla tabella delle
 * causali INPS) la sanzione diventa un rigo della delega con origine
 * "SanzioniCivili".
 *
 * Se i contributi sono un versamento scaduto dell'archivio (es. una rata
 * INPS non pagata) il versamento è collegato (versamentoTardivo) e passa
 * nella delega insieme al calcolo; torna alla sua data se il calcolo viene
 * eliminato.
 */
class SanzioneCivile extends BaseEntity
{

    public const ORIGINE = 'SanzioniCivili';

    protected int $id;
    protected Delega $delega;
    protected ?Versamento $versamentoTardivo = null;
    protected SezioneF24 $sezione;
    protected string $riferimento;
    protected float $importoContributi;
    protected SismaDate $scadenzaOriginale;
    protected RegimeSanzioneCivile $regime = RegimeSanzioneCivile::omissione;
    protected bool $spontaneo = true;
    protected ?string $causale = null;
    protected ?string $codiceSede = null;
    protected ?string $posizione = null;
    protected ?string $periodoDa = null;
    protected ?string $periodoA = null;
    protected int $giorniRitardo = 0;
    protected float $sanzione = 0.0;
    protected bool $tettoApplicato = false;
    protected string $dettaglio = '[]';

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getRiferimentoOrigine(): string
    {
        return 'sanzione-civile-' . $this->id;
    }

    /**
     * @return array<array{dal: string, al: string, giorni: int, tasso: float, sanzione: float}>
     */
    public function getPeriodi(): array
    {
        return json_decode($this->dettaglio, true) ?? [];
    }
}
