<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\Abbinamento;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\TipoChiusura;
use Contabilita\Application\Enumerations\TipoPartita;
use Contabilita\Application\Services\CalcoloLiquidazioneIva;
use Contabilita\Application\Services\ControlloRegistrazione;
use Contabilita\Application\Services\IvaSospesa;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * IVA a esigibilità differita e per cassa: quota maturata con i pagamenti
 * abbinati, limite di un anno dell'IVA per cassa, liquidazione del periodo
 * ed esclusioni.
 */
class IvaSospesaTest extends ContabilitaTestCase
{

    /** @var Partita[] */
    private array $partite = [];

    /** @var Abbinamento[] */
    private array $abbinamenti = [];

    private function documento(int $id, CausaleContabile $causale, EsigibilitaIva $esigibilita, string $data, float $totale): RegistrazioneContabile
    {
        $beta = $this->creaControparte(9, 'Beta', $this->conti['06.01.0001'], $this->conti['13.01.0001']);
        $registrazione = $this->creaRegistrazione($causale, $beta, $data);
        $registrazione->id = $id;
        $registrazione->esigibilita = $esigibilita;
        $partita = new Partita($this->dataMapper);
        $partita->id = $id * 10;
        $partita->soggetto = $this->azienda;
        $partita->controparte = $beta;
        $partita->conto = $this->conti['06.01.0001'];
        $partita->registrazioneContabile = $registrazione;
        $partita->tipo = TipoPartita::documento;
        $partita->data = new SismaDate($data);
        $partita->importo = $totale;
        $this->partite[$id] = $partita;
        return $registrazione;
    }

    private function paga(int $documento, TipoChiusura $tipo, string $data, float $importo, ?Partita $collegata = null): void
    {
        $abbinamento = new Abbinamento($this->dataMapper);
        $abbinamento->id = count($this->abbinamenti) + 1;
        $abbinamento->soggetto = $this->azienda;
        $abbinamento->controparte = $this->partite[$documento]->controparte;
        $abbinamento->partita = $this->partite[$documento];
        $abbinamento->registrazioneContabile = $this->creaRegistrazione(CausaleContabile::incasso, null, $data);
        $abbinamento->data = new SismaDate($data);
        $abbinamento->tipo = $tipo;
        $abbinamento->importo = $importo;
        $abbinamento->partitaCollegata = $collegata;
        $this->abbinamenti[] = $abbinamento;
    }

    private function rispondi(array $altre = []): void
    {
        $this->rispondiConti(['partita' => array_values($this->partite), 'abbinamento' => $this->abbinamenti] + $altre);
    }

    public function testQuoteMaturateConIPagamenti(): void
    {
        $differita = $this->documento(1, CausaleContabile::fatturaVendita, EsigibilitaIva::differita, '2026-02-10', 1220.0);
        $this->paga(1, TipoChiusura::denaro, '2026-03-15', 610.0);
        $this->paga(1, TipoChiusura::abbuono, '2026-04-10', 10.0);
        $this->paga(1, TipoChiusura::denaro, '2027-05-20', 600.0);
        $this->rispondi();
        $sospesa = new IvaSospesa($this->dataMapper);
        $this->assertSame(0.0, $sospesa->getQuota($differita, new \DateTimeImmutable('2026-02-28')));
        $this->assertSame(0.5, $sospesa->getQuota($differita, new \DateTimeImmutable('2026-03-31')));
        $this->assertSame(0.5, $sospesa->getQuota($differita, new \DateTimeImmutable('2026-12-31')), 'l\'abbuono non è un pagamento; la differita non ha il limite di un anno');
        $this->assertSame(round(1210 / 1220, 6), $sospesa->getQuota($differita, new \DateTimeImmutable('2027-05-31')));
        $this->assertNull(IvaSospesa::getDataLimite($differita));
    }

    public function testIvaPerCassaComunqueDopoUnAnno(): void
    {
        $vendita = $this->documento(2, CausaleContabile::fatturaVendita, EsigibilitaIva::perCassa, '2025-03-10', 1000.0);
        $acconto = new Partita($this->dataMapper);
        $acconto->id = 99;
        $acconto->data = new SismaDate('2025-01-15');
        // acconto incassato prima del documento: matura con il documento, non prima
        $this->paga(2, TipoChiusura::acconto, '2025-03-20', 250.0, $acconto);
        $this->rispondi();
        $sospesa = new IvaSospesa($this->dataMapper);
        $this->assertSame(0.0, $sospesa->getQuota($vendita, new \DateTimeImmutable('2025-02-28')));
        $this->assertSame(0.25, $sospesa->getQuota($vendita, new \DateTimeImmutable('2025-03-31')));
        $this->assertSame('2026-03-10', IvaSospesa::getDataLimite($vendita)->format('Y-m-d'));
        $this->assertSame(0.25, $sospesa->getQuota($vendita, new \DateTimeImmutable('2026-03-09')));
        $this->assertSame(1.0, $sospesa->getQuota($vendita, new \DateTimeImmutable('2026-03-10')), 'decorso un anno dall\'operazione');

        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto);
        $acquisto->esigibilita = EsigibilitaIva::perCassa;
        $this->assertTrue(IvaSospesa::isSospesa($acquisto));
        $acquisto->esigibilita = EsigibilitaIva::differita;
        $this->assertFalse(IvaSospesa::isSospesa($acquisto), 'la differita del fornitore non sposta la detrazione del cessionario');
        $nota = $this->creaRegistrazione(CausaleContabile::notaCreditoEmessa);
        $nota->esigibilita = EsigibilitaIva::perCassa;
        $this->assertFalse(IvaSospesa::isSospesa($nota));
        $this->assertSame('D', EsigibilitaIva::perCassa->getValoreFattura());
    }

    public function testLiquidazioneDelPeriodo(): void
    {
        // fattura per cassa di febbraio, metà incassata a febbraio: imponibile nel riepilogo, metà dell'imposta esigibile
        $vendita = $this->documento(3, CausaleContabile::fatturaVendita, EsigibilitaIva::perCassa, '2026-02-10', 1220.0);
        $this->paga(3, TipoChiusura::denaro, '2026-02-20', 610.0);
        $riga = $this->creaRigaIva('22', 1000.0, 1, null, $vendita);
        $this->rispondi(['riga_iva' => [$riga], 'registrazione_contabile' => [$vendita]]);
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($this->azienda, new SismaDate('2026-02-01'), new SismaDate('2026-02-28'));
        $this->assertSame(110.0, $risultato->ivaEsigibile);
        $this->assertCount(1, $risultato->riepilogo);
        $this->assertSame(1000.0, array_values($risultato->riepilogo)[0]['imponibile']);
        $this->assertCount(1, $risultato->sospese);
        $this->assertSame(0.5, $risultato->sospese[0]['quota']);
        $this->assertSame(110.0, $risultato->sospese[0]['esigibile']);
        $this->assertFalse($risultato->sospese[0]['decorsoAnno']);
    }

    public function testEsclusioniDellIvaPerCassa(): void
    {
        $controllo = new ControlloRegistrazione();
        $gamma = $this->creaControparte(7, 'Gamma', null, $this->conti['13.01.0001']);
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, $gamma);
        $acquisto->esigibilita = EsigibilitaIva::perCassa;
        $this->assertArrayNotHasKey('esigibilita', $controllo->verifica($this->piano, $acquisto, [$this->creaRigaIva('22', 100.0)], []));
        $this->assertArrayHasKey('esigibilita', $controllo->verifica($this->piano, $acquisto, [$this->creaRigaIva('RC22', 100.0)], []), 'inversione contabile');
        $acquisto->importazione = true;
        $this->assertArrayHasKey('esigibilita', $controllo->verifica($this->piano, $acquisto, [$this->creaRigaIva('22', 100.0)], []), 'importazione');
        $nota = $this->creaRegistrazione(CausaleContabile::notaCreditoRicevuta, $gamma);
        $nota->esigibilita = EsigibilitaIva::perCassa;
        $this->assertArrayHasKey('esigibilita', $controllo->verifica($this->piano, $nota, [$this->creaRigaIva('22', -100.0)], []));
    }
}
