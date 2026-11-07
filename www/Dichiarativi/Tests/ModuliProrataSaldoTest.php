<?php

namespace Dichiarativi\Tests;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaIva;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use Dichiarativi\Application\Enumerations\ModalitaSaldo;
use Dichiarativi\Application\Enumerations\TipoModulo;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\RegioniVt;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Pro-rata di detrazione, più moduli, tipologia dell'inversione contabile
 * nel quadro VJ, ripartizione regionale del VT e saldo differito.
 */
class ModuliProrataSaldoTest extends DichiarativiTestCase
{

    public function testPercentualeDiDetrazione(): void
    {
        $quadri = $this->creaQuadri();
        $quadri->imposta('VE050001', 138000);
        $quadri->imposta('VE033001', 120000);
        $this->assertSame(13, PropostaIva::calcolaPercentuale($quadri));
        // esenti occasionali (nn. 1-9) fuori dal calcolo: 18.000 / 118.000
        $quadri->imposta('VF034002', 20000);
        $this->assertSame(15, PropostaIva::calcolaPercentuale($quadri));
        // arrotondamento sulle prime tre cifre decimali: 7,500 a 7, 0,502 a 1
        $quadri->imposta('VF034002', null);
        $quadri->imposta('VE050001', 1000);
        $quadri->imposta('VE033001', 925);
        $this->assertSame(7, PropostaIva::calcolaPercentuale($quadri));
        $quadri->imposta('VE050001', 100000);
        $quadri->imposta('VE033001', 99498);
        $this->assertSame(1, PropostaIva::calcolaPercentuale($quadri));
    }

    public function testGruppiDeiModuliERegioni(): void
    {
        $quadri = $this->creaQuadri();
        $this->assertSame([[1, 2]], PropostaIva::getGruppi($quadri, [2, 1]));
        $quadri->imposta('VA001001', '09556670702', 2);
        $this->assertSame([[1], [2]], PropostaIva::getGruppi($quadri, [1, 2]));
        $quadri->imposta('VA001001', null, 2);
        $quadri->imposta('VA003001', true, 1);
        $this->assertSame([[1], [2]], PropostaIva::getGruppi($quadri, [1, 2]));
        $this->assertSame(11, RegioniVt::getRigo('MI'));
        $this->assertSame(4, RegioniVt::getRigo('bz'));
        $this->assertSame(16, RegioniVt::getRigo('OT'));
        $this->assertNull(RegioniVt::getRigo('XX'));
    }

    public function testMaggiorazioneDelSaldoDifferito(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modalitaSaldo = ModalitaSaldo::differito;
        $this->assertSame('2026-06-30', VersamentiIva::getDataDifferimento($dichiarazione)->format('Y-m-d'));
        // dal 16 marzo al 30 giugno: quattro mesi o frazioni
        $this->assertSame(1.016, VersamentiIva::getMaggiorazione($dichiarazione));
        $dichiarazione->dataDifferimento = new SismaDate('2026-04-16');
        $this->assertSame(1.004, VersamentiIva::getMaggiorazione($dichiarazione));
        $dichiarazione->dataDifferimento = new SismaDate('2026-04-17');
        $this->assertSame(1.008, VersamentiIva::getMaggiorazione($dichiarazione));
        // nei 30 giorni successivi un ulteriore 0,40% sull'importo maggiorato
        $dichiarazione->dataDifferimento = new SismaDate('2026-07-30');
        $dichiarazione->maggiorazioneUlteriore = true;
        $this->assertSame(1.020064, VersamentiIva::getMaggiorazione($dichiarazione));
    }

    public function testPropostaConPiuModuliProrataETipologiaRc(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $vendita->id = 1;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-04-10');
        $acquisto->id = 2;
        $cellulari = $this->creaRigaIva('RC22', 200.0, 1, null, $acquisto);
        $cellulari->naturaInversione = NaturaIva::inversioneCellulari;
        $this->testAdapter->impostaSelectCallback(function (string $cmd) use ($vendita, $acquisto, $cellulari): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            $tabelle = [
                'dati_fiscali' => [$this->creaDatiFiscali()],
                'riga_iva' => [$this->creaRigaIva('22', 1000.0, 1, null, $vendita), $this->creaRigaIva('N4', 9000.0, 1, null, $vendita),
                    $this->creaRigaIva('22', 1000.0, 1, null, $acquisto), $cellulari],
            ];
            return new TestResultSet(preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1 ? ($tabelle[$tabella[1]] ?? []) : []);
        });
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        // modulo 2: attività con contabilità separata compilata a mano
        foreach (['VE023001' => 5000, 'VE023002' => 1100, 'VF013001' => 1000, 'VF013002' => 220] as $codice => $valore) {
            $quadri->imposta($codice, $valore, 2);
        }
        $modulo = new ModuloDichiarazione($this->dataMapper);
        $modulo->numero = 2;
        $modulo->tipo = TipoModulo::attivitaSeparata;
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, [$modulo]);
        // modulo 1: integrazione dei cellulari nel VJ14; pro-rata 10% (1.000 imponibili su 10.000 di volume d'affari)
        $this->assertSame(44, $quadri->getImporto('VJ014002', 1));
        $this->assertSame(0, $quadri->getImporto('VJ012002', 1));
        $this->assertSame('1', $quadri->get('VF030003', 1));
        $this->assertSame(10, $quadri->getImporto('VF034009', 1));
        $this->assertSame(264, $quadri->getImporto('VF027002', 1));
        $this->assertSame(26, $quadri->getImporto('VF037001', 1));
        $this->assertSame(26, $quadri->getImporto('VF071002', 1));
        $this->assertSame(238, $quadri->getImporto('VL003001', 1));
        // modulo 2: totali dai righi manuali, risultato nel modulo 1 (contabilità separate)
        $this->assertSame(5000, $quadri->getImporto('VE024001', 2));
        $this->assertSame(880, $quadri->getImporto('VL003001', 2));
        $this->assertSame(0, $quadri->getImporto('VL032001', 2));
        $this->assertSame(1118, $quadri->getImporto('VL032001', 1));
        $this->assertSame(1118, $quadri->getImporto('VX001001', 1));
        // VT sulla somma dei moduli, nel solo modulo 1
        $this->assertSame(6000, $quadri->getImporto('VT001001', 1));
        $this->assertNull($quadri->get('VT001001', 2));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'pro-rata di detrazione del 10%')));
        $this->assertEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'VJ12')));
        $segnalazioni = (new ControlliIva($this->dataMapper))->getSegnalazioni($quadri);
        $this->assertSame(['VT001003: indicato 6.000, risulta 0.', 'VT001004: indicato 1.320, risulta 0.'], $segnalazioni);
    }
}
