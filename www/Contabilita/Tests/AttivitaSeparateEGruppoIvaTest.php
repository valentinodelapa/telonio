<?php

namespace Contabilita\Tests;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneGruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\PartecipanteGruppoIva;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Services\AttivitaSeparate;
use Contabilita\Application\Services\CalcoloLiquidazioneIva;
use Contabilita\Application\Services\ElaborazioneLiquidazione;
use Contabilita\Application\Services\LiquidazioneGruppo;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Attività con contabilità separata (art. 36) e liquidazione IVA di
 * gruppo (art. 73, c. 3).
 */
class AttivitaSeparateEGruppoIvaTest extends ContabilitaTestCase
{

    private function creaDatiFiscali(): DatiFiscali
    {
        $dati = new DatiFiscali($this->dataMapper);
        $dati->soggetto = $this->azienda;
        $dati->validoDal = new SismaDate('2020-01-01');
        $dati->partitaIva = '01234560785';
        $dati->regimeFiscale = RegimeFiscale::ordinario;
        $dati->periodicitaIva = PeriodicitaIva::mensile;
        return $dati;
    }

    private function attivita(int $id, int $numero, ?PeriodicitaIva $periodicita = null): AttivitaIva
    {
        $attivita = new AttivitaIva($this->dataMapper);
        $attivita->id = $id;
        $attivita->studio = $this->studioPredefinito;
        $attivita->soggetto = $this->azienda;
        $attivita->numero = $numero;
        $attivita->descrizione = 'Attività ' . $numero;
        $attivita->periodicita = $periodicita;
        $attivita->dalAnno = 2026;
        return $attivita;
    }

    private function registro(int $id, TipoRegistroIva $tipo, string $sezionale, ?AttivitaIva $attivita): RegistroIva
    {
        $registro = new RegistroIva($this->dataMapper);
        $registro->id = $id;
        $registro->studio = $this->studioPredefinito;
        $registro->soggetto = $this->azienda;
        $registro->tipo = $tipo;
        $registro->sezionale = $sezionale;
        $registro->descrizione = 'Registro ' . $sezionale;
        $registro->attivitaIva = $attivita;
        return $registro;
    }

    public function testRipartizioneDegliAcquistiPromiscui(): void
    {
        $this->assertSame([1 => 60.0, 2 => 40.0], RigaIva::leggiRipartizione('2:40;1:60;x:3;4:0'));
        $this->assertSame('1:60;2:40.5', RigaIva::scriviRipartizione([2 => 40.5, 1 => 60.0, 3 => 0.0]));
        $this->assertNull(RigaIva::scriviRipartizione([]));
        $attivita = [1 => $this->attivita(1, 1), 2 => $this->attivita(2, 2)];
        $this->assertNull(AttivitaSeparate::verificaRipartizione([1 => 70.0, 2 => 30.0], $attivita));
        $this->assertNull(AttivitaSeparate::verificaRipartizione([], $attivita));
        $this->assertStringContainsString('sommare 100', AttivitaSeparate::verificaRipartizione([1 => 70.0, 2 => 20.0], $attivita));
        $this->assertStringContainsString('n. 3', AttivitaSeparate::verificaRipartizione([1 => 70.0, 3 => 30.0], $attivita));
    }

    public function testLiquidazionePerAttivitaConPromiscuiETrimestraleAnticipata(): void
    {
        $ingrosso = $this->attivita(1, 1);
        $negozio = $this->attivita(2, 2, PeriodicitaIva::trimestrale);
        $promiscui = $this->registro(20, TipoRegistroIva::acquisti, 'PR', null);
        $this->registri['V']->attivitaIva = $ingrosso;
        $this->registri['A']->attivitaIva = $ingrosso;
        $venditeNegozio = $this->registro(21, TipoRegistroIva::vendite, 'NG', $negozio);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2026-03-10');
        $vendita->id = 1;
        $negozioMarzo = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2026-02-10');
        $negozioMarzo->id = 2;
        $negozioMarzo->registroIva = $venditeNegozio;
        $affitto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2026-03-05');
        $affitto->id = 3;
        $affitto->registroIva = $promiscui;
        $rigaAffitto = $this->creaRigaIva('22', 1000.0, 1, null, $affitto);
        $rigaAffitto->ripartizione = '1:70;2:30';
        $nonRipartito = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2026-03-06');
        $nonRipartito->id = 4;
        $nonRipartito->registroIva = $promiscui;
        $righe = [$this->creaRigaIva('22', 2000.0, 1, null, $vendita), $this->creaRigaIva('22', 1000.0, 1, null, $negozioMarzo), $rigaAffitto,
            $this->creaRigaIva('22', 500.0, 1, null, $nonRipartito)];
        $tributo = new CodiceTributo($this->dataMapper);
        $tributo->id = 1;
        $tributo->sezione = SezioneCodiceTributo::erario;
        $tributo->codice = '6003';
        $tributo->descrizione = 'IVA marzo';
        $this->rispondiConti(['riga_iva' => $righe, 'attivita_iva' => [$ingrosso, $negozio], 'dati_fiscali' => [$this->creaDatiFiscali()], 'codice_tributo' => [$tributo]]);
        $calcolo = new CalcoloLiquidazioneIva($this->dataMapper);
        $risultato = $calcolo->calcola($this->azienda, new SismaDate('2026-03-01'), new SismaDate('2026-03-31'), $ingrosso);
        // 22% di 2.000 a debito, 70% dell'IVA dell'affitto in detrazione; l'acquisto non ripartito è escluso con l'avviso
        $this->assertSame(440.0, $risultato->ivaEsigibile);
        $this->assertSame(154.0, $risultato->ivaDetraibile);
        $this->assertStringContainsString('senza ripartizione', implode(' ', $risultato->avvisi));
        $trimestre = $calcolo->calcola($this->azienda, new SismaDate('2026-01-01'), new SismaDate('2026-03-31'), $negozio);
        $this->assertSame(220.0, $trimestre->ivaEsigibile);
        $this->assertSame(66.0, $trimestre->ivaDetraibile);
        $liquidazione = (new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->azienda, 2026, 3, 0.0, false);
        $this->assertSame(PeriodicitaIva::mensile, $liquidazione->periodicita);
        $this->assertSame(1, $liquidazione->trimestreAnticipato);
        // marzo dell'ingrosso (440 - 154) e primo trimestre del negozio (220 - 66) con l'1% sul suo debito
        $this->assertSame(660.0, $liquidazione->ivaEsigibile);
        $this->assertSame(220.0, $liquidazione->ivaDetraibile);
        $this->assertSame(1.54, $liquidazione->interessi);
        $this->assertSame(441.54, $liquidazione->importoDovuto);
        $febbraio = (new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->azienda, 2026, 2, 0.0, false);
        $this->assertNull($febbraio->trimestreAnticipato, 'il negozio trimestrale non si liquida nei primi due mesi del trimestre');
    }

    public function testPeriodoDiControlloDelleControllate(): void
    {
        $gruppo = new GruppoIva($this->dataMapper);
        $gruppo->controllante = $this->azienda;
        $gruppo->annoInizio = 2025;
        $gruppo->annoRevoca = 2028;
        $partecipante = new PartecipanteGruppoIva($this->dataMapper);
        $partecipante->gruppoIva = $gruppo;
        $partecipante->soggetto = $this->azienda;
        $partecipante->annoIngresso = 2026;
        $this->assertFalse($partecipante->partecipaNel(2025));
        $this->assertTrue($partecipante->partecipaNel(2027));
        $this->assertFalse($partecipante->partecipaNel(2028), 'revoca del gruppo');
        $this->assertSame(12, $partecipante->getUltimoMese(2026, PeriodicitaIva::mensile));
        // controllo cessato a giugno: mensile fino a maggio, trimestrale fino al primo trimestre
        $partecipante->dataCessazione = new SismaDate('2026-06-15');
        $this->assertSame(5, $partecipante->getUltimoMese(2026, PeriodicitaIva::mensile));
        $this->assertSame(3, $partecipante->getUltimoMese(2026, PeriodicitaIva::trimestrale));
        $this->assertTrue($partecipante->isPeriodoControllato(2026, PeriodicitaIva::mensile, 5));
        $this->assertFalse($partecipante->isPeriodoControllato(2026, PeriodicitaIva::mensile, 6));
        $this->assertTrue($partecipante->isPeriodoControllato(2026, PeriodicitaIva::trimestrale, 1));
        $this->assertFalse($partecipante->isPeriodoControllato(2026, PeriodicitaIva::trimestrale, 2));
        $this->assertFalse($partecipante->partecipaNel(2027), 'dopo la cessazione non partecipa più');
    }

    public function testLiquidazioneTrasferitaAlGruppo(): void
    {
        $gruppo = new GruppoIva($this->dataMapper);
        $gruppo->id = 1;
        $gruppo->studio = $this->studioPredefinito;
        $gruppo->controllante = $this->azienda;
        $gruppo->annoInizio = 2026;
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2026-03-10');
        $vendita->id = 1;
        $precedente = new LiquidazioneIva($this->dataMapper);
        $precedente->periodicita = PeriodicitaIva::mensile;
        $precedente->anno = 2026;
        $precedente->periodo = 2;
        $precedente->creditoFinale = 500.0;
        $precedente->gruppoIva = $gruppo;
        $this->rispondiConti(['riga_iva' => [$this->creaRigaIva('22', 300.0, 1, null, $vendita)], 'gruppo_iva' => [$gruppo], 'dati_fiscali' => [$this->creaDatiFiscali()],
            'liquidazione_iva' => [$precedente]]);
        $liquidazione = (new ElaborazioneLiquidazione($this->dataMapper))->elabora($this->azienda, 2026, 3, 200.0, false);
        // credito del periodo precedente già trasferito, credito annuale non compensabile, debito di 66 euro trasferito senza rinvio
        $this->assertSame($gruppo, $liquidazione->gruppoIva);
        $this->assertSame(0.0, $liquidazione->creditoPrecedente);
        $this->assertSame(0.0, $liquidazione->creditoAnnoPrecedente);
        $this->assertSame(66.0, $liquidazione->importoDovuto);
        $this->assertSame(0.0, $liquidazione->debitoRinviato);
        $this->assertSame(66.0, $liquidazione->getSaldoTrasferito());
        $this->assertStringContainsString('trasferito alla controllante', (string) $liquidazione->avvisi);
    }

    public function testImportiDellaLiquidazioneDiGruppo(): void
    {
        $servizio = new LiquidazioneGruppo($this->dataMapper);
        $liquidazione = new LiquidazioneGruppoIva($this->dataMapper);
        $liquidazione->anno = 2026;
        $liquidazione->periodo = 3;
        $liquidazione->periodicita = PeriodicitaIva::mensile;
        $liquidazione->debitiTrasferiti = 1320.0;
        $liquidazione->creditiTrasferiti = 3960.0;
        $servizio->determinaImporti($liquidazione);
        $this->assertSame(2640.0, $liquidazione->creditoFinale);
        $liquidazione->creditoPrecedente = 0.0;
        $liquidazione->creditiTrasferiti = 1250.0;
        $servizio->determinaImporti($liquidazione);
        $this->assertSame(70.0, $liquidazione->debitoRinviato, 'debito del gruppo non superiore a 100 euro rinviato');
        $liquidazione->periodo = 12;
        $liquidazione->acconto = 50.0;
        $servizio->determinaImporti($liquidazione);
        $this->assertSame(20.0, $liquidazione->importoDovuto);
        $this->assertSame('iva-gruppo-2026-3', GruppoIva::getRiferimento(2026, 3));
    }
}
