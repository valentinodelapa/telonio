<?php

namespace Contabilita\Tests;

use Contabilita\Application\Services\PlafondEsportatori;

/**
 * Plafond degli esportatori abituali: disponibile iniziale con i metodi
 * solare e mensile, prospetto mese per mese, superamento entro il periodo
 * della liquidazione e status di esportatore abituale.
 */
class PlafondEsportatoriTest extends ContabilitaTestCase
{

    private static function dati(array $acquisti = [], array $esportazioni = [], array $volume = []): array
    {
        return ['acquisti' => array_replace(array_fill(1, 12, 0.0), $acquisti), 'importazioni' => array_fill(1, 12, 0.0),
            'volume' => array_replace(array_fill(1, 12, 0.0), $volume), 'esportazioni' => array_replace(array_fill(1, 12, 0.0), $esportazioni)];
    }

    public function testMetodoSolare(): void
    {
        $precedente = self::dati([], [3 => 6000.0, 9 => 6000.0], [3 => 6000.0, 9 => 6000.0, 10 => 20000.0]);
        $this->assertSame(12000.0, PlafondEsportatori::getPlafondIniziale($precedente, false));
        $this->assertTrue(PlafondEsportatori::isEsportatoreAbituale($precedente), '12.000 su 32.000: oltre il 10%');
        $this->assertNull(PlafondEsportatori::isEsportatoreAbituale(self::dati()));
        $corrente = self::dati([1 => 5000.0, 4 => 5000.0, 7 => 4000.0]);
        $prospetto = PlafondEsportatori::prospetto($corrente, $precedente, false, 12000.0);
        $this->assertSame(['disponibile' => 12000.0, 'utilizzato' => 5000.0, 'residuo' => 7000.0, 'superato' => false], $prospetto[1]);
        $this->assertSame(2000.0, $prospetto[7]['disponibile']);
        $this->assertTrue($prospetto[7]['superato']);
        $this->assertNull(PlafondEsportatori::verifica($prospetto, 6), 'liquidazioni fino a giugno: nessun superamento');
        $this->assertStringContainsString('superato a luglio', PlafondEsportatori::verifica($prospetto, 9));
    }

    public function testMetodoMensile(): void
    {
        // dodici mesi precedenti al netto degli utilizzi degli stessi mesi
        $precedente = self::dati([11 => 1000.0], [6 => 8000.0, 12 => 4000.0]);
        $this->assertSame(11000.0, PlafondEsportatori::getPlafondIniziale($precedente, true));
        $corrente = self::dati([1 => 6000.0, 6 => 6000.0, 7 => 500.0], [2 => 3000.0]);
        $prospetto = PlafondEsportatori::prospetto($corrente, $precedente, true, 11000.0);
        $this->assertSame(11000.0, $prospetto[1]['disponibile']);
        // giugno: da giugno a dicembre dell'anno precedente e da gennaio a maggio (12000 - 1000 + 3000 - 6000)
        $this->assertSame(8000.0, $prospetto[6]['disponibile']);
        $this->assertFalse($prospetto[6]['superato']);
        // luglio: escono le esportazioni di giugno dell'anno precedente, entrano gli acquisti di giugno
        $this->assertSame(0.0, $prospetto[7]['disponibile']);
        $this->assertTrue($prospetto[7]['superato']);
    }
}
