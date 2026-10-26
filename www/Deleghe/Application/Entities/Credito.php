<?php

namespace Deleghe\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Telonio\Application\Entities\Studio;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;

/**
 * Credito del contribuente da utilizzare in compensazione nelle deleghe (es.
 * credito IVA annuale, eccedenza IRPEF o IRES, credito INPS). I campi sono
 * quelli del rigo a credito del modello, con lo stesso significato che hanno
 * in Versamento; gli utilizzi sono i versamenti collegati al credito e il
 * residuo è calcolato da CreditoModel::getResiduo.
 *
 * utilizzabileDal è la prima data di versamento in cui il credito si può
 * compensare (es. per il credito IVA annuale oltre 5.000 euro il decimo
 * giorno successivo alla presentazione della dichiarazione, art. 17 D.Lgs.
 * 241/1997).
 */
class Credito extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected SezioneF24 $sezione;
    protected ?CodiceTributo $codiceTributo = null;
    protected ?string $codiceEnte = null;
    protected ?string $codiceSede = null;
    protected ?string $causale = null;
    protected ?string $posizione = null;
    protected ?string $cifraControllo = null;
    protected ?string $numeroRiferimento = null;
    protected ?string $rateazione = null;
    protected ?int $annoRiferimento = null;
    protected ?string $periodoDa = null;
    protected ?string $periodoA = null;
    protected float $importo = 0.0;
    protected ?SismaDate $utilizzabileDal = null;
    protected ?string $descrizione = null;
    protected ?string $origine = null;
    protected ?string $riferimentoOrigine = null;
    protected ?string $note = null;
    protected SismaDateTime $dataInserimento;

    /**
     * Campi copiati nel rigo a credito della delega.
     */
    public const CAMPI_RIGO = ['codiceTributo', 'codiceEnte', 'codiceSede', 'causale', 'posizione', 'cifraControllo', 'numeroRiferimento', 'rateazione', 'annoRiferimento', 'periodoDa', 'periodoA'];

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->dataInserimento = new SismaDateTime();
    }

    /**
     * Codice del rigo come appare nel modello: codice tributo o causale.
     */
    public function getCodice(): string
    {
        return $this->codiceTributo?->codice ?? $this->causale ?? '';
    }

    public function getEtichetta(): string
    {
        return trim($this->sezione->getLabel() . ' ' . $this->getCodice() . ' ' . ($this->annoRiferimento ?? '') . ($this->descrizione !== null ? ' - ' . $this->descrizione : ''));
    }

    public function isUtilizzabile(\DateTimeInterface $dataVersamento): bool
    {
        return ($this->utilizzabileDal === null) || ($this->utilizzabileDal->format('Y-m-d') <= $dataVersamento->format('Y-m-d'));
    }
}
