<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Entities\Studio;

/**
 * Bene ammortizzabile dell'azienda (registro dei beni ammortizzabili, art.
 * 16 D.P.R. 600/1973): conti dell'immobilizzazione, del fondo e
 * dell'ammortamento, costo, coefficiente annuo (D.M. 31 dicembre 1988),
 * riduzione alla metà nel primo esercizio e deduzione integrale dei beni
 * di costo unitario fino a 516,46 euro (art. 102, cc. 2 e 5, TUIR; dal
 * 2027 art. 111, cc. 8 e 10, D.Lgs. 117/2026), ammortamenti già effettuati
 * prima della contabilità (fondo alla fine di un esercizio) e dismissione
 * con il prezzo di cessione. Le quote si calcolano in Services\Ammortamenti.
 */
class Cespite extends BaseEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected string $codice;
    protected string $descrizione;
    protected Conto $contoCespite;
    protected Conto $contoFondo;
    protected Conto $contoAmmortamento;
    protected SismaDate $dataAcquisto;
    /** entrata in funzione, se diversa dall'acquisto: l'ammortamento parte dal suo esercizio */
    protected ?SismaDate $dataEntrataFunzione = null;
    protected float $costo = 0.0;
    /** coefficiente annuo di ammortamento (%) */
    protected float $percentuale = 0.0;
    protected bool $riduzionePrimoEsercizio = true;
    /** beni di costo unitario fino a 516,46 euro: costo dedotto per intero nel primo esercizio */
    protected bool $deduzioneIntegrale = false;
    /** coefficiente fiscale massimo, se diverso da quello civilistico (art. 102, c. 2, TUIR; dal 2027 art. 111, c. 8) */
    protected ?float $percentualeFiscale = null;
    /** parte deducibile delle quote (es. 20% delle autovetture, art. 164 TUIR; 80% della telefonia, art. 102, c. 9; dal 2027 artt. 168 e 111, c. 14) */
    protected float $percentualeDeducibile = 100.0;
    /** costo fiscalmente rilevante massimo (es. 18.075,99 euro delle autovetture) */
    protected ?float $limiteCostoFiscale = null;
    /** ammortamenti già effettuati alla fine dell'esercizio indicato (presa in carico) */
    protected float $fondoIniziale = 0.0;
    protected ?int $esercizioFondoIniziale = null;
    protected ?RegistrazioneContabile $registrazioneAcquisto = null;
    protected ?SismaDate $dataDismissione = null;
    protected ?float $prezzoDismissione = null;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    public function getDataInizioAmmortamento(): SismaDate
    {
        return $this->dataEntrataFunzione ?? $this->dataAcquisto;
    }

    public function getEtichetta(): string
    {
        return $this->codice . ' ' . $this->descrizione;
    }
}
