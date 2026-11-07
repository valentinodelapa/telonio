<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use Dichiarativi\Application\Enumerations\TipoModulo;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Iva\PropostaGruppoIva;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Telonio\Tests\TestResultSet;

/**
 * Moduli delle attività separate e IVA di gruppo nella dichiarazione IVA:
 * righi VX1, VX2, VX7 e VX8 del paragrafo 2.9.1 delle specifiche, quadro
 * VW del prospetto IVA 26 PR, debiti e crediti trasferiti.
 */
class AttivitaSeparateEGruppoIvaTest extends DichiarativiTestCase
{

    private function liquidazione(PeriodicitaIva $periodicita, int $periodo, float $dovuto, float $credito, float $interessi = 0.0, bool $trasferita = true): LiquidazioneIva
    {
        $liquidazione = new LiquidazioneIva($this->dataMapper);
        $liquidazione->periodicita = $periodicita;
        $liquidazione->anno = 2025;
        $liquidazione->periodo = $periodo;
        $liquidazione->importoDovuto = $dovuto;
        $liquidazione->creditoFinale = $credito;
        $liquidazione->interessi = $interessi;
        if ($trasferita) {
            $gruppo = new GruppoIva($this->dataMapper);
            $gruppo->controllante = $this->azienda;
            $gruppo->annoInizio = 2025;
            $liquidazione->gruppoIva = $gruppo;
        }
        return $liquidazione;
    }

    public function testTrasferimentiSenzaQuartoTrimestre(): void
    {
        $trasferimenti = PropostaGruppoIva::getTrasferimenti([$this->liquidazione(PeriodicitaIva::trimestrale, 1, 0.0, 3300.0),
                    $this->liquidazione(PeriodicitaIva::trimestrale, 2, 3333.0, 0.0, 33.0), $this->liquidazione(PeriodicitaIva::trimestrale, 4, 3333.0, 0.0, 33.0),
                    $this->liquidazione(PeriodicitaIva::trimestrale, 3, 500.0, 0.0, 0.0, false)]);
        $this->assertSame(['debiti' => 3333.0, 'crediti' => 3300.0, 'interessi' => 33.0], $trasferimenti);
        $this->assertTrue(PropostaGruppoIva::isVersamentoGruppo('iva-gruppo-2025-3'));
        $this->assertTrue(PropostaGruppoIva::isVersamentoGruppo('acconto-iva-2025-gruppo'));
        $this->assertFalse(PropostaGruppoIva::isVersamentoGruppo('iva-2025-3'));
        // righi dei quadri VG (3 per sezione) e VS (12) nei quadri successivi
        $this->assertSame([1, 2], PropostaGruppoIva::getPosizione(0, 3, 2));
        $this->assertSame([2, 2], PropostaGruppoIva::getPosizione(3, 3, 2));
        $this->assertSame([2, 6], PropostaGruppoIva::getPosizione(4, 3, 5));
        $this->assertSame([1, 12], PropostaGruppoIva::getPosizione(11, 12, 1));
        $this->assertSame([2, 1], PropostaGruppoIva::getPosizione(12, 12, 1));
    }

    public function testImposteDelQuadroVxConIlGruppo(): void
    {
        $quadri = $this->creaQuadri();
        $quadri->imposta('VL038001', 3333);
        $this->assertSame([3333, 0], PropostaIva::calcolaImposteVx($quadri, [1]));
        // società del gruppo per tutto l'anno: il risultato va al gruppo, restano i crediti non trasferibili
        $quadri->imposta('VK001001', '09222330301');
        $quadri->imposta('VK001002', 12);
        $quadri->imposta('VL010001', 500);
        $this->assertSame([-500, 3333], PropostaIva::calcolaImposteVx($quadri, [1]));
        // modulo di un'incorporata esterna al gruppo: il suo risultato resta della società
        $quadri->imposta('VA001001', '01234560785', 2);
        $quadri->imposta('VL039001', 200, 2);
        $this->assertSame([-700, 3333], PropostaIva::calcolaImposteVx($quadri, [1, 2]));
        // controllo cessato in corso d'anno: niente VX7 e VX8
        $quadri->imposta('VK001002', 5);
        $this->assertSame([3133, 0], PropostaIva::calcolaImposteVx($quadri, [1, 2]));
        $quadri->imposta('VK001002', 12);
        $quadri->imposta('VX002001', 700);
        $quadri->imposta('VX005001', 700);
        $quadri->imposta('VX007001', 3333);
        $quadri->imposta('VK021001', 3333);
        $quadri->imposta('VK022001', 3333);
        $segnalazioni = (new ControlliIva($this->dataMapper))->getSegnalazioni($quadri);
        $this->assertEmpty(array_filter($segnalazioni, fn(string $segnalazione) => str_starts_with($segnalazione, 'VX') || str_starts_with($segnalazione, 'VK')));
        $quadri->imposta('VX007001', 3000);
        $this->assertContains('VX007001: indicato 3.000, risulta 3.333.', (new ControlliIva($this->dataMapper))->getSegnalazioni($quadri));
    }

    public function testLiquidazioneAnnualeDelGruppo(): void
    {
        $quadri = $this->creaQuadri();
        foreach (['VW001001' => 70400, 'VW002001' => 50600, 'VW003001' => 19800, 'VW023001' => 66, 'VW030002' => 18546, 'VW030003' => 18546, 'VW030001' => 18546, 'VW036001' => 33] as $codice => $valore) {
            $quadri->imposta($codice, $valore);
        }
        PropostaGruppoIva::calcolaVw($quadri);
        // 19.800 + 66 di interessi periodici - 18.546 versati, più 33 di interessi del quarto trimestre
        $this->assertSame(1320, $quadri->getImporto('VW032001'));
        $this->assertSame(1353, $quadri->getImporto('VW038001'));
        $this->assertSame(0, $quadri->getImporto('VW039001'));
        $quadri->imposta('VW025001', 5000);
        PropostaGruppoIva::calcolaVw($quadri);
        $this->assertSame(0, $quadri->getImporto('VW032001'));
        $this->assertSame(3680, $quadri->getImporto('VW033001'));
        $this->assertSame(3647, $quadri->getImporto('VW039001'));
    }

    public function testModuliDelleAttivitaSeparate(): void
    {
        $ingrosso = new AttivitaIva($this->dataMapper);
        $ingrosso->id = 1;
        $ingrosso->soggetto = $this->azienda;
        $ingrosso->numero = 1;
        $ingrosso->descrizione = 'Ingrosso';
        $ingrosso->dalAnno = 2025;
        $negozio = new AttivitaIva($this->dataMapper);
        $negozio->id = 2;
        $negozio->soggetto = $this->azienda;
        $negozio->numero = 2;
        $negozio->descrizione = 'Negozio';
        $negozio->dalAnno = 2025;
        $this->registri['V']->attivitaIva = $ingrosso;
        $this->registri['A']->attivitaIva = $ingrosso;
        $venditeNegozio = new RegistroIva($this->dataMapper);
        $venditeNegozio->id = 21;
        $venditeNegozio->soggetto = $this->azienda;
        $venditeNegozio->tipo = TipoRegistroIva::vendite;
        $venditeNegozio->sezionale = 'NG';
        $venditeNegozio->descrizione = 'Negozio';
        $venditeNegozio->attivitaIva = $negozio;
        $promiscui = new RegistroIva($this->dataMapper);
        $promiscui->id = 22;
        $promiscui->soggetto = $this->azienda;
        $promiscui->tipo = TipoRegistroIva::acquisti;
        $promiscui->sezionale = 'PR';
        $promiscui->descrizione = 'Promiscui';
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $vendita->id = 1;
        $scontrino = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-12');
        $scontrino->id = 2;
        $scontrino->registroIva = $venditeNegozio;
        $affitto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-03-05');
        $affitto->id = 3;
        $affitto->registroIva = $promiscui;
        $rigaAffitto = $this->creaRigaIva('22', 1000.0, 1, null, $affitto);
        $rigaAffitto->ripartizione = '1:70;2:30';
        $this->testAdapter->impostaSelectCallback(function (string $cmd) use ($vendita, $scontrino, $rigaAffitto, $ingrosso, $negozio): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            $tabelle = [
                'dati_fiscali' => [$this->creaDatiFiscali()],
                'attivita_iva' => [$ingrosso, $negozio],
                'riga_iva' => [$this->creaRigaIva('22', 10000.0, 1, null, $vendita), $this->creaRigaIva('22', 3000.0, 1, null, $scontrino), $rigaAffitto],
            ];
            return new TestResultSet(preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1 ? ($tabelle[$tabella[1]] ?? []) : []);
        });
        $modulo = new ModuloDichiarazione($this->dataMapper);
        $modulo->numero = 2;
        $modulo->tipo = TipoModulo::attivitaSeparata;
        $modulo->attivitaIva = $negozio;
        $quadri = $this->creaQuadri();
        (new PropostaIva($this->dataMapper))->proponi($this->creaDichiarazione(), $quadri, [$modulo]);
        // operazioni dei registri dell'attività e quota dell'acquisto promiscuo
        $this->assertSame(10000, $quadri->getImporto('VE023001', 1));
        $this->assertSame(3000, $quadri->getImporto('VE023001', 2));
        $this->assertSame(154, $quadri->getImporto('VF027002', 1));
        $this->assertSame(66, $quadri->getImporto('VF027002', 2));
        // sezione 1 del VL per modulo, risultato nel modulo 1
        $this->assertSame(594, $quadri->getImporto('VL003001', 2));
        $this->assertSame(2046 + 594, $quadri->getImporto('VL032001', 1));
        $this->assertNull($quadri->get('VL030001', 2));
        $this->assertSame(2640, $quadri->getImporto('VX001001', 1));
    }
}
