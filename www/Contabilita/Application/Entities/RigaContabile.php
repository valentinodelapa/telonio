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
 *
 * Attività della riga (Attivita): quella del registro IVA o quella
 * predefinita del conto, proposte al salvataggio; vuota per le righe comuni
 * a più attività, con le eventuali quote per numero di attività
 * (ripartizione, criteri oggettivi) o, senza, il criterio di ciascuna
 * imposta (Services\AttivitaSoggetto).
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
    protected ?Attivita $attivita = null;
    protected ?string $ripartizione = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Quote della riga comune per numero di attività.
     *
     * @return array<int, float> percentuali (somma 100)
     */
    public function getRipartizione(): array
    {
        return RigaIva::leggiRipartizione($this->ripartizione);
    }

    /**
     * Saldo della riga (dare meno avere).
     */
    public function getSaldo(): float
    {
        return round($this->dare - $this->avere, 2);
    }
}
