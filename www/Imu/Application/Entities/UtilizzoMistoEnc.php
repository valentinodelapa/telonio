<?php

namespace Imu\Application\Entities;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\Enumerations\AttivitaEnc;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Immobile di un ente non commerciale destinato alle attività del comma
 * 759, lett. g), per l'anno (quadro B della dichiarazione IMU ENC, D.M. 19
 * novembre 2012, n. 200):
 * - esente: utilizzato interamente con modalità non commerciali;
 * - utilizzo misto: la percentuale di imponibilità è la somma dei rapporti
 *   di superficie, di soggetti e di giorni destinati alle attività svolte
 *   con modalità commerciali (art. 5 del regolamento); per l'attività
 *   didattica, se il corrispettivo medio (Cm) non è inferiore al costo
 *   medio per studente (Cms), è imponibile anche la parte esente nella
 *   misura 1 - Cms/Cm (art. 4, c. 3, lett. c).
 *
 * Rapporti in percentuale, corrispettivi in euro.
 */
class UtilizzoMistoEnc extends BaseEntity
{

    protected int $id;
    protected Soggetto $soggetto;
    protected Immobile $immobile;
    protected int $anno;
    protected string $attivita = '';
    protected bool $esente = false;
    protected ?string $tipoConcessione = null;
    protected ?string $comodatario = null;
    protected ?float $didatticaCorrispettivoMedio = null;
    protected ?float $didatticaCostoMedioStudente = null;
    protected ?float $didatticaSuperficie = null;
    protected ?float $didatticaSuperficieGiorni = null;
    protected ?float $didatticaSoggetti = null;
    protected ?float $didatticaSoggettiGiorni = null;
    protected ?float $didatticaGiorni = null;
    protected ?float $altreCorrispettivoMedio = null;
    protected ?float $altreCorrispettivoMercato = null;
    protected ?float $altreSuperficie = null;
    protected ?float $altreSuperficieGiorni = null;
    protected ?float $altreSoggetti = null;
    protected ?float $altreSoggettiGiorni = null;
    protected ?float $altreGiorni = null;
    protected ?string $note = null;

    public const RAPPORTI = ['Superficie', 'SuperficieGiorni', 'Soggetti', 'SoggettiGiorni', 'Giorni'];
    private const PERCENTUALE_MASSIMA = 99.99;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * @return AttivitaEnc[]
     */
    public function getAttivita(): array
    {
        return AttivitaEnc::daCodici($this->attivita);
    }

    public function hasAttivita(AttivitaEnc $attivita): bool
    {
        return in_array($attivita, $this->getAttivita(), true);
    }

    public function isDidattica(): bool
    {
        return $this->hasAttivita(AttivitaEnc::didattiche);
    }

    public function hasAltreAttivita(): bool
    {
        return count(array_filter($this->getAttivita(), fn(AttivitaEnc $attivita) => $attivita !== AttivitaEnc::didattiche)) > 0;
    }

    /**
     * Percentuale di imponibilità di una sezione ("didattica" o "altre"):
     * somma dei rapporti, al massimo 99,99 (oltre l'immobile è interamente
     * imponibile e va nel quadro A).
     */
    public function getPercentualeImponibilita(string $sezione): float
    {
        if ($this->esente) {
            return 0.0;
        }
        $somma = 0.0;
        foreach (self::RAPPORTI as $rapporto) {
            $somma += (float) $this->{$sezione . $rapporto};
        }
        return round(min($somma, self::PERCENTUALE_MASSIMA), 2);
    }

    public function isInteramenteImponibile(): bool
    {
        $somma = 0.0;
        foreach (['didattica', 'altre'] as $sezione) {
            foreach (self::RAPPORTI as $rapporto) {
                $somma += (float) $this->{$sezione . $rapporto};
            }
        }
        return ($this->esente === false) && ($somma > self::PERCENTUALE_MASSIMA);
    }

    /**
     * Didattica: rapporto Cms/Cm in percentuale se Cm >= Cms, altrimenti
     * null (attività non commerciale).
     */
    public function getRapportoCmsCm(): ?float
    {
        $cm = (float) $this->didatticaCorrispettivoMedio;
        $cms = (float) $this->didatticaCostoMedioStudente;
        return ($cm > 0) && ($cm >= $cms) ? round($cms / $cm * 100, 2) : null;
    }

    /**
     * Valore da assoggettare a IMU dato il valore dell'immobile (campi 60,
     * 66, 67 e 82 del quadro B).
     *
     * @return array{imponibile: float, esente: float, parziale: float}
     */
    public function getValori(float $valore): array
    {
        if ($this->esente) {
            return ['imponibile' => 0.0, 'esente' => $valore, 'parziale' => 0.0];
        }
        $sezione = $this->isDidattica() ? 'didattica' : 'altre';
        $imponibile = round($valore * $this->getPercentualeImponibilita($sezione) / 100, 2);
        $esente = round($valore - $imponibile, 2);
        $parziale = 0.0;
        if ($this->isDidattica() && (($rapporto = $this->getRapportoCmsCm()) !== null)) {
            $parziale = round($esente * (1 - $rapporto / 100), 2);
        }
        return ['imponibile' => $imponibile, 'esente' => $esente, 'parziale' => $parziale];
    }

    /**
     * Coefficiente da applicare alla base imponibile nel calcolo.
     */
    public function getCoefficienteImponibile(): float
    {
        $valori = $this->getValori(100.0);
        return round(($valori['imponibile'] + $valori['parziale']) / 100, 6);
    }
}
