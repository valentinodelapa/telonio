<?php

namespace Anagrafica\Application\Entities;

use Anagrafica\Application\Enumerations\TipoDetrazioneEdilizia;
use Anagrafica\Application\Services\TabellaDetrazioniEdilizie;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Intervento sull'immobile per il quale il soggetto fruisce di una
 * detrazione in rate annuali (quadro RP del modello Redditi PF, sezioni
 * III A, III C e IV): i dati sono quelli del rigo del quadro nell'anno
 * della spesa (anno, tipologia, codice fiscale del condominio, caselle
 * "Interventi particolari", "Acquisto, eredità o donazione",
 * "Maggiorazione sisma", "Percentuale" e "Opzione 2023") con la spesa
 * detraibile del soggetto, la percentuale di detrazione e il numero delle
 * rate. Ogni anno la dichiarazione propone il rigo con il numero della rata
 * e il suo importo fino all'ultima.
 *
 * Gli interventi sulle parti comuni condominiali (casella "Condominio" dei
 * righi RP51 e RP52) si registrano sull'unità del condòmino.
 */
class InterventoEdilizio extends BaseEntity
{

    protected int $id;
    protected Immobile $immobile;
    protected Soggetto $soggetto;
    protected TipoDetrazioneEdilizia $tipo;
    protected int $anno;
    protected ?int $tipologia = null;
    protected ?string $codiceFiscaleCondominio = null;
    protected ?int $casiParticolari = null;
    protected bool $acquistoEreditaDonazione = false;
    protected bool $maggiorazioneSisma = false;
    protected ?int $codicePercentuale = null;
    protected bool $opzione2023 = false;
    protected bool $condominio = false;
    protected float $spesa = 0.0;
    protected float $percentualeDetrazione = 50.0;
    protected int $rateazione = 10;
    protected ?string $descrizione = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {
        $this->anno = (int) date('Y') - 1;
    }

    /**
     * Numero della rata nell'anno d'imposta, null se le rate sono finite.
     */
    public function getNumeroRata(int $annoImposta): ?int
    {
        return TabellaDetrazioniEdilizie::numeroRata($this->tipo, $this->anno, $this->tipologia, $this->codicePercentuale, $this->rateazione, $annoImposta);
    }

    /**
     * Importo della rata: spesa diviso il numero delle rate, all'unità di
     * euro (specifiche, par. 13.1.4, 14.3 e 15.11).
     */
    public function getImportoRata(): int
    {
        return (int) round($this->spesa / $this->rateazione);
    }

    public function getDescrizioneBreve(): string
    {
        return $this->tipo->getEtichettaBreve() . ' ' . $this->anno . ($this->tipologia !== null ? ' (codice ' . $this->tipologia . ')' : '')
                . ' al ' . rtrim(rtrim(number_format($this->percentualeDetrazione, 2, ',', '.'), '0'), ',') . '%';
    }
}
