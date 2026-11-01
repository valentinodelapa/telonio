<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Services\ControlloRegistrazione;
use Contabilita\Application\Services\PropostaRighe;
use SismaFramework\Orm\CustomTypes\SismaDate;

class RegistrazioniTest extends ContabilitaTestCase
{

    /**
     * @param RigaContabile[] $righe
     * @return array<string, float> saldo (dare - avere) per codice di conto
     */
    private static function saldi(array $righe): array
    {
        $saldi = [];
        foreach ($righe as $riga) {
            $saldi[$riga->conto->codice] = round(($saldi[$riga->conto->codice] ?? 0) + $riga->dare - $riga->avere, 2);
        }
        return $saldi;
    }

    public function testPropostaFatturaDiVenditaENotaDiCredito(): void
    {
        $this->rispondiConti();
        $beta = $this->creaControparte(1, 'Beta', $this->conti['06.01.0001'], null);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, $beta);
        $righe = (new PropostaRighe($this->dataMapper))->proponi($this->piano, $vendita, [$this->creaRigaIva('22', 1000.0), $this->creaRigaIva('10', 200.0)]);
        $this->assertSame(['06.01.0001' => 1440.0, '20.01.0001' => -1200.0, '13.04.0001' => -240.0], self::saldi($righe));
        $this->assertSame($beta, $righe[0]->controparte);

        $nota = $this->creaRegistrazione(CausaleContabile::notaCreditoEmessa, $beta);
        $righe = (new PropostaRighe($this->dataMapper))->proponi($this->piano, $nota, [$this->creaRigaIva('22', 100.0, -1)]);
        $this->assertSame(['06.01.0001' => -122.0, '20.01.0001' => 100.0, '13.04.0001' => 22.0], self::saldi($righe));
    }

    public function testPropostaConScissioneDeiPagamenti(): void
    {
        $this->rispondiConti();
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, $this->creaControparte(1, 'Comune', $this->conti['06.01.0001'], null));
        $vendita->esigibilita = EsigibilitaIva::scissionePagamenti;
        $righe = (new PropostaRighe($this->dataMapper))->proponi($this->piano, $vendita, [$this->creaRigaIva('22', 500.0)]);
        $this->assertSame(['06.01.0001' => 500.0, '20.01.0001' => -500.0, '13.04.0001' => 0.0], self::saldi($righe));
        $this->assertCount(5, $righe, 'IVA esposta e stornata in righe distinte');
    }

    public function testPropostaAcquistoConIndetraibileEInversioneContabile(): void
    {
        $this->rispondiConti();
        $gamma = $this->creaControparte(2, 'Gamma', null, $this->conti['13.01.0001']);
        $gamma->contoContropartita = $this->conti['22.01.0001'];
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, $gamma);
        $righeIva = [$this->creaRigaIva('A22', 1000.0), $this->creaRigaIva('RC22', 100.0)];
        $righe = (new PropostaRighe($this->dataMapper))->proponi($this->piano, $acquisto, $righeIva);
        // A22: 1000 + 220, detraibile 88; RC22: fornitore solo per l'imponibile, 22 integrata
        $this->assertSame(['13.01.0001' => -1320.0, '22.01.0001' => 1232.0, '06.03.0002' => 110.0, '13.04.0001' => -22.0], self::saldi($righe));
        $this->assertSame(1320.0, PropostaRighe::totaleDocumento($righeIva));
        $this->assertSame(1342.0, PropostaRighe::totale($righeIva));
    }

    public function testPropostaIncassiPagamentiECorrispettivi(): void
    {
        $this->rispondiConti();
        $incasso = $this->creaRegistrazione(CausaleContabile::incasso, $this->creaControparte(1, 'Beta', $this->conti['06.01.0001'], null));
        $incasso->totaleDocumento = 300.0;
        $this->assertSame(['07.01.0001' => 300.0, '06.01.0001' => -300.0], self::saldi((new PropostaRighe($this->dataMapper))->proponi($this->piano, $incasso, [])));
        $versamento = $this->creaRegistrazione(CausaleContabile::versamentoIva);
        $versamento->totaleDocumento = 414.0;
        $this->assertSame(['06.03.0001' => 414.0, '07.01.0001' => -414.0], self::saldi((new PropostaRighe($this->dataMapper))->proponi($this->piano, $versamento, [])));
        $corrispettivi = $this->creaRegistrazione(CausaleContabile::corrispettivi);
        $this->assertSame(['07.02.0001' => 122.0, '20.01.0001' => -100.0, '13.04.0001' => -22.0],
                self::saldi((new PropostaRighe($this->dataMapper))->proponi($this->piano, $corrispettivi, [$this->creaRigaIva('22', 100.0)])));
        $this->assertSame([], (new PropostaRighe($this->dataMapper))->proponi($this->piano, $this->creaRegistrazione(CausaleContabile::giroconto), []));
    }

    public function testPropostaSenzaControparteOContoDiFunzione(): void
    {
        $this->rispondiConti();
        try {
            (new PropostaRighe($this->dataMapper))->proponi($this->piano, $this->creaRegistrazione(CausaleContabile::fatturaVendita), [$this->creaRigaIva('22', 10.0)]);
            $this->fail('Controparte richiesta');
        } catch (\DomainException $errore) {
            $this->assertStringContainsString('cliente o il fornitore', $errore->getMessage());
        }
        unset($this->conti['BAN']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Banca');
        $pagamento = $this->creaRegistrazione(CausaleContabile::pagamento, $this->creaControparte(2, 'Gamma', null, $this->conti['13.01.0001']));
        $pagamento->totaleDocumento = 10.0;
        (new PropostaRighe($this->dataMapper))->proponi($this->piano, $pagamento, []);
    }

    private function riga(string $codice, float $dare, float $avere): RigaContabile
    {
        $riga = new RigaContabile($this->dataMapper);
        $riga->conto = $this->conti[$codice];
        $riga->dare = $dare;
        $riga->avere = $avere;
        return $riga;
    }

    public function testControlloRegistrazione(): void
    {
        $controllo = new ControlloRegistrazione();
        $beta = $this->creaControparte(1, 'Beta', $this->conti['06.01.0001'], null);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, $beta);
        $vendita->totaleDocumento = 122.0;
        $righe = [$this->riga('06.01.0001', 122, 0), $this->riga('RIC', 0, 100), $this->riga('IVV', 0, 22)];
        $this->assertSame([], $controllo->verifica($this->piano, $vendita, [$this->creaRigaIva('22', 100.0)], $righe));

        $errori = $controllo->verifica($this->piano, $vendita, [$this->creaRigaIva('22', 100.0, 1, 25.0)], [$this->riga('06.01.0001', 122, 0), $this->riga('RIC', 0, 100)]);
        $this->assertStringContainsString('attesa 22,00', $errori['righeIva']);
        $this->assertStringContainsString('non quadra', $errori['righe']);

        $vendita->totaleDocumento = 130.0;
        $this->assertArrayHasKey('totaleDocumento', $controllo->verifica($this->piano, $vendita, [$this->creaRigaIva('22', 100.0)], $righe));

        $nota = $this->creaRegistrazione(CausaleContabile::notaCreditoEmessa, $beta);
        $this->assertStringContainsString('nota di credito', $controllo->verifica($this->piano, $nota, [$this->creaRigaIva('22', 100.0)], $righe)['righeIva']);

        $vendita->totaleDocumento = null;
        $vendita->registroIva = $this->registri['A'];
        $vendita->controparte = null;
        $vendita->dataRegistrazione = new SismaDate('2027-01-05');
        $errori = $controllo->verifica($this->piano, $vendita, [$this->creaRigaIva('RC22', 100.0)], $righe);
        $this->assertArrayHasKey('registroIva', $errori);
        $this->assertArrayHasKey('controparte', $errori);
        $this->assertStringContainsString('non è compresa', $errori['dataRegistrazione']);
        $this->assertStringContainsString('solo per gli acquisti', $errori['righeIva']);

        $this->esercizio->chiuso = true;
        $giroconto = $this->creaRegistrazione(CausaleContabile::giroconto);
        $errori = $controllo->verifica($this->piano, $giroconto, [], [$this->riga('BAN', 10, 0)]);
        $this->assertStringContainsString('chiuso', $errori['dataRegistrazione']);
        $this->assertStringContainsString('almeno due righe', $errori['righe']);
        $this->esercizio->chiuso = false;
        $errori = $controllo->verifica($this->piano, $giroconto, [], [$this->riga('BAN', 10, 5), $this->riga('IVV', 0, 5)]);
        $this->assertStringContainsString('Riga 1', $errori['righe']);
        $errori = $controllo->verifica($this->piano, $giroconto, [], [$this->riga('BAN', 10, 0), $this->riga('CLI', 0, 10)]);
        $this->assertStringContainsString('sottoconto', $errori['righe']);
    }

    public function testCompetenzaIvaDegliAcquisti(): void
    {
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, $this->creaControparte(2, 'Gamma', null, $this->conti['13.01.0001']));
        $acquisto->dataDocumento = new SismaDate('2026-02-20');
        $acquisto->dataCompetenzaIva = new SismaDate('2026-01-31');
        $righe = [$this->riga('COS', 100, 0), $this->riga('IVA', 22, 0), $this->riga('13.01.0001', 0, 122)];
        $this->assertStringContainsString('prima della data del documento', (new ControlloRegistrazione())->verifica($this->piano, $acquisto, [$this->creaRigaIva('22', 100.0)], $righe)['dataCompetenzaIva']);
    }
}
