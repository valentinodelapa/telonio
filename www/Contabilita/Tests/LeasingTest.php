<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\ContrattoLeasing;
use Contabilita\Application\Services\Leasing;
use Contabilita\Application\Services\RighiIrap;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Contratti di locazione finanziaria: quota interessi con il metodo
 * forfetario del D.M. 24 aprile 1998 e periodo del contratto nell'anno.
 */
class LeasingTest extends ContabilitaTestCase
{

    public function testQuotaInteressiForfetaria(): void
    {
        // contratto di 4 anni (dal 1° luglio 2024 al 30 giugno 2028): canoni 110.000 + riscatto 1.000 - costo 100.000 = 11.000 di interessi
        $contratto = new ContrattoLeasing($this->dataMapper);
        $contratto->dataInizio = new SismaDate('2024-07-01');
        $contratto->dataFine = new SismaDate('2028-06-30');
        $contratto->costoConcedente = 100000.0;
        $contratto->totaleCanoni = 110000.0;
        $contratto->prezzoRiscatto = 1000.0;
        $this->assertSame(1461, Leasing::getDurata($contratto));
        $this->assertSame(184, Leasing::getGiorniNellAnno($contratto, 2024));
        $this->assertSame(365, Leasing::getGiorniNellAnno($contratto, 2025));
        $this->assertSame(round(11000 / 1461 * 365, 2), Leasing::getQuotaInteressi($contratto, 2025));
        $this->assertSame(0.0, Leasing::getQuotaInteressi($contratto, 2029));
        // riscatto anticipato: il contratto finisce alla data del riscatto
        $contratto->dataRiscatto = new SismaDate('2025-03-31');
        $this->assertSame(90, Leasing::getGiorniNellAnno($contratto, 2025));
        $this->assertEqualsWithDelta(90 / 365, Leasing::getQuotaPossesso($contratto, 2025), 0.0001);
        // nessun interesse se i canoni non superano il costo
        $contratto->totaleCanoni = 90000.0;
        $this->assertSame(0.0, Leasing::getQuotaInteressi($contratto, 2025));
    }

    public function testVariazioniIrapDelConto(): void
    {
        $this->assertTrue(RighiIrap::isValido(null));
        $this->assertTrue(RighiIrap::isValido('IC46'));
        $this->assertTrue(RighiIrap::isValido('IC57:04'));
        $this->assertFalse(RighiIrap::isValido('IC57:98'));
    }
}
