<?php

namespace Imu\Tests\Services;

use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Enumerations\StatoDichiarazione;
use Imu\Application\Services\ScadenzeModulo;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ScadenzeModuloTest extends SuiteTestCase
{

    public function testDichiarazioniConTermineAlTrentaGiugnoDellAnnoSuccessivo(): void
    {
        $soggetto = $this->creaSoggettoFisico(1, $this->studioTest, 'RSSMRA80A01H501U', 'ROSSI', 'MARIO');
        $roma = $this->creaDichiarazione(1, $soggetto, 2025, 'ROMA', StatoDichiarazione::bozza);
        $milano = $this->creaDichiarazione(2, $soggetto, 2025, 'MILANO', StatoDichiarazione::presentata);
        $this->testAdapter->impostaSelectCallback(fn(string $cmd, array $bind) => new TestResultSet(($bind[1] ?? null) === 2025 ? [$roma, $milano] : []));

        $scadenze = (new ScadenzeModulo($this->dataMapper))->getScadenze($this->studioTest, new SismaDate('2026-06-01'), new SismaDate('2026-07-31'));
        $this->assertCount(2, $scadenze);
        $this->assertSame(ModuloSuite::imu, $scadenze[0]->modulo);
        $this->assertSame('dichiarazione-1', $scadenze[0]->riferimento);
        $this->assertSame('2026-06-30', $scadenze[0]->data->format('Y-m-d'));
        $this->assertSame('Dichiarazione IMU 2025 - ROMA', $scadenze[0]->descrizione);
        $this->assertFalse($scadenze[0]->completata);
        $this->assertTrue($scadenze[1]->completata);

        // fuori dal periodo
        $this->assertSame([], (new ScadenzeModulo($this->dataMapper))->getScadenze($this->studioTest, new SismaDate('2026-07-01'), new SismaDate('2026-12-31'), $soggetto));
    }

    private function creaDichiarazione(int $id, $soggetto, int $anno, string $comune, StatoDichiarazione $stato): DichiarazioneImu
    {
        $dichiarazione = new DichiarazioneImu($this->dataMapper);
        $dichiarazione->id = $id;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->anno = $anno;
        $dichiarazione->codiceComune = 'H501';
        $dichiarazione->denominazioneComune = $comune;
        $dichiarazione->stato = $stato;
        return $dichiarazione;
    }
}
