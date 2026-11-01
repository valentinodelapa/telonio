<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Enumerations\DirezioneFattura;
use Contabilita\Application\Enumerations\StatoFatturaElettronica;
use Contabilita\Application\Enumerations\TipoDocumentoFattura;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use Telonio\Application\Entities\Studio;

/**
 * Fattura elettronica importata nell'azienda: i dati principali per
 * l'elenco, il documento XML completo (estratto dalla busta firmata
 * .p7m) e, una volta registrata, la registrazione di prima nota. Un file
 * con un lotto di fatture produce una riga per fattura (indice del
 * corpo); l'impronta impedisce di caricare due volte la stessa fattura.
 */
class FatturaElettronica extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected DirezioneFattura $direzione;
    protected string $nomeFile;
    protected string $impronta;
    protected int $indiceCorpo = 1;
    protected TipoDocumentoFattura $tipoDocumento;
    protected string $numero;
    protected SismaDate $data;
    protected string $denominazioneControparte;
    protected ?string $partitaIvaControparte = null;
    protected ?string $codiceFiscaleControparte = null;
    protected ?string $codicePaese = null;
    protected ?float $importoTotale = null;
    protected ?Controparte $controparte = null;
    protected ?RegistrazioneContabile $registrazioneContabile = null;
    protected StatoFatturaElettronica $stato = StatoFatturaElettronica::daRegistrare;
    protected string $contenuto;
    protected SismaDateTime $dataImportazione;
    protected ?string $avvisi = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataImportazione = new SismaDateTime();
    }
}
