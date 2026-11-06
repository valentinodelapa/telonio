<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Entities\Studio;

/**
 * Avviso di accertamento o di rettifica notificato al contribuente, per le
 * situazioni di rischio dei rimborsi IVA oltre 30.000 euro (art. 38-bis, c.
 * 4, lett. b), D.P.R. 633/1972): la garanzia è dovuta se, per ciascun anno,
 * la differenza tra l'accertato e il dichiarato supera il 10% del dichiarato
 * fino a 150.000 euro, il 5% fino a 1.500.000 euro, oltre l'1% o comunque
 * 150.000 euro.
 */
class AttoAccertamento extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $annoImposta;
    protected SismaDate $dataNotifica;
    protected ?string $estremi = null;
    protected float $importoDichiarato = 0.0;
    protected float $importoAccertato = 0.0;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getDifferenza(): float
    {
        return round(abs($this->importoAccertato - $this->importoDichiarato), 2);
    }

    /**
     * Differenza oltre cui il contribuente è in situazione di rischio.
     */
    public function getSoglia(): float
    {
        return round(match (true) {
            $this->importoDichiarato <= 150000 => $this->importoDichiarato * 0.10,
            $this->importoDichiarato <= 1500000 => $this->importoDichiarato * 0.05,
            default => min($this->importoDichiarato * 0.01, 150000),
        }, 2);
    }

    public function isOltreSoglia(): bool
    {
        return $this->getDifferenza() > $this->getSoglia();
    }

    /**
     * Notificato nei due anni precedenti la richiesta di rimborso.
     */
    public function isNelBiennio(SismaDate $richiesta): bool
    {
        $inizio = (new \DateTimeImmutable($richiesta->format('Y-m-d')))->modify('-2 years')->format('Y-m-d');
        return ($this->dataNotifica->format('Y-m-d') >= $inizio) && ($this->dataNotifica->format('Y-m-d') <= $richiesta->format('Y-m-d'));
    }
}
