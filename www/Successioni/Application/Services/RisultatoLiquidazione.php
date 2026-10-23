<?php

namespace Successioni\Application\Services;

use Successioni\Application\Entities\BeneficiarioSuccessione;

/**
 * Esito della liquidazione: riepilogo dell'attivo e del passivo (quadro
 * EE), imposte del quadro EF e imposta di successione per beneficiario con
 * i passaggi di calcolo. Importi delle imposte in euro interi, come nel
 * modello.
 */
class RisultatoLiquidazione
{

    /** @var array{immobili: int, aziende: int, titoli: int, aereiNavi: int, altriBeni: int, attivo: int, passivo: int, netto: int} quadro EE */
    public array $riepilogo = ['immobili' => 0, 'aziende' => 0, 'titoli' => 0, 'aereiNavi' => 0, 'altriBeni' => 0, 'attivo' => 0, 'passivo' => 0, 'netto' => 0];

    /** Sezione I - imposta ipotecaria */
    public int $ipotecariaImponibile = 0;
    public int $ipotecariaProporzionale = 0;
    public int $agevolazioneGValore = 0;
    public int $agevolazioneGImposta = 0;
    public int $agevolazioneMValore = 0;
    public int $agevolazioneMImposta = 0;

    /** EF3bis/EF10bis: valore degli immobili conferiti in trust e imposte fisse */
    public int $trustValore = 0;
    public int $trustIpotecaria = 0;
    public int $trustCatastale = 0;

    /** Trust senza pagamento anticipato: l'imposta di successione è dovuta all'attribuzione ai beneficiari finali */
    public bool $trustDifferito = false;
    public int $primaCasaNumero = 0;
    public int $primaCasaValore = 0;
    public int $primaCasaImposta = 0;
    public int $ipotecariaDovuta = 0;
    public int $ipotecariaVersata = 0;
    public int $ipotecariaCredito = 0;
    public int $ipotecariaDaVersare = 0;

    /** Sezione II - imposta catastale */
    public int $catastaleImponibile = 0;
    public int $catastaleProporzionale = 0;
    public int $catastaleFissa = 0;
    public int $catastaleDovuta = 0;
    public int $catastaleVersata = 0;
    public int $catastaleCredito = 0;
    public int $catastaleDaVersare = 0;

    /** Sezioni III, IV, V - tassa ipotecaria, bollo, tributi speciali */
    public int $circoscrizioniTassa = 0;
    public int $tassaIpotecaria = 0;
    public int $bolloCopiaConforme = 0;
    public int $circoscrizioniBollo = 0;
    public int $bollo = 0;
    public float $tributiSpeciali = 0.0;

    /** Sezione V-bis - imposta di successione */
    public bool $autoliquidazione = false;
    public int $successioneImposta = 0;
    public int $successioneVersata = 0;
    public int $successioneCredito = 0;
    public int $successioneDaVersare = 0;

    /** Totale da versare con la dichiarazione (EF19, addebito sul conto) */
    public float $totaleDaVersare = 0.0;

    /** Ravvedimento (sezione VI): giorni di ritardo, sanzioni e interessi per voce (ipotecaria, catastale, tassaIpotecaria, bollo, successione) */
    public int $giorniRitardo = 0;

    /** @var array<string, float> */
    public array $sanzioni = [];

    /** @var array<string, float> */
    public array $interessi = [];

    /** Sanzione per la tardiva presentazione da versare con F24 (codice 1549) */
    public float $sanzionePresentazione = 0.0;

    /** Sanzione in misura fissa (nessuna imposta di successione dovuta), a carico di chi presenta */
    public bool $sanzionePresentazioneFissa = false;

    /** @var array<int, QuotaImposta> passaggi di calcolo per id del beneficiario */
    public array $quote = [];

    /** @var string[] */
    public array $avvisi = [];

    public function avviso(string $testo): void
    {
        $this->avvisi[] = $testo;
    }

    public function hasQuadroEF(): bool
    {
        return ($this->ipotecariaDovuta + $this->catastaleDovuta + $this->tassaIpotecaria + $this->bollo + $this->successioneImposta > 0) || ($this->tributiSpeciali > 0) || $this->autoliquidazione;
    }

    public function getTotaleSanzioni(): float
    {
        return round(array_sum($this->sanzioni), 2);
    }

    public function getTotaleInteressi(): float
    {
        return round(array_sum($this->interessi), 2);
    }

    /**
     * Importo addebitato con la dichiarazione: EF19 più sanzioni e interessi
     * della sezione VI.
     */
    public function getImportoDaVersare(): float
    {
        return round($this->totaleDaVersare + $this->getTotaleSanzioni() + $this->getTotaleInteressi(), 2);
    }

    public function getQuota(BeneficiarioSuccessione $beneficiario): ?QuotaImposta
    {
        return $this->quote[$beneficiario->id] ?? null;
    }
}
