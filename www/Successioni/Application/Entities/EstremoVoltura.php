<?php

namespace Successioni\Application\Entities;

use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Estremo di un atto (trascrizione o registrazione) che ricostruisce la
 * provenienza di un immobile i cui dati dell'intestatario in catasto non
 * coincidono con quelli del defunto (quadro EI, sezione II).
 */
class EstremoVoltura extends BaseEntity
{

    public const TRASCRIZIONE = 'T';
    public const REGISTRAZIONE = 'R';

    protected int $id;
    protected BeneSuccessione $beneSuccessione;
    protected string $tipo = self::TRASCRIZIONE;
    protected ?UfficioTrascrizione $ufficioTrascrizione = null;
    protected ?UfficioRegistrazione $ufficioRegistrazione = null;
    protected int $anno;
    protected ?string $volume = null;
    protected string $numero;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function isTrascrizione(): bool
    {
        return $this->tipo === self::TRASCRIZIONE;
    }

    public function getDescrizione(): string
    {
        return $this->isTrascrizione() ? 'Trascrizione ' . $this->ufficioTrascrizione?->codice . ' reg. part. ' . $this->numero . '/' . $this->anno
                : 'Registrazione ' . $this->ufficioRegistrazione?->codice . ' ' . $this->anno . ' vol. ' . $this->volume . ' n. ' . $this->numero;
    }
}
