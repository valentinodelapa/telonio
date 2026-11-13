<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Registrazione di prima nota: numero progressivo nell'esercizio, causale,
 * documento e controparte, e per i documenti IVA il registro, il
 * protocollo e la data di competenza che determina il periodo di
 * liquidazione (per gli acquisti può precedere la registrazione: art. 1,
 * c. 1, D.P.R. 100/1998). Le righe sono RigaContabile (partita doppia) e
 * RigaIva (registro).
 *
 * Origine e riferimento identificano le registrazioni generate (es. il
 * giroconto della liquidazione IVA), sostituite a ogni elaborazione.
 */
class RegistrazioneContabile extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected EsercizioContabile $esercizioContabile;
    protected int $numero = 0;
    protected SismaDate $dataRegistrazione;
    protected CausaleContabile $causale = CausaleContabile::giroconto;
    protected ?string $descrizione = null;
    protected ?Controparte $controparte = null;
    protected ?string $numeroDocumento = null;
    protected ?SismaDate $dataDocumento = null;
    protected ?RegistroIva $registroIva = null;
    protected ?int $protocolloIva = null;
    protected ?SismaDate $dataCompetenzaIva = null;
    protected EsigibilitaIva $esigibilita = EsigibilitaIva::immediata;
    protected ?float $totaleDocumento = null;
    /** imposta di bollo assolta in modo virtuale sulla fattura elettronica emessa */
    protected ?float $impostaBollo = null;
    protected bool $importazione = false;
    protected ?string $origine = null;
    protected ?string $riferimentoOrigine = null;
    protected SismaDateTime $dataInserimento;
    protected ?SismaDateTime $dataModifica = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataRegistrazione = new SismaDate();
        $this->dataInserimento = new SismaDateTime();
    }

    public function isGenerata(): bool
    {
        return $this->origine !== null;
    }

    public function isModificabile(): bool
    {
        return $this->esercizioContabile->chiuso === false;
    }

    public function getDescrizioneCompleta(): string
    {
        $documento = $this->numeroDocumento === null ? '' : ' n. ' . $this->numeroDocumento . ($this->dataDocumento === null ? '' : ' del ' . $this->dataDocumento->format('d/m/Y'));
        return trim(($this->descrizione ?? $this->causale->getLabel()) . $documento);
    }
}
