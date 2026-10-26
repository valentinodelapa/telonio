<?php

namespace GestioneStudio\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use Telonio\Application\Entities\Utente;
use GestioneStudio\Application\Enumerations\StatoLavorazione;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;

/**
 * Stato di lavorazione dello studio su una scadenza di qualsiasi modulo,
 * collegato a modulo e riferimento della scadenza (Telonio Scadenza).
 * Data e descrizione sono quelle alla registrazione: la scadenza resta
 * calcolata dal modulo.
 */
class LavorazioneScadenza extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected string $modulo;
    protected string $riferimento;
    protected ?Soggetto $soggetto = null;
    protected SismaDate $dataScadenza;
    protected string $descrizione;
    protected StatoLavorazione $stato = StatoLavorazione::daFare;
    protected ?Utente $responsabile = null;
    protected ?string $note = null;
    protected ?SismaDate $dataCompletamento = null;
    protected ?Utente $utenteCompletamento = null;
    protected SismaDateTime $dataModifica;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataModifica = new SismaDateTime();
    }
}
