<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Entities\GaranziaRimborso;
use Dichiarativi\Application\Enumerations\TipoGaranzia;
use Dichiarativi\Application\Services\ScadenzeModulo;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Archivio delle garanzie dei rimborsi IVA (art. 38-bis, c. 5): scadenza di
 * legge e scadenzario dello studio.
 */
class GaranziaRimborsoTest extends DichiarativiTestCase
{

    private function creaGaranzia(int $id, int $anno, ?string $rimborso, ?string $scadenza = null, ?string $svincolo = null): GaranziaRimborso
    {
        $garanzia = new GaranziaRimborso($this->dataMapper);
        $garanzia->id = $id;
        $garanzia->studio = $this->studioPredefinito;
        $garanzia->soggetto = $this->azienda;
        $garanzia->annoImposta = $anno;
        $garanzia->tipo = TipoGaranzia::polizzaAssicurativa;
        $garanzia->importo = 35000.0;
        $garanzia->dataRilascio = new SismaDate(($anno + 1) . '-02-01');
        $garanzia->dataRimborso = $rimborso === null ? null : new SismaDate($rimborso);
        $garanzia->dataScadenza = $scadenza === null ? null : new SismaDate($scadenza);
        $garanzia->dataSvincolo = $svincolo === null ? null : new SismaDate($svincolo);
        return $garanzia;
    }

    public function testScadenzaDiTreAnniDalRimborso(): void
    {
        $this->assertSame('2029-07-15', $this->creaGaranzia(1, 2025, '2026-07-15')->getScadenzaProposta()->format('Y-m-d'));
    }

    public function testScadenzaLimitataDallaDecadenza(): void
    {
        // dichiarazione 2025 presentata nel 2026: accertamento fino al 31/12/2031
        $this->assertSame('2031-12-31', $this->creaGaranzia(1, 2025, '2029-06-30')->getScadenzaProposta()->format('Y-m-d'));
    }

    public function testDifferimentoDellaDecadenza(): void
    {
        $garanzia = $this->creaGaranzia(1, 2025, '2029-06-30');
        $garanzia->dataRichiestaDocumenti = new SismaDate('2026-05-04');
        // entro i quindici giorni la decadenza non si sposta
        $garanzia->dataConsegnaDocumenti = new SismaDate('2026-05-19');
        $this->assertSame(0, $garanzia->getGiorniDifferimento());
        // consegna al sedicesimo giorno: un giorno
        $garanzia->dataConsegnaDocumenti = new SismaDate('2026-05-20');
        $this->assertSame(1, $garanzia->getGiorniDifferimento());
        $garanzia->dataConsegnaDocumenti = new SismaDate('2026-06-01');
        $this->assertSame(13, $garanzia->getGiorniDifferimento());
        $this->assertSame('2032-01-13', $garanzia->getScadenzaProposta()->format('Y-m-d'));
    }

    public function testDifferimentoNonSuperaITreAnni(): void
    {
        $garanzia = $this->creaGaranzia(1, 2025, '2026-07-15');
        $garanzia->dataRichiestaDocumenti = new SismaDate('2026-05-04');
        $garanzia->dataConsegnaDocumenti = new SismaDate('2026-06-01');
        $this->assertSame('2029-07-15', $garanzia->getScadenzaProposta()->format('Y-m-d'));
    }

    public function testScadenzaNonNotaSenzaRimborso(): void
    {
        $this->assertNull($this->creaGaranzia(1, 2025, null)->getScadenzaProposta());
    }

    public function testCompensazioneDiGruppo(): void
    {
        // la durata decorre dalla presentazione della dichiarazione, indicata nella stessa data
        $garanzia = $this->creaGaranzia(1, 2025, '2026-09-30');
        $garanzia->compensazioneGruppo = true;
        $this->assertSame('2029-09-30', $garanzia->getScadenzaProposta()->format('Y-m-d'));
    }

    public function testTipiDiGaranzia(): void
    {
        foreach (TipoGaranzia::cases() as $tipo) {
            $this->assertNotSame('', $tipo->getLabel());
        }
        $this->assertCount(6, TipoGaranzia::cases());
    }

    public function testScadenzario(): void
    {
        $garanzie = [
            $this->creaGaranzia(1, 2025, '2026-07-15', '2029-07-15'),
            $this->creaGaranzia(2, 2024, '2025-03-20', '2029-03-20', '2029-02-01'),
            $this->creaGaranzia(3, 2023, '2024-03-20', '2031-01-10'),
            $this->creaGaranzia(4, 2025, null),
        ];
        $this->testAdapter->impostaSelectCallback(function (string $cmd) use ($garanzie): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            return new TestResultSet(str_contains($cmd, 'FROM `garanzia_rimborso`') ? $garanzie : []);
        });
        $scadenze = (new ScadenzeModulo($this->dataMapper))->getScadenze($this->studioPredefinito, new SismaDate('2029-01-01'), new SismaDate('2029-12-31'));
        $this->assertSame(['garanzia-1', 'garanzia-2'], array_map(fn($scadenza) => $scadenza->riferimento, $scadenze));
        $this->assertFalse($scadenze[0]->completata);
        $this->assertTrue($scadenze[1]->completata);
        $this->assertSame('2029-07-15', $scadenze[0]->data->format('Y-m-d'));
    }
}
