<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\TipoOpzioneTassazione;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Opzione della società per la tassazione di gruppo (consolidato nazionale
 * o trasparenza fiscale) per gli esercizi dall'anno di inizio all'anno di
 * fine (tre esercizi, rinnovabili). Per il consolidato la consolidante è
 * la società controllante (o designata) che presenta il modello CNM; la
 * consolidante stessa ha l'opzione con sé come consolidante.
 */
class OpzioneTassazione extends BaseEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected TipoOpzioneTassazione $tipoOpzione;
    protected ?Soggetto $consolidante = null;
    protected int $annoInizio;
    protected int $annoFine;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isVigenteNel(int $anno): bool
    {
        return ($anno >= $this->annoInizio) && ($anno <= $this->annoFine);
    }

    public function isConsolidante(): bool
    {
        return ($this->tipoOpzione === TipoOpzioneTassazione::consolidatoNazionale) && ($this->consolidante !== null) && ($this->consolidante->id === $this->soggetto->id);
    }
}
