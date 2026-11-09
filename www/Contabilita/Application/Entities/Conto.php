<?php

namespace Contabilita\Application\Entities;

use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\VoceCee;
use SismaFramework\Orm\ExtendedClasses\SelfReferencedEntity;

/**
 * Conto del piano: mastro, conto o sottoconto, con il conto superiore
 * (parentConto). Si registra solo sui sottoconti; la funzione lo indica
 * alle proposte automatiche, la voce CEE lo raccorda agli schemi di
 * bilancio.
 */
class Conto extends SelfReferencedEntity
{

    protected int $id;
    protected PianoConti $pianoConti;
    protected ?Conto $parentConto = null;
    protected string $codice;
    protected string $descrizione;
    protected LivelloConto $livello = LivelloConto::sottoconto;
    protected NaturaConto $natura;
    protected ?FunzioneConto $funzione = null;
    protected ?VoceCee $voceCee = null;
    protected ?string $rigoRedditi = null;
    protected ?float $percentualeDeducibile = null;
    protected ?string $variazioneRedditi = null;
    protected bool $attivo = true;

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
        return $this->codice . ' ' . $this->descrizione;
    }

    public function isMovimentabile(): bool
    {
        return ($this->livello === LivelloConto::sottoconto) && $this->attivo;
    }
}
