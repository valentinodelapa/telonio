<?php

namespace Deleghe\Application\Entities;

use Deleghe\Application\Enumerations\CircostanzaRavvedimento;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Ravvedimento operoso di un versamento omesso o tardivo, regolarizzato con
 * la delega (data di versamento = scadenza della delega). Sanzione ridotta e
 * interessi sono calcolati da Services\CalcoloRavvedimento; le righe
 * (tributo, sanzione, interessi) sono generate nella delega con origine
 * "Ravvedimento". Senza codice di sanzione o di interessi l'importo si
 * somma al tributo. Se il versamento omesso è nell'archivio del
 * contribuente è collegato (versamentoOmesso) e non risulta più da versare.
 */
class Ravvedimento extends BaseEntity
{

    public const ORIGINE = 'Ravvedimento';

    protected int $id;
    protected Delega $delega;
    protected ?Versamento $versamentoOmesso = null;
    protected SezioneF24 $sezione;
    protected CodiceTributo $codiceTributo;
    protected ?string $codiceEnte = null;
    protected ?string $rateazione = null;
    protected ?int $annoRiferimento = null;
    protected bool $acconto = false;
    protected bool $saldo = false;
    protected ?int $numeroImmobili = null;
    protected float $importoTributo;
    protected SismaDate $scadenzaOriginale;
    protected ?SismaDate $termineDichiarazione = null;
    protected ?SismaDate $termineDichiarazioneSuccessiva = null;
    protected ?CodiceTributo $codiceSanzione = null;
    protected ?CodiceTributo $codiceInteressi = null;
    protected CircostanzaRavvedimento $circostanza = CircostanzaRavvedimento::nessuna;
    protected int $giorniRitardo = 0;
    protected float $percentualeSanzione = 0.0;
    protected int $riduzione = 10;
    protected float $sanzione = 0.0;
    protected float $interessi = 0.0;
    protected string $dettaglioInteressi = '[]';

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getRiferimentoOrigine(): string
    {
        return 'ravvedimento-' . $this->id;
    }

    public function getTotale(): float
    {
        return round($this->importoTributo + $this->sanzione + $this->interessi, 2);
    }

    /**
     * @return array<array{dal: string, al: string, giorni: int, tasso: float, interessi: float}>
     */
    public function getPeriodiInteressi(): array
    {
        return json_decode($this->dettaglioInteressi, true) ?? [];
    }
}
