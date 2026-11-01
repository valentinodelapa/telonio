<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Esercizio contabile dell'azienda, identificato dall'anno di chiusura
 * (di norma coincide con l'anno solare). Chiuso, le sue registrazioni non
 * si modificano più.
 */
class EsercizioContabile extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $anno;
    protected SismaDate $dataInizio;
    protected SismaDate $dataFine;
    protected bool $chiuso = false;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function comprende(\DateTimeInterface $data): bool
    {
        $giorno = $data->format('Y-m-d');
        return ($giorno >= $this->dataInizio->format('Y-m-d')) && ($giorno <= $this->dataFine->format('Y-m-d'));
    }

    public function getEtichetta(): string
    {
        return ($this->dataInizio->format('m-d') === '01-01') && ($this->dataFine->format('m-d') === '12-31')
                ? (string) $this->anno
                : $this->dataInizio->format('d/m/Y') . ' - ' . $this->dataFine->format('d/m/Y');
    }
}
