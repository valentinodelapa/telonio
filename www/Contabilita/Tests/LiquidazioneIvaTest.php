<?php

namespace Contabilita\Tests;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Contabilita\Application\Entities\AccontoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Services\CalcoloLiquidazioneIva;
use Contabilita\Application\Services\ElaborazioneAccontoIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\RegimeIva;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

class LiquidazioneIvaTest extends ContabilitaTestCase
{

    private function liquidazione(PeriodicitaIva $periodicita, int $periodo, float $esigibile, float $detraibile, int $anno = 2026): LiquidazioneIva
    {
        $liquidazione = new LiquidazioneIva($this->dataMapper);
        $liquidazione->periodicita = $periodicita;
        $liquidazione->anno = $anno;
        $liquidazione->periodo = $periodo;
        $liquidazione->ivaEsigibile = $esigibile;
        $liquidazione->ivaDetraibile = $detraibile;
        return $liquidazione;
    }

    public function testImportiDovutiRinviatiEInteressi(): void
    {
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        $mensile = $this->liquidazione(PeriodicitaIva::mensile, 3, 590.0, 176.0);
        $mensile->creditoPrecedente = 14.0;
        $elaborazione->determinaImporti($mensile);
        $this->assertSame(400.0, $mensile->importoDovuto);
        $this->assertSame(0.0, $mensile->interessi);

        $piccolo = $this->liquidazione(PeriodicitaIva::mensile, 3, 120.0, 30.0);
        $elaborazione->determinaImporti($piccolo);
        $this->assertSame(0.0, $piccolo->importoDovuto);
        $this->assertSame(90.0, $piccolo->debitoRinviato);

        $successivo = $this->liquidazione(PeriodicitaIva::mensile, 4, 50.0, 0.0);
        $successivo->debitoPrecedente = 90.0;
        $elaborazione->determinaImporti($successivo);
        $this->assertSame(140.0, $successivo->importoDovuto, 'il debito rinviato si somma al periodo successivo');

        $novembre = $this->liquidazione(PeriodicitaIva::mensile, 11, 60.0, 0.0);
        $elaborazione->determinaImporti($novembre);
        $this->assertSame(60.0, $novembre->importoDovuto, 'novembre non si rinvia');

        $trimestre = $this->liquidazione(PeriodicitaIva::trimestrale, 3, 1000.0, 200.0);
        $elaborazione->determinaImporti($trimestre);
        $this->assertSame(8.0, $trimestre->interessi);
        $this->assertSame(808.0, $trimestre->importoDovuto);

        $credito = $this->liquidazione(PeriodicitaIva::mensile, 12, 100.0, 50.0);
        $credito->acconto = 80.0;
        $credito->creditoAnnoPrecedente = 10.0;
        $elaborazione->determinaImporti($credito);
        $this->assertSame(40.0, $credito->creditoFinale);
        $this->assertSame(0.0, $credito->importoDovuto);
    }

    public function testCalcoloDaiRegistri(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita);
        $vendita->id = 1;
        $split = $this->creaRegistrazione(CausaleContabile::fatturaVendita);
        $split->id = 2;
        $split->esigibilita = EsigibilitaIva::scissionePagamenti;
        $differita = $this->creaRegistrazione(CausaleContabile::fatturaVendita);
        $differita->id = 3;
        $differita->esigibilita = EsigibilitaIva::differita;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto);
        $acquisto->id = 4;
        $nota = $this->creaRegistrazione(CausaleContabile::notaCreditoRicevuta);
        $nota->id = 5;
        $this->testAdapter->accodaSelect(new TestResultSet([
                    $this->creaRigaIva('22', 1000.0, 1, null, $vendita),
                    $this->creaRigaIva('22', 500.0, 1, null, $split),
                    $this->creaRigaIva('10', 100.0, 1, null, $differita),
                    $this->creaRigaIva('A22', 500.0, 1, null, $acquisto),
                    $this->creaRigaIva('RC22', 200.0, 1, null, $acquisto),
                    $this->creaRigaIva('22', 50.0, -1, null, $nota),
        ]));
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($this->azienda, new SismaDate('2026-02-01'), new SismaDate('2026-02-28'));
        // vendite: 220 (split ed esigibilità differita esclusi) + integrazione RC 44
        $this->assertSame(264.0, $risultato->ivaEsigibile);
        // acquisti: 44 (40% di 110) + 44 (RC) - 11 (nota)
        $this->assertSame(77.0, $risultato->ivaDetraibile);
        $this->assertSame(187.0, $risultato->getIvaPeriodo());
        $this->assertCount(1, $risultato->avvisi);
        $this->assertStringContainsString('differita', $risultato->avvisi[0]);
        $this->assertCount(5, $risultato->riepilogo);
    }

    private function datiFiscali(?RegimeFiscale $regime, ?PeriodicitaIva $periodicita): DatiFiscali
    {
        $dati = new DatiFiscali($this->dataMapper);
        $dati->soggetto = $this->azienda;
        $dati->validoDal = new SismaDate('2020-01-01');
        $dati->partitaIva = '01234567897';
        $dati->regimeFiscale = $regime;
        $dati->periodicitaIva = $periodicita;
        return $dati;
    }

    public function testRegimeIva(): void
    {
        $this->testAdapter->accodaSelect(new TestResultSet([$this->datiFiscali(RegimeFiscale::forfetario, null)]));
        try {
            (new RegimeIva($this->dataMapper))->getPeriodicita($this->azienda, 2026);
            $this->fail('Forfetario senza liquidazioni');
        } catch (\DomainException $errore) {
            $this->assertStringContainsString('non prevede le liquidazioni', $errore->getMessage());
        }
        $this->testAdapter->accodaSelect(new TestResultSet([$this->datiFiscali(RegimeFiscale::associazioniSportive, PeriodicitaIva::mensile)]));
        $this->assertSame(PeriodicitaIva::trimestrale, (new RegimeIva($this->dataMapper))->getPeriodicita($this->azienda, 2026), 'L. 398/1991: trimestrale per legge');
        $this->testAdapter->accodaSelect(new TestResultSet([]), new TestResultSet([]));
        $this->expectException(\DomainException::class);
        (new RegimeIva($this->dataMapper))->getPeriodicita($this->azienda, 2026);
    }

    public function testVersamentiAutoECreditiImposta(): void
    {
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        $mensile = $this->liquidazione(PeriodicitaIva::mensile, 5, 1000.0, 300.0);
        $mensile->creditoPrecedente = 100.0;
        $mensile->versamentiAuto = 150.0;
        $this->assertSame(450.0, $elaborazione->getMassimoCreditiImposta($mensile), '700 - 100 - 150');
        $mensile->creditiImposta = 80.0;
        $elaborazione->determinaImporti($mensile);
        $this->assertSame(370.0, $mensile->importoDovuto);

        $trimestre = $this->liquidazione(PeriodicitaIva::trimestrale, 2, 500.0, 100.0);
        $trimestre->creditiImposta = 100.0;
        $elaborazione->determinaImporti($trimestre);
        $this->assertSame(3.0, $trimestre->interessi, 'interessi sull\'importo dopo lo scomputo');
        $this->assertSame(303.0, $trimestre->importoDovuto);

        $credito = $this->liquidazione(PeriodicitaIva::mensile, 5, 100.0, 300.0);
        $this->assertSame(0.0, $elaborazione->getMassimoCreditiImposta($credito), 'con IVA a credito nessuno scomputo');
    }

    public function testCreditiImpostaOltreIlLimite(): void
    {
        $this->rispondiConti(['dati_fiscali' => [$this->datiFiscali(RegimeFiscale::ordinario, PeriodicitaIva::mensile)]]);
        try {
            (new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->azienda, 2026, 1, 0.0, false, 0.0, 50.0);
            $this->fail('Crediti scomputati senza IVA da versare');
        } catch (\DomainException $errore) {
            $this->assertStringContainsString('VP11', $errore->getMessage());
        }
        $this->rispondiConti(['dati_fiscali' => [$this->datiFiscali(RegimeFiscale::ordinario, PeriodicitaIva::trimestrale)]]);
        $this->expectExceptionMessage('Quarto trimestre');
        (new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->azienda, 2026, 4, 0.0, false, 0.0, 50.0);
    }

    public function testElaborazioneConCreditoAccontoVersamentoEGiroconto(): void
    {
        $dicembre = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2026-12-10');
        $dicembre->id = 9;
        $precedente = $this->liquidazione(PeriodicitaIva::mensile, 11, 0.0, 0.0);
        $precedente->id = 11;
        $precedente->creditoFinale = 50.0;
        $acconto = new AccontoIva($this->dataMapper);
        $acconto->id = 1;
        $acconto->importo = 200.0;
        $codice = new CodiceTributo($this->dataMapper);
        $codice->id = 6012;
        $codice->sezione = SezioneCodiceTributo::erario;
        $codice->codice = '6012';
        $codice->descrizione = 'VERSAMENTO IVA MENSILE DICEMBRE';
        $this->piano = new PianoConti($this->dataMapper);
        $this->piano->id = 1000;
        $this->piano->soggetto = $this->azienda;
        $this->piano->studio = $this->studioPredefinito;
        $this->piano->denominazione = 'Piano';
        $tabelle = ['dati_fiscali' => [$this->datiFiscali(RegimeFiscale::ordinario, PeriodicitaIva::mensile)], 'riga_iva' => [$this->creaRigaIva('22', 2000.0, 1, null, $dicembre)],
            'acconto_iva' => [$acconto], 'codice_tributo' => [$codice], 'esercizio_contabile' => [$this->esercizio], 'piano_conti' => [$this->piano]];
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind) use ($tabelle, $precedente): TestResultSet {
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
            if (str_contains($cmd, 'FROM `liquidazione_iva`')) {
                return new TestResultSet(in_array(11, $bind, true) ? [$precedente] : []);
            }
            if (preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1) {
                return new TestResultSet($tabelle[$tabella[1]] ?? []);
            }
            return new TestResultSet([]);
        });
        $liquidazione = (new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->azienda, 2026, 12, 0.0, true);
        $this->assertSame(PeriodicitaIva::mensile, $liquidazione->periodicita);
        $this->assertSame(440.0, $liquidazione->ivaEsigibile);
        $this->assertSame(50.0, $liquidazione->creditoPrecedente);
        $this->assertSame(200.0, $liquidazione->acconto);
        $this->assertSame(190.0, $liquidazione->importoDovuto);
        $this->assertSame('2027-01-18', $liquidazione->scadenza->format('Y-m-d'));
        $this->assertNotNull($liquidazione->registrazioneContabile);
        $this->assertSame(CausaleContabile::liquidazioneIva, $liquidazione->registrazioneContabile->causale);
        $inserimenti = array_values(array_filter($this->testAdapter->queryEseguite, fn(array $query) => str_starts_with($query['cmd'], 'INSERT')));
        $versamento = array_values(array_filter($inserimenti, fn(array $query) => str_contains($query['cmd'], '`versamento`')));
        $this->assertCount(1, $versamento);
        $this->assertContains('Contabilita', $versamento[0]['bindValues']);
        $this->assertContains('iva-2026-12', $versamento[0]['bindValues']);
        $righe = array_values(array_filter($inserimenti, fn(array $query) => str_contains($query['cmd'], '`riga_contabile`')));
        $this->assertCount(3, $righe, 'IVA vendite, acconto, Erario c/IVA');
    }

    public function testPropostaAccontoStorico(): void
    {
        $precedente = $this->liquidazione(PeriodicitaIva::mensile, 12, 0.0, 0.0, 2025);
        $precedente->importoDovuto = 900.0;
        $precedente->acconto = 100.0;
        $this->testAdapter->accodaSelect(new TestResultSet([$precedente]));
        [$base, $provenienza] = (new ElaborazioneAccontoIva($this->dataMapper))->proponiBase($this->azienda, 2026, PeriodicitaIva::mensile, MetodoAccontoIva::storico);
        $this->assertSame(1000.0, $base);
        $this->assertStringContainsString('dicembre 2025', $provenienza);
        $this->assertSame([null, 'Indicare il dovuto presunto per l\'ultimo periodo dell\'anno.'], (new ElaborazioneAccontoIva($this->dataMapper))->proponiBase($this->azienda, 2026, PeriodicitaIva::mensile, MetodoAccontoIva::previsionale));
        $acconto = new AccontoIva($this->dataMapper);
        $acconto->importo = 103.28;
        $this->assertFalse($acconto->isDovuto());
        $acconto->importo = 103.29;
        $this->assertTrue($acconto->isDovuto());
    }
}
