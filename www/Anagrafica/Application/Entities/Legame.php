<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Interfaces\Storicizzabile;
use Anagrafica\Application\Traits\StoricizzabileTrait;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Relazione storicizzata tra due soggetti dell'anagrafica: il soggetto
 * collegato ricopre il ruolo indicato presso il soggetto (es. Mario Rossi
 * è legale rappresentante di Alfa S.r.l. dal 01/01/2020) o ha il legame
 * familiare indicato con il soggetto (es. Laura Rossi è coniuge di Mario
 * Rossi dal 15/06/2010). Per i familiari: disabilità (figli, casella D),
 * convivenza (ascendenti e altri familiari), percentuale della detrazione
 * dei figli (100, 50, 0 o C per il primo figlio senza l'altro genitore;
 * vuota per la proposta automatica) e affidamento con la detrazione al 100%.
 */
class Legame extends BaseEntity implements Storicizzabile
{

    use StoricizzabileTrait;

    protected int $id;
    protected Soggetto $soggetto;
    protected Soggetto $soggettoCollegato;
    protected RuoloLegame $ruolo;
    protected SismaDate $validoDal;
    protected ?SismaDate $validoAl = null;
    protected ?float $quotaPartecipazione = null;
    protected ?string $note = null;
    protected bool $disabile = false;
    protected bool $convivente = false;
    protected ?string $percentualeDetrazione = null;
    protected bool $affidamento = false;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->validoDal = new SismaDate();
    }
}
