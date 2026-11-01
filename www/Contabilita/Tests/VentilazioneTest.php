<?php

namespace Contabilita\Tests;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Services\ControlloRegistrazione;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\VentilazioneCorrispettivi;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

class VentilazioneTest extends ContabilitaTestCase
{

    private CodiceIva $ven;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->ven = new CodiceIva($this->dataMapper);
        $this->ven->id = 70;
        $this->ven->codice = 'VEN';
        $this->ven->descrizione = 'Corrispettivi da ventilare';
        $this->ven->ventilazione = true;
        $this->codiciIva['VEN'] = $this->ven;
    }

    private function acquisto(string $codice, float $imponibile, string $data, bool $rivendita = true): RigaIva
    {
        $registrazione = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, $data);
        $riga = $this->creaRigaIva($codice, $imponibile, 1, null, $registrazione);
        $riga->rivendita = $rivendita;
        return $riga;
    }

    private function corrispettivi(float $lordo, string $data): RigaIva
    {
        $registrazione = $this->creaRegistrazione(CausaleContabile::corrispettivi, null, $data);
        return $this->creaRigaIva('VEN', $lordo, 1, 0.0, $registrazione);
    }

    /**
     * Esempio della documentazione consultata: acquisti per rivendita da
     * gennaio ad aprile 1.320,00 al 10% e 14.640,00 al 22% (IVA compresa),
     * corrispettivi di aprile 10.000,00.
     */
    public function testRipartizioneConIRapportiProgressivi(): void
    {
        $righe = [
            $this->acquisto('10', 1200.0, '2026-01-20'),
            $this->acquisto('22', 6000.0, '2026-02-10'),
            $this->acquisto('22', 6000.0, '2026-04-05'),
            $this->acquisto('22', 5000.0, '2026-03-01', false),
            $this->corrispettivi(7000.0, '2026-03-31'),
            $this->corrispettivi(10000.0, '2026-04-30'),
        ];
        $this->testAdapter->accodaSelect(new TestResultSet($righe));
        $risultato = (new VentilazioneCorrispettivi($this->dataMapper))->calcola($this->azienda, new SismaDate('2026-04-01'), new SismaDate('2026-04-30'));
        $this->assertSame(10000.0, $risultato->corrispettivi, 'solo i corrispettivi del periodo');
        $this->assertSame(15960.0, $risultato->acquisti, 'acquisti per rivendita dal 1° gennaio, IVA compresa');
        $this->assertCount(2, $risultato->righe);
        [$dieci, $ventidue] = $risultato->righe;
        $this->assertSame('10', $dieci['codice']->codice);
        $this->assertSame(827.07, $dieci['corrispettivi']);
        $this->assertSame(75.19, $dieci['imposta']);
        $this->assertSame(9172.93, $ventidue['corrispettivi']);
        $this->assertSame(1654.13, $ventidue['imposta']);
        $this->assertSame(1729.32, $risultato->imposta);
    }

    public function testSenzaCorrispettiviOAcquisti(): void
    {
        $this->testAdapter->accodaSelect(new TestResultSet([$this->acquisto('22', 100.0, '2026-01-10')]));
        $this->assertSame(0.0, (new VentilazioneCorrispettivi($this->dataMapper))->calcola($this->azienda, new SismaDate('2026-01-01'), new SismaDate('2026-01-31'))->imposta);
        $this->testAdapter->accodaSelect(new TestResultSet([$this->corrispettivi(500.0, '2026-01-31'), $this->acquisto('22', 100.0, '2026-01-10', false)]));
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('senza acquisti di beni per la rivendita');
        (new VentilazioneCorrispettivi($this->dataMapper))->calcola($this->azienda, new SismaDate('2026-01-01'), new SismaDate('2026-01-31'));
    }

    public function testCodiceDiVentilazioneSoloNeiCorrispettivi(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, $this->creaControparte(1, 'Beta', $this->conti['06.01.0001'], null));
        $errori = (new ControlloRegistrazione())->verifica($this->piano, $vendita, [$this->creaRigaIva('VEN', 100.0, 1, 0.0)], []);
        $this->assertStringContainsString('vale solo per i corrispettivi', $errori['righeIva']);
        $corrispettivi = $this->creaRegistrazione(CausaleContabile::corrispettivi);
        $errori = (new ControlloRegistrazione())->verifica($this->piano, $corrispettivi, [$this->creaRigaIva('VEN', 100.0, 1, 5.0)], []);
        $this->assertStringContainsString('al lordo, senza imposta', $errori['righeIva']);
    }

    public function testLiquidazioneConVentilazioneEGiroconto(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->azienda;
        $datiFiscali->validoDal = new SismaDate('2020-01-01');
        $datiFiscali->partitaIva = '01234567897';
        $datiFiscali->regimeFiscale = RegimeFiscale::semplificato;
        $datiFiscali->periodicitaIva = PeriodicitaIva::mensile;
        $datiFiscali->ventilazioneCorrispettivi = false;
        $codice = new CodiceTributo($this->dataMapper);
        $codice->id = 6003;
        $codice->sezione = SezioneCodiceTributo::erario;
        $codice->codice = '6003';
        $codice->descrizione = 'VERSAMENTO IVA MENSILE MARZO';
        $righe = [$this->acquisto('10', 1000.0, '2026-03-05'), $this->corrispettivi(2200.0, '2026-03-31')];
        $tabelle = ['dati_fiscali' => [$datiFiscali], 'riga_iva' => $righe, 'codice_tributo' => [$codice], 'esercizio_contabile' => [$this->esercizio], 'piano_conti' => [$this->piano]];
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind) use ($tabelle): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            if (str_contains($cmd, 'FROM `conto`')) {
                foreach ($bind as $valore) {
                    $funzione = $valore instanceof FunzioneConto ? $valore : FunzioneConto::tryFrom(is_scalar($valore) ? (string) $valore : '');
                    if ($funzione !== null) {
                        return new TestResultSet(isset($this->conti[$funzione->value]) ? [$this->conti[$funzione->value]] : []);
                    }
                }
                return new TestResultSet([]);
            }
            if (preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1) {
                return new TestResultSet($tabelle[$tabella[1]] ?? []);
            }
            return new TestResultSet([]);
        });
        $liquidazione = (new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->azienda, 2026, 3, 0.0, true);
        // corrispettivi 2.200 tutti al 10% (unica aliquota degli acquisti): imposta 200
        $this->assertSame(200.0, $liquidazione->ivaVentilata);
        $this->assertSame(200.0, $liquidazione->ivaEsigibile);
        $this->assertSame(100.0, $liquidazione->ivaDetraibile);
        $this->assertSame(0.0, $liquidazione->importoDovuto);
        $this->assertSame(100.0, $liquidazione->debitoRinviato, 'non superiore a 100 euro: rinviato al periodo successivo');
        $this->assertStringContainsString('la ventilazione dei corrispettivi non è indicata', (string) $liquidazione->avvisi);
        $righeGiroconto = array_values(array_filter($this->testAdapter->queryEseguite, fn(array $query) => str_starts_with($query['cmd'], 'INSERT') && str_contains($query['cmd'], '`riga_contabile`')));
        $this->assertCount(3, $righeGiroconto, 'ricavi in dare per l\'imposta ventilata, IVA acquisti, Erario c/IVA');
        $this->assertContains($this->conti['RIC']->id, array_merge(...array_map(fn(array $query) => array_map(fn($valore) => is_object($valore) ? ($valore->id ?? null) : $valore, $query['bindValues']), $righeGiroconto)));
    }

    public function testVentilazioneAnnualeNellUltimoPeriodo(): void
    {
        $datiFiscali = new DatiFiscali($this->dataMapper);
        $datiFiscali->soggetto = $this->azienda;
        $datiFiscali->validoDal = new SismaDate('2020-01-01');
        $datiFiscali->partitaIva = '01234567897';
        $datiFiscali->regimeFiscale = RegimeFiscale::semplificato;
        $datiFiscali->periodicitaIva = PeriodicitaIva::mensile;
        $datiFiscali->ventilazioneCorrispettivi = true;
        $codice = new CodiceTributo($this->dataMapper);
        $codice->id = 6012;
        $codice->sezione = SezioneCodiceTributo::erario;
        $codice->codice = '6012';
        $codice->descrizione = 'VERSAMENTO IVA MENSILE DICEMBRE';
        $novembre = new LiquidazioneIva($this->dataMapper);
        $novembre->id = 11;
        $novembre->anno = 2026;
        $novembre->periodo = 11;
        $novembre->periodicita = PeriodicitaIva::mensile;
        $novembre->ivaVentilata = 90.0;
        $righe = [$this->acquisto('10', 1000.0, '2026-01-10'), $this->corrispettivi(1100.0, '2026-11-30'), $this->corrispettivi(1100.0, '2026-12-31')];
        $tabelle = ['dati_fiscali' => [$datiFiscali], 'riga_iva' => $righe, 'codice_tributo' => [$codice]];
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind) use ($tabelle, $novembre): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            if (str_contains($cmd, 'FROM `liquidazione_iva`')) {
                return new TestResultSet(in_array(12, $bind, true) ? [] : [$novembre]);
            }
            if (preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1) {
                return new TestResultSet($tabelle[$tabella[1]] ?? []);
            }
            return new TestResultSet([]);
        });
        $liquidazione = (new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->azienda, 2026, 12, 0.0, false);
        $this->assertSame(100.0, $liquidazione->ivaVentilata, 'dicembre: 1.100 di corrispettivi al 10%');
        // anno: 2.200 al 10% = 200 di imposta; nelle liquidazioni 90 + 100 = 190
        $this->assertStringContainsString('Ventilazione annuale', (string) $liquidazione->avvisi);
        $this->assertStringContainsString('differenza € 10,00', (string) $liquidazione->avvisi);
        $this->assertStringNotContainsString('non è indicata', (string) $liquidazione->avvisi);
    }

    public function testLimitiSullAnnoPrecedente(): void
    {
        $merci = $this->acquisto('22', 6000.0, '2025-03-10');
        $altri = $this->acquisto('22', 4000.0, '2025-04-10');
        $altri->altriProdotti = true;
        $servizi = $this->acquisto('22', 1000.0, '2025-05-10', false);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-06-10');
        $fattura = $this->creaRigaIva('22', 3000.0, 1, null, $vendita);
        $cespite = $this->creaRigaIva('22', 5000.0, 1, null, $vendita);
        $impianti = new Conto($this->dataMapper);
        $impianti->id = 100900;
        $impianti->pianoConti = $this->piano;
        $impianti->codice = '02.01.0001';
        $impianti->descrizione = 'Impianti e macchinari';
        $impianti->natura = NaturaConto::attivita;
        $impianti->livello = LivelloConto::sottoconto;
        $cespite->conto = $impianti;
        $corrispettivi = $this->creaRigaIva('10', 9000.0, 1, null, $this->creaRegistrazione(CausaleContabile::corrispettivi, null, '2025-06-30'));
        $this->rispondiConti(['riga_iva' => [$merci, $altri, $servizi, $fattura, $cespite, $corrispettivi]]);
        $requisiti = (new \Contabilita\Application\Services\RequisitiVentilazione($this->dataMapper))->verifica($this->azienda, 2026);
        $this->assertSame(2025, $requisiti['anno']);
        $this->assertTrue($requisiti['disponibile']);
        $this->assertSame(36.36, $requisiti['percentualeAltri'], '4.000 su 11.000 di acquisti e importazioni');
        $this->assertSame(3000.0, $requisiti['fatture'], 'esclusa la cessione del bene strumentale');
        $this->assertSame(12000.0, $requisiti['volumeAffari']);
        $this->assertSame(25.0, $requisiti['percentualeFatture']);
        $this->assertCount(1, $requisiti['superati']);
        $this->assertStringContainsString('art. 5', $requisiti['superati'][0]);

        $this->rispondiConti();
        $this->assertFalse((new \Contabilita\Application\Services\RequisitiVentilazione($this->dataMapper))->verifica($this->azienda, 2026)['disponibile']);
    }

    public function testSceltaRivenditaNelleRigheIva(): void
    {
        $this->rispondiConti(['codice_iva' => [$this->codiciIva['22']]]);
        foreach (['' => [false, false], '1' => [true, false], '2' => [true, true]] as $valore => [$rivendita, $altriProdotti]) {
            $form = new \Contabilita\Application\Forms\RigaIvaForm(new RigaIva($this->dataMapper), $this->dataMapper);
            $form->handleRequest($this->creaRequest(['submitted' => '1', 'codiceIva' => '1', 'imponibile' => '100,00', 'imposta' => '22,00', 'rivendita' => (string) $valore]));
            $this->assertTrue($form->isValid(), 'valore ' . $valore);
            $riga = $form->resolveEntity();
            $this->assertSame([$rivendita, $altriProdotti], [$riga->rivendita, $riga->altriProdotti], 'valore ' . $valore);
        }
    }
}
