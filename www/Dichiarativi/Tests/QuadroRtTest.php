<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Entities\CessioneFinanziaria;
use Dichiarativi\Application\Services\Redditi\CalcoloQuadroRt;
use Telonio\Tests\SuiteTestCase;

/**
 * Quadro RT, sezioni II e V: totali delle cessioni, costo rideterminato,
 * minusvalenze degli anni precedenti, franchigia delle cripto-attività
 * detenute prima del 2025, imposta e residui della sezione VII.
 */
class QuadroRtTest extends SuiteTestCase
{

    private function cessione(string $tipo, float $corrispettivo, float $costo, bool $rideterminato = false, bool $ante = false): CessioneFinanziaria
    {
        $cessione = new CessioneFinanziaria($this->dataMapper);
        $cessione->tipo = $tipo;
        $cessione->corrispettivo = $corrispettivo;
        $cessione->costo = $costo;
        $cessione->costoRideterminato = $rideterminato;
        $cessione->anteriore2025 = $ante;
        return $cessione;
    }

    public function testPlusvalenzeConMinusvalenzePrecedenti(): void
    {
        $t = CessioneFinanziaria::TITOLI;
        $calcolo = CalcoloQuadroRt::calcola([$this->cessione($t, 10000, 7000), $this->cessione($t, 5000, 6000),
            $this->cessione($t, 8000, 5000, true), $this->cessione($t, 2000, 3000, true)], [1 => 1000, 3 => 2500], [], 100);
        $campi = $calcolo['campi'];
        $this->assertSame(15000, $campi['RT011001']);
        $this->assertSame(13000, $campi['RT011002']);
        // la minusvalenza sul costo rideterminato non conta
        $this->assertSame(5000, $campi['RT052002']);
        $this->assertSame(3500, $campi['RT013001']);
        $this->assertSame(1500, $campi['RT072001']);
        $this->assertSame(390, $campi['RT073001']);
        $this->assertSame(100, $campi['RT015001']);
        $this->assertSame(290, $campi['RT074001']);
        $this->assertArrayNotHasKey('RT102001', $campi);
        $this->assertCount(2, $calcolo['righe12']);
        $this->assertSame([1 => true, 3 => 8000, 4 => 5000], $calcolo['righe12'][0]);
    }

    public function testMinusvalenzaRiportata(): void
    {
        $campi = CalcoloQuadroRt::calcola([$this->cessione(CessioneFinanziaria::TITOLI, 1000, 3000)], [2 => 400])['campi'];
        $this->assertSame(2000, $campi['RT052001']);
        $this->assertSame(2000, $campi['RT102005']);
        $this->assertSame(400, $campi['RT102002']);
        $this->assertArrayNotHasKey('RT073001', $campi);
    }

    public function testCriptoAttivita(): void
    {
        $c = CessioneFinanziaria::CRIPTO;
        // detenute prima del 2025: 3.000 meno la franchigia di 2.000; dal 2025: minusvalenza di 500
        $campi = CalcoloQuadroRt::calcola([$this->cessione($c, 5000, 2000, false, true), $this->cessione($c, 1000, 1500)])['campi'];
        $this->assertSame(5000, $campi['RT041001']);
        $this->assertSame(2000, $campi['RT041002']);
        $this->assertSame(1000, $campi['RT041003']);
        $this->assertSame(1500, $campi['RT041004']);
        $this->assertSame(500, $campi['RT057002']);
        $this->assertSame(130, $campi['RT089001']);
        $this->assertSame(130, $campi['RT090001']);
    }

    public function testUsoDelleMinusvalenze(): void
    {
        $this->assertSame([700, [1 => 0, 2 => 200, 4 => 300]], CalcoloQuadroRt::usaMinusvalenze([4 => 300, 1 => 500, 2 => 400], 700));
    }
}
