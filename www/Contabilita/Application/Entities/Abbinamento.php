<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\TipoChiusura;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Abbinamento di una registrazione (incasso, pagamento, nota di credito,
 * compensazione, documento che usa un acconto) a una partita, per
 * l'importo che la chiude e con il modo della chiusura. La partita
 * collegata è l'acconto o la nota di credito usati, o il documento
 * chiuso da questi. "Automatico" indica gli abbinamenti creati dal
 * comando di migrazione dalle registrazioni precedenti, da verificare.
 */
class Abbinamento extends BaseEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected Controparte $controparte;
    protected Partita $partita;
    protected RegistrazioneContabile $registrazioneContabile;
    protected SismaDate $data;
    protected TipoChiusura $tipo = TipoChiusura::denaro;
    protected float $importo = 0.0;
    protected ?Partita $partitaCollegata = null;
    protected bool $automatico = false;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->data = new SismaDate();
    }
}
