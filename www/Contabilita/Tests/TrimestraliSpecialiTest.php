<?php

namespace Contabilita\Tests;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\Lipe\PreparazioneLipe;
use Contabilita\Application\Services\PeriodiIva;

/**
 * Trimestrali per natura dell'attività (art. 74, c. 4, D.P.R. 633/1972;
 * dal 2027 art. 139, c. 4, D.Lgs. 10/2026): senza gli interessi dell'1%,
 * quarto trimestre liquidato e versato entro il 16 febbraio (6034) e
 * comunicato come trimestre "4".
 */
class TrimestraliSpecialiTest extends ContabilitaTestCase
{

    private function liquidazione(int $trimestre, bool $speciale): LiquidazioneIva
    {
        $liquidazione = new LiquidazioneIva($this->dataMapper);
        $liquidazione->id = $trimestre;
        $liquidazione->studio = $this->studioPredefinito;
        $liquidazione->soggetto = $this->azienda;
        $liquidazione->periodicita = PeriodicitaIva::trimestrale;
        $liquidazione->trimestraleSpeciale = $speciale;
        $liquidazione->anno = 2025;
        $liquidazione->periodo = $trimestre;
        $liquidazione->ivaEsigibile = 3000.0;
        $liquidazione->ivaDetraibile = 1000.0;
        return $liquidazione;
    }

    public function testTerminiECodiciTributo(): void
    {
        $this->assertSame('2026-03-16', PeriodiIva::getScadenza(PeriodicitaIva::trimestrale, 2025, 4)->format('Y-m-d'), 'per opzione: con il saldo annuale');
        $this->assertSame('2026-02-16', PeriodiIva::getScadenza(PeriodicitaIva::trimestrale, 2025, 4, true)->format('Y-m-d'));
        $this->assertSame('2025-08-20', PeriodiIva::getScadenza(PeriodicitaIva::trimestrale, 2025, 2, true)->format('Y-m-d'), 'gli altri trimestri non cambiano');
        $this->assertSame('6099', PeriodiIva::getCodiceTributo(PeriodicitaIva::trimestrale, 4));
        $this->assertSame('6034', PeriodiIva::getCodiceTributo(PeriodicitaIva::trimestrale, 4, true));
        $this->assertSame('6031', PeriodiIva::getCodiceTributo(PeriodicitaIva::trimestrale, 1, true));
    }

    public function testSenzaInteressiEQuartoTrimestrePeriodico(): void
    {
        $elaborazione = new ElaborazioneLiquidazione($this->dataMapper);
        $ordinario = $this->liquidazione(2, false);
        $elaborazione->determinaImporti($ordinario);
        $this->assertSame(20.0, $ordinario->interessi);
        $this->assertSame(2020.0, $ordinario->importoDovuto);
        $speciale = $this->liquidazione(2, true);
        $elaborazione->determinaImporti($speciale);
        $this->assertSame(0.0, $speciale->interessi);
        $this->assertSame(2000.0, $speciale->importoDovuto);

        $this->assertTrue($this->liquidazione(4, false)->isQuartoTrimestreConDichiarazione());
        $this->assertFalse($this->liquidazione(4, true)->isQuartoTrimestreConDichiarazione());
        $this->assertFalse($this->liquidazione(3, false)->isQuartoTrimestreConDichiarazione());
    }

    public function testComunicazioneDelQuartoTrimestre(): void
    {
        $this->rispondiConti();
        $comunicazione = new ComunicazioneLipe($this->dataMapper);
        $comunicazione->id = 1;
        $comunicazione->studio = $this->studioPredefinito;
        $comunicazione->soggetto = $this->azienda;
        $comunicazione->anno = 2025;
        $comunicazione->trimestre = 4;
        $quarto = $this->liquidazione(4, true);
        (new ElaborazioneLiquidazione($this->dataMapper))->determinaImporti($quarto);
        $avvisi = [];
        $modulo = (new PreparazioneLipe($this->dataMapper))->preparaModulo($comunicazione, $quarto, $avvisi);
        $this->assertSame(4, $modulo->trimestre, 'trimestre "4" e non "5"');
        $this->assertFalse($modulo->isQuartoTrimestreTrimestrali());
        $this->assertSame(2000.0, $modulo->importoDaVersare, 'il rigo VP14 si compila');
        $this->assertSame(0.0, $modulo->interessi);
        $this->assertSame([], $avvisi);
    }
}
