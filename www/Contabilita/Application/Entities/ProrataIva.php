<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use Telonio\Application\Entities\Studio;

/**
 * Percentuale di detrazione provvisoria dell'anno per le aziende che
 * effettuano anche operazioni esenti (art. 19, c. 5, e art. 19-bis D.P.R.
 * 633/1972): nelle liquidazioni periodiche si applica la percentuale
 * definitiva dell'anno precedente, conguagliata nella dichiarazione
 * annuale. Proposta dalla dichiarazione IVA dell'anno precedente
 * (origine "Dichiarativi") o inserita a mano. Con le attività separate
 * il pro-rata è dell'attività.
 */
class ProrataIva extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $anno;
    protected int $percentuale = 100;
    protected ?string $origine = null;
    protected ?string $note = null;
    protected ?AttivitaIva $attivitaIva = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Imposta detraibile ridotta dalla percentuale, al centesimo.
     */
    public function applica(float $detraibile): float
    {
        return round($detraibile * $this->percentuale / 100, 2);
    }
}
