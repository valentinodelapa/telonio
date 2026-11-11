<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Entities\Studio;

/**
 * Cessione a titolo oneroso di titoli, partecipazioni non qualificate o
 * cripto-attività in regime dichiarativo (fuori dal risparmio
 * amministrato e gestito), per il quadro RT: corrispettivo, costo o
 * valore di acquisto, costo rideterminato (rigo RT12 o RT42), Paese a
 * regime fiscale privilegiato (rigo RT16) e, per le cripto-attività,
 * detenzione dal 2024 o prima (colonne "ante 2025" dei righi RT41 e RT42).
 */
class CessioneFinanziaria extends BaseEntity
{

    public const TITOLI = 'T';
    public const CRIPTO = 'C';
    public const TIPI = [self::TITOLI => 'Titoli e partecipazioni non qualificate (sezione II, 26%)', self::CRIPTO => 'Cripto-attività (sezione V, 26%)'];

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $annoImposta;
    protected string $tipo = self::TITOLI;
    protected SismaDate $dataCessione;
    protected string $descrizione = '';
    protected float $corrispettivo = 0.0;
    protected float $costo = 0.0;
    protected bool $costoRideterminato = false;
    protected bool $paesePrivilegiato = false;
    protected bool $anteriore2025 = false;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataCessione = new SismaDate();
    }

    public function getDifferenza(): float
    {
        return round($this->corrispettivo - $this->costo, 2);
    }
}
