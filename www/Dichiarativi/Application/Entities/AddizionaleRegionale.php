<?php

namespace Dichiarativi\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Aliquote dell'addizionale regionale all'IRPEF della regione o provincia
 * autonoma per l'anno d'imposta (tabella delle istruzioni del modello
 * Redditi PF; formati delle regole in Database/schema.sql).
 */
class AddizionaleRegionale extends BaseEntity
{

    protected int $id;
    protected int $anno;
    protected string $codice;
    protected string $regione;
    protected string $scaglioni;
    protected string $modalita;
    protected ?float $esenzioneReddito = null;
    protected ?string $sogliaAliquota = null;
    protected ?string $detrazioni = null;
    protected ?string $detrazioneCrescente = null;
    protected ?string $deduzione = null;
    protected ?string $casiParticolari = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
