<?php

namespace Contabilita\Tests;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Contabilita\Application\Entities\AccontoIva;
use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\ModuloLipe;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Contabilita\Application\Enumerations\ImpegnoPresentazione;
use Contabilita\Application\Enumerations\MetodoAccontoIva;
use Contabilita\Application\Services\Lipe\GeneratoreLipe;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\PeriodiIva;
use Deleghe\Application\Entities\Intermediario;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

class LipeTest extends ContabilitaTestCase
{

    private function liquidazione(PeriodicitaIva $periodicita, int $periodo, float $esigibile, float $detraibile): LiquidazioneIva
    {
        $liquidazione = new LiquidazioneIva($this->dataMapper);
        $liquidazione->id = $periodo;
        $liquidazione->studio = $this->studioPredefinito;
        $liquidazione->soggetto = $this->azienda;
        $liquidazione->periodicita = $periodicita;
        $liquidazione->anno = 2026;
        $liquidazione->periodo = $periodo;
        $liquidazione->ivaEsigibile = $esigibile;
        $liquidazione->ivaDetraibile = $detraibile;
        return $liquidazione;
    }

    private function comunicazione(int $trimestre = 1): ComunicazioneLipe
    {
        $comunicazione = new ComunicazioneLipe($this->dataMapper);
        $comunicazione->id = 1;
        $comunicazione->studio = $this->studioPredefinito;
        $comunicazione->soggetto = $this->azienda;
        $comunicazione->anno = 2026;
        $comunicazione->trimestre = $trimestre;
        $comunicazione->progressivo = 3;
        return $comunicazione;
    }

    public function testScadenze(): void
    {
        $this->assertSame('2026-06-01', PeriodiIva::getScadenzaLipe(2026, 1)->format('Y-m-d'), '31 maggio domenica');
        $this->assertSame('2026-09-30', PeriodiIva::getScadenzaLipe(2026, 2)->format('Y-m-d'));
        $this->assertSame('2026-11-30', PeriodiIva::getScadenzaLipe(2026, 3)->format('Y-m-d'));
        $this->assertSame('2026-03-02', PeriodiIva::getScadenzaLipe(2025, 4)->format('Y-m-d'), '28 febbraio sabato');
    }

    public function testModuloMensileConAccontoEOperazioni(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2026-12-10');
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2026-12-11');
        $acconto = new AccontoIva($this->dataMapper);
        $acconto->metodo = MetodoAccontoIva::previsionale;
        $acconto->importo = 200.0;
        $this->rispondiConti(['riga_iva' => [$this->creaRigaIva('22', 2000.0, 1, null, $vendita), $this->creaRigaIva('N4', 300.0, 1, null, $vendita),
                $this->creaRigaIva('A22', 500.0, 1, null, $acquisto), $this->creaRigaIva('RC22', 100.0, 1, null, $acquisto)], 'acconto_iva' => [$acconto]]);
        $dicembre = $this->liquidazione(PeriodicitaIva::mensile, 12, 462.0, 66.0);
        $dicembre->debitoPrecedente = 30.0;
        $dicembre->creditoPrecedente = 10.0;
        $dicembre->acconto = 200.0;
        $dicembre->importoDovuto = 216.0;
        $avvisi = [];
        $modulo = (new PreparazioneLipe($this->dataMapper))->preparaModulo($this->comunicazione(4), $dicembre, $avvisi);
        $this->assertSame(12, $modulo->mese);
        $this->assertNull($modulo->trimestre);
        $this->assertSame(2300.0, $modulo->operazioniAttive, 'comprese le esenti');
        $this->assertSame(600.0, $modulo->operazioniPassive, 'comprese indetraibili e inversione contabile');
        $this->assertSame(0.0, $modulo->debitoPrecedente, 'VP7 non ammesso a dicembre');
        $this->assertSame(10.0, $modulo->creditoPrecedente);
        $this->assertSame('2', $modulo->metodoAcconto);
        $this->assertSame(200.0, $modulo->acconto);
        $this->assertSame(186.0, $modulo->importoDaVersare);
        $this->assertCount(2, $avvisi, 'VP7 omesso e VP14 diverso dalla liquidazione');
    }

    public function testQuartoTrimestreDeiTrimestrali(): void
    {
        $this->rispondiConti();
        $quarto = $this->liquidazione(PeriodicitaIva::trimestrale, 4, 1000.0, 200.0);
        $quarto->interessi = 8.0;
        $quarto->importoDovuto = 808.0;
        $avvisi = [];
        $modulo = (new PreparazioneLipe($this->dataMapper))->preparaModulo($this->comunicazione(4), $quarto, $avvisi);
        $this->assertSame(5, $modulo->trimestre);
        $this->assertTrue($modulo->isQuartoTrimestreTrimestrali());
        $this->assertSame(0.0, $modulo->interessi);
        $this->assertSame(0.0, $modulo->importoDaVersare);
        $this->assertSame([], $avvisi);

        $secondo = $this->liquidazione(PeriodicitaIva::trimestrale, 2, 1000.0, 200.0);
        $secondo->interessi = 8.0;
        $secondo->importoDovuto = 808.0;
        $modulo = (new PreparazioneLipe($this->dataMapper))->preparaModulo($this->comunicazione(2), $secondo, $avvisi);
        $this->assertSame(8.0, $modulo->interessi);
        $this->assertSame(808.0, $modulo->importoDaVersare);

        $quarto->creditiImposta = 30.0;
        $quarto->versamentiAuto = 20.0;
        $modulo = (new PreparazioneLipe($this->dataMapper))->preparaModulo($this->comunicazione(4), $quarto, $avvisi);
        $this->assertSame(0.0, $modulo->creditiImposta, 'VP11 non nel quarto trimestre dei trimestrali');
        $this->assertSame(20.0, $modulo->versamentiAuto);
    }

    public function testVersamentiAutoECreditiNelModulo(): void
    {
        $this->rispondiConti();
        $maggio = $this->liquidazione(PeriodicitaIva::mensile, 5, 1000.0, 300.0);
        $maggio->versamentiAuto = 150.0;
        $maggio->creditiImposta = 80.0;
        $maggio->importoDovuto = 470.0;
        $avvisi = [];
        $modulo = (new PreparazioneLipe($this->dataMapper))->preparaModulo($this->comunicazione(2), $maggio, $avvisi);
        $this->assertSame([150.0, 80.0, 470.0], [$modulo->versamentiAuto, $modulo->creditiImposta, $modulo->importoDaVersare]);
        $this->assertSame([], $avvisi);
    }

    public function testPreparazioneRichiedeLeLiquidazioni(): void
    {
        $dati = new DatiFiscali($this->dataMapper);
        $dati->soggetto = $this->azienda;
        $dati->validoDal = new SismaDate('2020-01-01');
        $dati->partitaIva = '01234560785';
        $dati->regimeFiscale = RegimeFiscale::ordinario;
        $dati->periodicitaIva = PeriodicitaIva::mensile;
        $this->rispondiConti(['dati_fiscali' => [$dati], 'liquidazione_iva' => [$this->liquidazione(PeriodicitaIva::mensile, 1, 0.0, 0.0)]]);
        try {
            (new PreparazioneLipe($this->dataMapper))->prepara($this->azienda, 2026, 1);
            $this->fail('Preparata senza le liquidazioni di febbraio e marzo');
        } catch (\DomainException $errore) {
            $this->assertStringContainsString('febbraio 2026, marzo 2026', $errore->getMessage());
        }
    }

    public function testRappresentanteProposto(): void
    {
        $amministratore = $this->creaSoggettoFisico(2, $this->studioPredefinito, 'RSSMRA70A01H501S', 'Rossi', 'Mario');
        $legame = new Legame($this->dataMapper);
        $legame->soggetto = $this->azienda;
        $legame->soggettoCollegato = $amministratore;
        $legame->ruolo = RuoloLegame::liquidatore;
        $legame->validoDal = new SismaDate('2020-01-01');
        $this->rispondiConti(['legame' => [$legame]]);
        $trovato = (new PreparazioneLipe($this->dataMapper))->getRappresentante($this->azienda, new SismaDate('2026-05-01'));
        $this->assertSame($amministratore, $trovato?->soggettoCollegato);
    }

    public function testFileConformeAlloSchema(): void
    {
        $comunicazione = $this->comunicazione();
        $generatore = new GeneratoreLipe($this->dataMapper);
        $this->rispondiConti();
        $errori = $generatore->getErrori($comunicazione);
        $this->assertStringContainsString('dichiarante', $errori[0]);
        $this->assertStringContainsString('Nessun modulo', end($errori));

        $comunicazione->dichiarante = $this->creaSoggettoFisico(2, $this->studioPredefinito, 'RSSMRA70A01H501S', 'Rossi', 'Mario');
        $comunicazione->codiceCarica = CodiceCaricaIva::rappresentanteLegale;
        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->id = 1;
        $intermediario->soggetto = $this->creaSoggettoFisico(3, $this->studioPredefinito, 'VRDLGI65B02F205X', 'Verdi', 'Luigi');
        $comunicazione->intermediario = $intermediario;
        $comunicazione->impegno = ImpegnoPresentazione::intermediario;
        $comunicazione->dataImpegno = new SismaDate('2026-04-15');
        $moduli = [];
        foreach ([1 => [500.0, 800.0, 0.0, 300.0], 2 => [900.0, 200.0, 0.0, 0.0], 3 => [100.0, 50.0, 0.0, 0.0]] as $mese => [$esigibile, $detratta, $versare, $credito]) {
            $modulo = new ModuloLipe($this->dataMapper);
            $modulo->comunicazioneLipe = $comunicazione;
            $modulo->numeroModulo = $mese;
            $modulo->mese = $mese;
            $modulo->operazioniAttive = $esigibile * 5;
            $modulo->operazioniPassive = -$detratta;
            $modulo->ivaEsigibile = $esigibile;
            $modulo->ivaDetratta = $detratta;
            $modulo->creditoPrecedente = $mese === 2 ? 300.0 : 0.0;
            $modulo->creditoAnnoPrecedente = $mese === 3 ? -20.0 : 0.0;
            $modulo->versamentiAuto = $mese === 2 ? 50.0 : 0.0;
            $modulo->creditiImposta = $mese === 2 ? 30.0 : 0.0;
            $modulo->importoDaVersare = $mese === 2 ? 320.0 : ($mese === 3 ? 70.0 : 0.0);
            $modulo->importoACredito = $credito;
            $moduli[] = $modulo;
        }
        $this->rispondiConti(['modulo_lipe' => $moduli]);
        $this->assertSame([], $generatore->getErrori($comunicazione));
        $xml = $generatore->genera($comunicazione);
        $this->assertNull(GeneratoreLipe::verifica($xml));
        $this->assertStringContainsString('<iv:Comunicazione identificativo="00003">', $xml);
        $this->assertStringContainsString('<iv:DataImpegno>15042026</iv:DataImpegno>', $xml);
        $this->assertStringContainsString('<iv:TotaleOperazioniPassive>-800,00</iv:TotaleOperazioniPassive>', $xml);
        $this->assertStringContainsString('<iv:IvaCredito>300,00</iv:IvaCredito>', $xml);
        $this->assertStringContainsString('<iv:CreditoAnnoPrecedente>-20,00</iv:CreditoAnnoPrecedente>', $xml);
        $this->assertStringNotContainsString('<iv:DebitoPrecedente>', $xml, 'importi zero omessi');
        $this->assertStringContainsString('<iv:VersamentiAutoUE>50,00</iv:VersamentiAutoUE>' . "\n" . '        <iv:CreditiImposta>30,00</iv:CreditiImposta>', $xml);
        $this->assertSame('IT01234560785_LI_00003.xml', GeneratoreLipe::getNomeFile($comunicazione));

        $comunicazione->dataImpegno = null;
        $this->assertStringContainsString('impegno', implode(' ', $generatore->getErrori($comunicazione)));
        $this->assertNotNull(GeneratoreLipe::verifica(str_replace('<iv:Mese>1</iv:Mese>', '<iv:Mese>13</iv:Mese>', $xml)));
    }
}
