<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Services\Redditi\CalcoloIrpef;
use Dichiarativi\Application\Services\Redditi\CalcoloOneri;
use Dichiarativi\Application\Services\Redditi\CoefficientiForfetari;
use PHPUnit\Framework\TestCase;

/**
 * IRPEF 2025 secondo le specifiche tecniche del modello Redditi PF 2026
 * (par. 19-21, 28 e 29), con valori calcolati a mano dalle formule.
 */
class CalcoloIrpefTest extends TestCase
{

    public function testImpostaLorda(): void
    {
        $this->assertSame(6440.0, CalcoloIrpef::impostaLorda(28000));
        $this->assertSame(7140.0, CalcoloIrpef::impostaLorda(30000));
        $this->assertSame(14140.0 + 4300.0, CalcoloIrpef::impostaLorda(60000));
        $this->assertSame(0.0, CalcoloIrpef::impostaLorda(-100));
    }

    public function testDetrazioniFamiliari(): void
    {
        // quoziente con quattro cifre decimali: 10.000 / 15.000 = 0,6666
        $this->assertEqualsWithDelta(800 - 110 * 0.6666, CalcoloIrpef::detrazioneConiuge(10000, 12), 0.001);
        // maggiorazione di 20 euro tra 29.200 e 34.700, non rapportata ai mesi
        $this->assertEqualsWithDelta(690 * 6 / 12 + 20, CalcoloIrpef::detrazioneConiuge(30000, 6), 0.001);
        $this->assertEqualsWithDelta(345.0, CalcoloIrpef::detrazioneConiuge(60000, 12), 0.001);
        $this->assertSame(0.0, CalcoloIrpef::detrazioneConiuge(80001, 12));
        // un figlio con 50.000 euro: 950 x (45.000 / 95.000 = 0,4736)
        $this->assertEqualsWithDelta(950 * 0.4736, CalcoloIrpef::detrazioneFigli(50000, [['mesi' => 12, 'percentuale' => 100.0]], 1), 0.001);
        // due figli al 50%: base 110.000
        $this->assertEqualsWithDelta(950 * 0.5 * 2 * 0.5454, CalcoloIrpef::detrazioneFigli(50000, [['mesi' => 12, 'percentuale' => 50.0], ['mesi' => 12, 'percentuale' => 50.0]], 2), 0.001);
        $this->assertEqualsWithDelta(750 * 0.625, CalcoloIrpef::detrazioneAltriFamiliari(30000, [['mesi' => 12, 'percentuale' => 100.0]]), 0.001);
    }

    public function testDetrazioniLavoroEPensione(): void
    {
        $this->assertEqualsWithDelta(1955.0, CalcoloIrpef::detrazioneLavoroDipendente(10000, 365, false)['detrazione'], 0.001);
        // minimo di 690 euro (1.380 a tempo determinato) non rapportato
        $this->assertSame(690.0, CalcoloIrpef::detrazioneLavoroDipendente(10000, 100, false)['detrazione']);
        $this->assertSame(1380.0, CalcoloIrpef::detrazioneLavoroDipendente(10000, 100, true)['detrazione']);
        $this->assertEqualsWithDelta(1910 + 1190 * 0.6153, CalcoloIrpef::detrazioneLavoroDipendente(20000, 365, false)['detrazione'], 0.001);
        // con 30.000 euro: 1.910 x 0,9090 + 65
        $this->assertEqualsWithDelta(1910 * 0.9090 + 65, CalcoloIrpef::detrazioneLavoroDipendente(30000, 365, false)['detrazione'], 0.001);
        $this->assertEqualsWithDelta(700 + 1255 * 0.4102, CalcoloIrpef::detrazionePensione(20000, 365)['detrazione'], 0.001);
        $this->assertSame(713.0, CalcoloIrpef::detrazionePensione(5000, 100)['detrazione']);
        $this->assertEqualsWithDelta(500 + 765 * 0.3555, CalcoloIrpef::detrazioneAltriRedditi(20000), 0.001);
        $this->assertSame(1265.0, CalcoloIrpef::detrazioneAltriRedditi(5000));

        // lavoro e pensione con la minima: la più favorevole tra la minima e la somma delle rapportate
        $colonne = CalcoloIrpef::detrazioniLavoro(8000, 60, false, 60, false, true, true);
        $this->assertEqualsWithDelta(713.0, $colonne[2], 0.001);
        $this->assertSame(0.0, $colonne[1]);
        // altri redditi più favorevoli del lavoro dipendente rapportato
        $colonne = CalcoloIrpef::detrazioniLavoro(20000, 30, false, 0, true, true, false);
        $this->assertSame([1 => 0.0, 2 => 0.0, 4 => CalcoloIrpef::detrazioneAltriRedditi(20000)], $colonne);
    }

    public function testCuneoFiscaleETrattamentoIntegrativo(): void
    {
        $this->assertEqualsWithDelta(864.0, CalcoloIrpef::sommaEsente(18000, 18000, 365), 0.001);
        $this->assertEqualsWithDelta(530.0, CalcoloIrpef::sommaEsente(10000, 10000, 365), 0.001);
        // reddito rapportato all'anno per la percentuale: 6.000 in 182 giorni = 12.033 annui, 5,3%
        $this->assertEqualsWithDelta(318.0, CalcoloIrpef::sommaEsente(6000, 6000, 182), 0.001);
        $this->assertSame(0.0, CalcoloIrpef::sommaEsente(20001, 20001, 365));
        $this->assertEqualsWithDelta(1000.0, CalcoloIrpef::ulterioreDetrazione(25000, 365), 0.001);
        $this->assertEqualsWithDelta(500.0, CalcoloIrpef::ulterioreDetrazione(36000, 365), 0.001);
        $this->assertSame(0.0, CalcoloIrpef::ulterioreDetrazione(40001, 365));
        // fino a 15.000 con capienza: 1.200 per il periodo di lavoro
        $this->assertEqualsWithDelta(1200.0, CalcoloIrpef::trattamentoIntegrativo(12000, 12000, 365, false, 0, 0), 0.001);
        // incapiente (imposta 1.840 sotto le detrazioni ridotte): non spetta
        $this->assertSame(0.0, CalcoloIrpef::trattamentoIntegrativo(8000, 8000, 365, false, 0, 0));
        // tra 15.000 e 28.000: differenza tra detrazioni e imposta lorda, fino a 1.200
        $this->assertEqualsWithDelta(500.0, CalcoloIrpef::trattamentoIntegrativo(20000, 20000, 365, false, 5100, 4600), 0.001);
        $this->assertSame(0.0, CalcoloIrpef::trattamentoIntegrativo(20000, 20000, 365, false, 2642, 4600));
    }

    public function testCoefficientiForfetari(): void
    {
        // tabella delle istruzioni del quadro LM (codici ATECO 2007)
        $this->assertSame(78, CoefficientiForfetari::getCoefficiente('74.10.21'));
        $this->assertSame(78, CoefficientiForfetari::getCoefficiente('69.20.11'));
        $this->assertSame(40, CoefficientiForfetari::getCoefficiente('47.11.02'));
        $this->assertSame(54, CoefficientiForfetari::getCoefficiente('47.82.01'));
        $this->assertSame(40, CoefficientiForfetari::getCoefficiente('47.81.01'));
        $this->assertSame(62, CoefficientiForfetari::getCoefficiente('46.18.99'));
        $this->assertSame(86, CoefficientiForfetari::getCoefficiente('43.21.01'));
        $this->assertSame(67, CoefficientiForfetari::getCoefficiente('62.01.00'));

    }

    public function testRiordinoDelleDetrazioni(): void
    {
        // art. 16-ter Tuir (specifiche, par. 10.3): importo base per coefficiente dei figli
        $this->assertSame(7000, CalcoloOneri::limiteRiordino(90000, 0, false));
        $this->assertSame(9800, CalcoloOneri::limiteRiordino(90000, 1, false));
        $this->assertSame(6800, CalcoloOneri::limiteRiordino(150000, 2, false));
        $this->assertSame(8000, CalcoloOneri::limiteRiordino(150000, 1, true));
        $this->assertSame(14000, CalcoloOneri::limiteRiordino(100000, 3, false));
        // oneri commisurati al reddito (art. 15, comma 3-bis)
        $this->assertSame(1.0, CalcoloOneri::coefficienteAbbattimento(120000));
        $this->assertSame(0.5, CalcoloOneri::coefficienteAbbattimento(180000));
        $this->assertSame(0.0, CalcoloOneri::coefficienteAbbattimento(240000));
    }
}
