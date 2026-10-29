<?php

namespace Deleghe\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Pagamento rateale del saldo e del primo acconto (art. 20, c. 1, D.Lgs.
 * 241/1997) o del secondo acconto differito da una norma straordinaria
 * (RateazioneSecondoAcconto): i versamenti originari dell'archivio, collegati al piano, non
 * sono più da versare; le rate (quote e interessi) sono versamenti generati
 * con origine "Rateazione" e riferimento "rateazione-<id>"
 * (Services\RateazioneService).
 *
 * dateRate è l'elenco JSON delle date delle rate (Y-m-d): la prima è la
 * scadenza dei versamenti originari (per il secondo acconto il termine
 * differito dalla norma), le successive di norma il 16 di ogni mese.
 */
class PianoRateazione extends ReferencedEntity
{

    public const ORIGINE = 'Rateazione';

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected SismaDate $scadenza;
    protected int $numeroRate = 2;
    protected string $dateRate = '[]';
    protected ?string $descrizione = null;
    protected SismaDateTime $dataInserimento;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInserimento = new SismaDateTime();
    }

    public function getRiferimentoOrigine(): string
    {
        return 'rateazione-' . $this->id;
    }

    /**
     * @return SismaDate[]
     */
    public function getDate(): array
    {
        return array_map(fn(string $data) => new SismaDate($data), json_decode($this->dateRate, true) ?? []);
    }

    /**
     * @param \DateTimeInterface[] $date
     */
    public function setDate(array $date): void
    {
        $this->dateRate = json_encode(array_map(fn(\DateTimeInterface $data) => $data->format('Y-m-d'), array_values($date)));
        $this->numeroRate = count($date);
    }
}
