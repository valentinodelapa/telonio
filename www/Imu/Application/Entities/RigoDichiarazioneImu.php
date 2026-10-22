<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use Imu\Application\Enumerations\CaratteristicaDichiarata;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Quadro descrittivo di un immobile nella dichiarazione IMU: una vicenda
 * (acquisto, cessione, inizio o termine di una riduzione o esenzione,
 * altro) con i dati alla sua data. Più vicende dello stesso immobile in
 * date diverse sono più righi con lo stesso progressivo immobile e numeri
 * d'ordine crescenti.
 *
 * Valore: base imponibile per intero, indipendentemente dalla quota.
 * Codici: riduzione 0-5 (ENC 0-3), esenzione 0-3 (ENC: 1 = esente), come
 * nelle istruzioni del modello. Nel modello ENC il quadro A contiene gli
 * immobili interamente imponibili, il quadro B quelli esenti o a utilizzo
 * misto (dati dell'utilizzo in UtilizzoMistoEnc).
 */
class RigoDichiarazioneImu extends BaseEntity
{

    public const RIDUZIONI = ['0' => 'Nessuna riduzione', '1' => 'Immobile storico o artistico', '2' => 'Immobile inagibile o inabitabile', '3' => 'Immobile in comodato',
        '4' => 'Pensionato residente all\'estero', '5' => 'Altre riduzioni'];
    public const RIDUZIONI_ENC = ['0' => 'Nessuna riduzione', '1' => 'Immobile storico o artistico', '2' => 'Immobile inagibile o inabitabile', '3' => 'Altre riduzioni'];
    public const ESENZIONI = ['0' => 'Nessuna esenzione', '1' => 'Immobile non utilizzabile né disponibile', '2' => 'Quadro temporaneo aiuti di Stato', '3' => 'Altre esenzioni'];
    public const EQUIPARAZIONI = ['1' => 'Alloggio sociale', '2' => 'Alloggio di servizio (Forze armate e di polizia)'];

    protected int $id;
    protected DichiarazioneImu $dichiarazioneImu;
    protected Immobile $immobile;
    protected string $quadro = 'A';
    protected int $numeroOrdine = 1;
    protected int $progressivoImmobile = 1;
    protected CaratteristicaDichiarata $caratteristica = CaratteristicaDichiarata::fabbricatoRendita;
    protected float $valore = 0.0;
    protected float $percentualePossesso = 100.0;
    protected ?float $detrazione = null;
    protected ?string $equiparazione = null;
    protected string $riduzione = '0';
    protected string $esenzione = '0';
    protected bool $acquisto = false;
    protected bool $cessione = false;
    protected bool $altro = false;
    protected ?string $altroDescrizione = null;
    protected ?SismaDate $dataVariazione = null;
    protected ?string $inizioTermine = null;
    protected ?string $agenziaEntrate = null;
    protected ?string $estremiTitolo = null;
    protected ?string $nonUtilizzabileTipo = null;
    protected ?string $nonUtilizzabileAutorita = null;
    protected ?SismaDate $nonUtilizzabileData = null;
    protected ?string $motivo = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Descrizione della vicenda, per gli elenchi.
     */
    public function getVicenda(): string
    {
        $vicende = array_keys(array_filter(['Acquisto' => $this->acquisto, 'Cessione' => $this->cessione, ($this->altroDescrizione ?? 'Altro') => $this->altro]));
        if ($this->inizioTermine !== null) {
            $vicende[] = ($this->inizioTermine === 'I' ? 'Inizio' : 'Termine') . ' agevolazione';
        }
        return implode(', ', $vicende);
    }
}
