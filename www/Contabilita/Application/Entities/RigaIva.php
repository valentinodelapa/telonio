<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Riga del registro IVA: codice IVA, imponibile e imposta con il segno
 * del documento (negativi nelle note di credito), percentuale detraibile
 * sugli acquisti, acquisto di beni per la rivendita (base della
 * ventilazione dei corrispettivi) e conto di costo o ricavo di
 * contropartita. Soggetto,
 * registro e data di competenza sono copiati dalla registrazione
 * (Services\SalvataggioRegistrazione) per le interrogazioni per periodo.
 */
class RigaIva extends BaseEntity
{

    protected int $id;
    protected RegistrazioneContabile $registrazioneContabile;
    protected Soggetto $soggetto;
    protected RegistroIva $registroIva;
    protected SismaDate $dataCompetenzaIva;
    protected int $ordine = 0;
    protected CodiceIva $codiceIva;
    protected float $imponibile = 0.0;
    protected float $imposta = 0.0;
    protected float $detraibilita = 100.0;
    protected bool $rivendita = false;
    protected ?Conto $conto = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Imposta detraibile (acquisti), arrotondata al centesimo.
     */
    public function getImpostaDetraibile(): float
    {
        return round($this->imposta * $this->detraibilita / 100, 2);
    }

    public function getImpostaIndetraibile(): float
    {
        return round($this->imposta - $this->getImpostaDetraibile(), 2);
    }
}
