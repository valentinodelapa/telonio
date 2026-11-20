<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Entities\Studio;

/**
 * Contratto di locazione finanziaria dell'azienda (utilizzatore): durata,
 * costo sostenuto dalla società concedente, canoni complessivi (compreso
 * il maxicanone) e prezzo di riscatto. Serve per la quota interessi dei
 * canoni (metodo forfetario del D.M. 24 aprile 1998, Services\Leasing),
 * indeducibile ai fini IRAP (rigo IC44), e per il valore dei beni in
 * leasing nel prospetto delle società di comodo (art. 30 L. 724/1994: costo
 * della concedente). L'immobile dell'Anagrafica, se il bene è un
 * fabbricato, ne dà la categoria catastale.
 */
class ContrattoLeasing extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected string $descrizione;
    protected ?string $concedente = null;
    protected ?string $numeroContratto = null;
    protected SismaDate $dataInizio;
    protected SismaDate $dataFine;
    /** riscatto anticipato o fine del contratto prima della scadenza */
    protected ?SismaDate $dataRiscatto = null;
    protected float $costoConcedente = 0.0;
    protected float $totaleCanoni = 0.0;
    protected float $prezzoRiscatto = 0.0;
    protected bool $beneImmobile = false;
    protected ?Immobile $immobile = null;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Ultimo giorno del contratto: riscatto o scadenza.
     */
    public function getDataTermine(): SismaDate
    {
        return $this->dataRiscatto ?? $this->dataFine;
    }
}
