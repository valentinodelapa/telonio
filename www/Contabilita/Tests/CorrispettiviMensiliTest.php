<?php

namespace Contabilita\Tests;

use Contabilita\Application\Controllers\CorrispettiviController;
use Contabilita\Application\Entities\CorrispettivoGiornaliero;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\CorrispettiviMensili;
use SismaFramework\Orm\CustomTypes\SismaDate;

class CorrispettiviMensiliTest extends ContabilitaTestCase
{

    private function codiciPerId(): array
    {
        $codici = [];
        foreach ($this->codiciIva as $codice) {
            $codici[$codice->id] = $codice;
        }
        return $codici;
    }

    private function inserimenti(string $tabella): array
    {
        return array_values(array_filter($this->testAdapter->queryEseguite, fn(array $query) => str_starts_with($query['cmd'], 'INSERT') && str_contains($query['cmd'], '`' . $tabella . '`')));
    }

    public function testScorporoDelTotaleMensile(): void
    {
        $this->assertSame([1000.0, 220.0], CorrispettiviMensili::scorpora($this->codiciIva['22'], 1220.0));
        $this->assertSame([90.91, 9.09], CorrispettiviMensili::scorpora($this->codiciIva['10'], 100.0));
        $this->assertSame([50.0, 0.0], CorrispettiviMensili::scorpora($this->codiciIva['N4'], 50.0));
        $this->assertSame('corrispettivi-3-2026-02', CorrispettiviMensili::getRiferimento($this->registri['C'], 2026, 2));
        $this->assertSame('2026-02-28', CorrispettiviMensili::getFineMese(2026, 2)->format('Y-m-d'));
    }

    public function testRegistrazioneUnicaDelMese(): void
    {
        $this->rispondiConti(['esercizio_contabile' => [$this->esercizio], 'piano_conti' => [$this->piano]]);
        $importi = ['2026-02-02' => [1 => 610.0, 2 => 55.0], '2026-02-03' => [1 => 610.0], '2026-02-04' => [1 => 0.0, 29 => 20.0]];
        $registrazione = (new CorrispettiviMensili($this->dataMapper))->registra($this->registri['C'], 2026, 2, $importi, $this->codiciPerId());
        $this->assertSame(CausaleContabile::corrispettivi, $registrazione->causale);
        $this->assertSame('2026-02-28', $registrazione->dataRegistrazione->format('Y-m-d'));
        $this->assertSame('2026-02-28', $registrazione->dataCompetenzaIva->format('Y-m-d'));
        $this->assertSame(1295.0, $registrazione->totaleDocumento);
        $this->assertSame(CorrispettiviMensili::ORIGINE, $registrazione->origine);
        $this->assertSame(1, $registrazione->protocolloIva);
        $righeIva = $this->inserimenti('riga_iva');
        $this->assertCount(3, $righeIva, 'una riga IVA per codice, non per giorno');
        $this->assertContains(1000.0, $righeIva[0]['bindValues']);
        $this->assertContains(220.0, $righeIva[0]['bindValues'], 'imposta scorporata dal totale del mese (1.220,00)');
        $this->assertCount(4, $this->inserimenti('corrispettivo_giornaliero'), 'dettaglio giornaliero senza gli importi zero');
        $this->assertCount(3, $this->inserimenti('riga_contabile'), 'cassa, ricavi, IVA sulle vendite');
    }

    public function testControlli(): void
    {
        $this->rispondiConti(['esercizio_contabile' => [$this->esercizio], 'piano_conti' => [$this->piano]]);
        $servizio = new CorrispettiviMensili($this->dataMapper);
        foreach ([
            [$this->registri['V'], ['2026-02-02' => [1 => 10.0]], 'registro dei corrispettivi'],
            [$this->registri['C'], ['2026-03-01' => [1 => 10.0]], 'fuori dal mese'],
            [$this->registri['C'], ['2026-02-02' => [999 => 10.0]], 'non disponibile'],
            [$this->registri['C'], ['2026-02-02' => [60 => 10.0]], 'integrazione'],
            [$this->registri['C'], ['2026-02-02' => [1 => 10.0], '2026-02-03' => [1 => -20.0]], 'negativo'],
        ] as [$registro, $importi, $atteso]) {
            try {
                $servizio->registra($registro, 2026, 2, $importi, $this->codiciPerId());
                $this->fail('Accettato: ' . $atteso);
            } catch (\DomainException $errore) {
                $this->assertStringContainsString($atteso, $errore->getMessage());
            }
        }
        $this->expectExceptionMessage('Nessun esercizio aperto al 31/01/2027');
        $servizio->registra($this->registri['C'], 2027, 1, ['2027-01-05' => [1 => 10.0]], $this->codiciPerId());
    }

    public function testMeseAzzeratoEliminaLaRegistrazione(): void
    {
        $esistente = new RegistrazioneContabile($this->dataMapper);
        $esistente->id = 40;
        $esistente->numero = 12;
        $esistente->esercizioContabile = $this->esercizio;
        $esistente->soggetto = $this->azienda;
        $esistente->causale = \Contabilita\Application\Enumerations\CausaleContabile::corrispettivi;
        $esistente->dataRegistrazione = new SismaDate('2026-02-28');
        $giornaliero = new CorrispettivoGiornaliero($this->dataMapper);
        $giornaliero->id = 7;
        $giornaliero->data = new SismaDate('2026-02-02');
        $giornaliero->codiceIva = $this->codiciIva['22'];
        $giornaliero->importo = 100.0;
        $this->rispondiConti(['registrazione_contabile' => [$esistente], 'corrispettivo_giornaliero' => [$giornaliero]]);
        $servizio = new CorrispettiviMensili($this->dataMapper);
        $this->assertSame(['2026-02-02' => [1 => 100.0]], $servizio->getImporti($servizio->getRegistrazione($this->registri['C'], 2026, 2)));
        $this->assertNull($servizio->registra($this->registri['C'], 2026, 2, ['2026-02-02' => [1 => 0.0]], $this->codiciPerId()));
        $cancellazioni = array_filter($this->testAdapter->queryEseguite, fn(array $query) => str_starts_with($query['cmd'], 'DELETE'));
        $this->assertNotEmpty(array_filter($cancellazioni, fn(array $query) => str_contains($query['cmd'], '`corrispettivo_giornaliero`')));
        $this->assertNotEmpty(array_filter($cancellazioni, fn(array $query) => str_contains($query['cmd'], '`registrazione_contabile`')));
    }

    public function testInputDellaGrigliaEMesePredefinito(): void
    {
        $this->assertSame(['2026-02-02' => [1 => 1234.5, 2 => 10.0]],
                CorrispettiviController::leggiImporti(['2026-02-02' => ['1' => '1.234,50', '2' => '10', '3' => '', 'x' => '5'], '2026-02-03' => 'non valido']));
        $this->assertSame([2026, 9], CorrispettiviController::normalizzaMese(0, 0, new \DateTimeImmutable('2026-10-10')), 'fino al 15 il mese precedente');
        $this->assertSame([2026, 10], CorrispettiviController::normalizzaMese(0, 0, new \DateTimeImmutable('2026-10-16')));
        $this->assertSame([2025, 12], CorrispettiviController::normalizzaMese(0, 13, new \DateTimeImmutable('2026-01-05')));
        $this->assertSame([2026, 3], CorrispettiviController::normalizzaMese(2026, 3));
    }
}
