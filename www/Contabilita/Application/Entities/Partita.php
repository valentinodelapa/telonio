<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\TipoPartita;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Partita del partitario clienti e fornitori: nasce dalla registrazione
 * del documento (fattura, addebito), dell'insoluto che riapre un
 * documento, dell'acconto non abbinato o della nota di credito non
 * abbinata, per l'importo sul sottoconto della controparte. Si chiude con
 * gli abbinamenti (Abbinamento): il residuo è l'importo meno quanto
 * abbinato.
 */
class Partita extends ReferencedEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected Controparte $controparte;
    protected Conto $conto;
    protected RegistrazioneContabile $registrazioneContabile;
    protected TipoPartita $tipo = TipoPartita::documento;
    protected bool $cliente = true;
    protected SismaDate $data;
    protected ?string $numeroDocumento = null;
    protected ?SismaDate $dataDocumento = null;
    protected ?SismaDate $dataScadenza = null;
    protected float $importo = 0.0;
    protected ?Partita $partitaOrigine = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->data = new SismaDate();
    }

    public function getEtichetta(): string
    {
        $documento = $this->numeroDocumento === null ? $this->registrazioneContabile->causale->getLabel() : 'n. ' . $this->numeroDocumento;
        $data = ($this->dataDocumento ?? $this->data)->format('d/m/Y');
        return ($this->tipo === TipoPartita::documento ? '' : $this->tipo->getLabel() . ' - ') . $documento . ' del ' . $data;
    }
}
