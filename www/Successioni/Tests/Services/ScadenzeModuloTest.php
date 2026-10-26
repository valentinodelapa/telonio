<?php

namespace Successioni\Tests\Services;

use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Services\ScadenzeModulo;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ScadenzeModuloTest extends SuiteTestCase
{

    public function testTermineDiPresentazioneDodiciMesiDallApertura(): void
    {
        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        // decesso il 14/03/2025: termine 14/03/2026 (sabato) -> lunedì 16/03/2026
        $defunto->dataDecesso = new SismaDate('2025-03-14');
        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->id = 3;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->defunto = $defunto;
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([$dichiarazione]));

        $fonte = new ScadenzeModulo($this->dataMapper);
        $scadenze = $fonte->getScadenze($this->studioTest, new SismaDate('2026-03-01'), new SismaDate('2026-03-31'), $defunto);
        $this->assertCount(1, $scadenze);
        $this->assertSame(ModuloSuite::successioni, $scadenze[0]->modulo);
        $this->assertSame('dichiarazione-3', $scadenze[0]->riferimento);
        $this->assertSame('2026-03-16', $scadenze[0]->data->format('Y-m-d'));
        $this->assertSame($defunto, $scadenze[0]->soggetto);
        $this->assertFalse($scadenze[0]->completata);

        $dichiarazione->stato = StatoDichiarazioneSuccessione::presentata;
        $this->assertTrue($fonte->getScadenze($this->studioTest, new SismaDate('2026-03-01'), new SismaDate('2026-03-31'))[0]->completata);
        $this->assertSame([], $fonte->getScadenze($this->studioTest, new SismaDate('2026-04-01'), new SismaDate('2026-04-30')));
    }
}
