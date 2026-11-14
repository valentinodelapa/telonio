<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\Cespite;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\RateoRisconto;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Services\Ammortamenti;
use Contabilita\Application\Services\ChiusuraEsercizio;
use Contabilita\Application\Services\RateiRisconti;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Fine dell'esercizio: piano di ammortamento dei cespiti, quote di ratei e
 * risconti, saldi e risultato per la chiusura dei conti.
 */
class FineEsercizioTest extends ContabilitaTestCase
{

    /**
     * @return EsercizioContabile[]
     */
    private function esercizi(int $dal, int $al): array
    {
        $esercizi = [];
        for ($anno = $dal; $anno <= $al; $anno++) {
            $esercizio = new EsercizioContabile($this->dataMapper);
            $esercizio->id = $anno;
            $esercizio->soggetto = $this->azienda;
            $esercizio->anno = $anno;
            $esercizio->dataInizio = new SismaDate($anno . '-01-01');
            $esercizio->dataFine = new SismaDate($anno . '-12-31');
            $esercizi[] = $esercizio;
        }
        return $esercizi;
    }

    private function cespite(float $costo, float $percentuale, string $acquisto): Cespite
    {
        $cespite = new Cespite($this->dataMapper);
        $cespite->codice = 'C1';
        $cespite->descrizione = 'Bene';
        $cespite->costo = $costo;
        $cespite->percentuale = $percentuale;
        $cespite->dataAcquisto = new SismaDate($acquisto);
        $cespite->riduzionePrimoEsercizio = true;
        return $cespite;
    }

    public function testPianoDiAmmortamento(): void
    {
        $esercizi = $this->esercizi(2024, 2030);
        $piano = Ammortamenti::piano($this->cespite(2400.0, 20.0, '2025-03-10'), $esercizi);
        $this->assertSame([2025, 2026, 2027, 2028, 2029, 2030], array_keys($piano), 'dall\'esercizio dell\'acquisto');
        $this->assertSame([240.0, 480.0, 480.0, 480.0, 480.0, 240.0], array_column($piano, 'quota'), 'metà il primo esercizio, il resto fino al costo');
        $this->assertSame(0.0, $piano[2030]['residuo']);
        $this->assertTrue($piano[2025]['primo']);

        // bene fino a 516,46 euro dedotto per intero
        $minore = $this->cespite(450.0, 20.0, '2026-05-02');
        $minore->deduzioneIntegrale = true;
        $pianoMinore = Ammortamenti::piano($minore, $esercizi);
        $this->assertSame(450.0, $pianoMinore[2026]['quota']);
        $this->assertSame(0.0, $pianoMinore[2027]['quota']);

        // bene già in uso: fondo alla fine del 2025, nessun ricalcolo fino a quell'esercizio
        $usato = $this->cespite(10000.0, 12.0, '2019-06-01');
        $usato->fondoIniziale = 7800.0;
        $usato->esercizioFondoIniziale = 2025;
        $pianoUsato = Ammortamenti::piano($usato, $esercizi);
        $this->assertSame([2026, 2027, 2028, 2029, 2030], array_keys($pianoUsato), 'resta nel registro anche ammortizzato per intero');
        $this->assertSame([1200.0, 1000.0, 0.0, 0.0, 0.0], array_column($pianoUsato, 'quota'));

        // dismissione: nessuna quota nell'esercizio, plusvalenza sul residuo
        $venduto = $this->cespite(2400.0, 20.0, '2025-03-10');
        $venduto->dataDismissione = new SismaDate('2027-09-30');
        $venduto->prezzoDismissione = 1800.0;
        $pianoVenduto = Ammortamenti::piano($venduto, $esercizi);
        $this->assertSame([2025, 2026, 2027], array_keys($pianoVenduto));
        $this->assertSame(0.0, $pianoVenduto[2027]['quota']);
        $this->assertTrue($pianoVenduto[2027]['dismesso']);
        $this->assertSame(120.0, $pianoVenduto[2027]['plusvalenza'], '1.800 - (2.400 - 720)');
    }

    public function testParteFiscaleDegliAmmortamenti(): void
    {
        $esercizi = $this->esercizi(2025, 2030);
        // autovettura: 20% deducibile sul costo fino a 18.075,99 euro
        $auto = $this->cespite(28300.0, 25.0, '2025-09-30');
        $auto->percentualeDeducibile = Ammortamenti::DEDUCIBILE_AUTOVETTURE;
        $auto->limiteCostoFiscale = Ammortamenti::LIMITE_AUTOVETTURE;
        $piano = Ammortamenti::piano($auto, $esercizi);
        $this->assertSame(3537.5, $piano[2025]['quota']);
        $this->assertSame(round(3537.5 * 18075.99 / 28300 * 0.2, 2), $piano[2025]['deducibile']);
        $this->assertSame(0.0, $piano[2025]['variazioneCoefficiente']);
        $this->assertSame(round(3537.5 - $piano[2025]['deducibile'], 2), $piano[2025]['variazioneLimiti']);

        // coefficiente civilistico del 25% oltre il fiscale del 20%: eccedenza ripresa e poi recuperata
        $macchina = $this->cespite(10000.0, 25.0, '2025-01-10');
        $macchina->riduzionePrimoEsercizio = false;
        $macchina->percentualeFiscale = 20.0;
        $piano = Ammortamenti::piano($macchina, $esercizi);
        $this->assertSame([2500.0, 2500.0, 2500.0, 2500.0, 0.0, 0.0], array_column($piano, 'quota'));
        $this->assertSame([2000.0, 2000.0, 2000.0, 2000.0, 2000.0, 0.0], array_column($piano, 'deducibile'));
        $this->assertSame([500.0, 500.0, 500.0, 500.0, -2000.0, 0.0], array_column($piano, 'variazioneCoefficiente'), 'recupero quando il civilistico è finito');
    }

    public function testRiservaLegale(): void
    {
        $this->assertSame(500.0, \Contabilita\Application\Services\DestinazioneRisultato::getRiservaLegale(10000.0, 0.0, 0.0), 'senza capitale in contabilità: il 5%');
        $this->assertSame(500.0, \Contabilita\Application\Services\DestinazioneRisultato::getRiservaLegale(10000.0, 50000.0, 0.0));
        $this->assertSame(200.0, \Contabilita\Application\Services\DestinazioneRisultato::getRiservaLegale(10000.0, 50000.0, 9800.0), 'fino a un quinto del capitale');
        $this->assertSame(0.0, \Contabilita\Application\Services\DestinazioneRisultato::getRiservaLegale(10000.0, 50000.0, 10000.0));
    }

    public function testRateiERisconti(): void
    {
        $esercizio = $this->esercizi(2026, 2026)[0];
        $voce = new RateoRisconto($this->dataMapper);
        $voce->conto = $this->conti['22.01.0001'];
        $voce->descrizione = 'Polizza';
        $voce->importo = 1200.0;
        $voce->competenzaDal = new SismaDate('2026-07-01');
        $voce->competenzaAl = new SismaDate('2027-06-30');
        $voce->registratoNellEsercizio = true;
        // risconto attivo: 181 giorni del 2027 su 365
        $this->assertSame(round(1200 * 181 / 365, 2), RateiRisconti::quota($voce, $esercizio));
        $this->assertSame(FunzioneConto::riscontiAttivi, RateiRisconti::getFunzione($voce));
        // rateo passivo: 184 giorni del 2026 su 365, il costo si registra nel 2027
        $voce->registratoNellEsercizio = false;
        $this->assertSame(round(1200 * 184 / 365, 2), RateiRisconti::quota($voce, $esercizio));
        $this->assertSame(FunzioneConto::rateiPassivi, RateiRisconti::getFunzione($voce));
        // ricavo: rateo attivo e risconto passivo
        $voce->conto = $this->conti['RIC'];
        $this->assertSame(FunzioneConto::rateiAttivi, RateiRisconti::getFunzione($voce));
        $voce->registratoNellEsercizio = true;
        $this->assertSame(FunzioneConto::riscontiPassivi, RateiRisconti::getFunzione($voce));
        // tutto nell'esercizio: nessun risconto
        $voce->competenzaAl = new SismaDate('2026-12-31');
        $this->assertSame(0.0, RateiRisconti::quota($voce, $esercizio));
    }

    public function testSaldiERisultatoSenzaLaChiusura(): void
    {
        $esercizio = $this->esercizi(2026, 2026)[0];
        $riga = function (string $conto, float $dare, float $avere, ?string $origine = null): RigaContabile {
            $registrazione = $this->creaRegistrazione(CausaleContabile::giroconto, null, '2026-06-30');
            $registrazione->origine = $origine;
            $riga = new RigaContabile($this->dataMapper);
            $riga->registrazioneContabile = $registrazione;
            $riga->conto = $this->conti[$conto];
            $riga->dare = $dare;
            $riga->avere = $avere;
            return $riga;
        };
        $this->rispondiConti(['riga_contabile' => [
                $riga('RIC', 0.0, 10000.0), $riga('BAN', 10000.0, 0.0),
                $riga('COS', 3000.0, 0.0), $riga('BAN', 0.0, 3000.0),
                // epilogo già registrato: escluso
                $riga('RIC', 10000.0, 0.0, ChiusuraEsercizio::ORIGINE), $riga('COS', 0.0, 3000.0, ChiusuraEsercizio::ORIGINE)]]);
        $saldi = (new ChiusuraEsercizio($this->dataMapper))->getSaldi($this->azienda, $esercizio);
        $this->assertCount(3, $saldi);
        $this->assertSame(7000.0, ChiusuraEsercizio::getRisultato($saldi), 'utile: ricavi meno costi');
        $this->assertTrue(ChiusuraEsercizio::isChiusuraORiapertura((function () {
                            $registrazione = $this->creaRegistrazione(CausaleContabile::giroconto);
                            $registrazione->origine = ChiusuraEsercizio::ORIGINE_RIAPERTURA;
                            return $registrazione;
                        })()));
    }
}
