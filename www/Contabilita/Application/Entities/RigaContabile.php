<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Riga in partita doppia: sottoconto, importo in dare o in avere e, per
 * le righe del partitario, il cliente o fornitore. Soggetto e data sono
 * copiati dalla registrazione (Services\SalvataggioRegistrazione) per
 * leggere i movimenti per periodo senza join.
 */
class RigaContabile extends BaseEntity
{

    protected int $id;
    protected RegistrazioneContabile $registrazioneContabile;
    protected Soggetto $soggetto;
    protected SismaDate $dataRegistrazione;
    protected int $ordine = 0;
    protected Conto $conto;
    protected ?Controparte $controparte = null;
    protected float $dare = 0.0;
    protected float $avere = 0.0;
    protected ?string $descrizione = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Saldo della riga (dare meno avere).
     */
    public function getSaldo(): float
    {
        return round($this->dare - $this->avere, 2);
    }
}
