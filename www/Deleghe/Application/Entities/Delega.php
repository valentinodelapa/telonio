<?php

namespace Deleghe\Application\Entities;

use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Soggetto;
use CommSuite\Application\Entities\Studio;
use Deleghe\Application\Enumerations\CodiceIdentificativo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\StatoDelega;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Delega di pagamento F24 di un soggetto dello studio per una scadenza.
 *
 * I dati della testata che nel modello appartengono a una sezione (codice
 * ufficio e atto per l'Erario, identificativo operazione e detrazione per
 * la sezione IMU) sono unici per delega e stanno qui; i righi sono in
 * Versamento. Il conto di addebito serve per l'invio telematico con addebito
 * sul conto del contribuente (o del secondo soggetto, per i codici
 * identificativi che lo richiedono).
 */
class Delega extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected ModelloF24 $modello = ModelloF24::ordinario;
    protected SismaDate $scadenza;
    protected ?string $descrizione = null;
    protected StatoDelega $stato = StatoDelega::bozza;
    protected ?string $codiceFiscaleCoobbligato = null;
    protected ?CodiceIdentificativo $codiceIdentificativo = null;
    protected ?ContoCorrente $contoAddebito = null;
    protected ?string $codiceUfficio = null;
    protected ?string $codiceAtto = null;
    protected ?string $identificativoOperazione = null;
    protected ?float $detrazioneImu = null;
    protected ?InvioTelematico $invioTelematico = null;
    protected ?SismaDate $dataQuietanza = null;
    protected ?string $protocollo = null;
    protected ?string $note = null;
    protected SismaDateTime $dataInserimento;
    protected ?SismaDateTime $dataModifica = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInserimento = new SismaDateTime();
    }

    public function isModificabile(): bool
    {
        return $this->stato->isModificabile();
    }
}
