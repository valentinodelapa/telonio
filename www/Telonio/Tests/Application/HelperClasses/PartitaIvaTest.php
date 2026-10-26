<?php

namespace Telonio\Tests\Application\HelperClasses;

use Telonio\Application\HelperClasses\PartitaIva;
use Telonio\Tests\SuiteTestCase;

class PartitaIvaTest extends SuiteTestCase
{

    public function testNormalizzaConESenzaPrefissoItESpazi(): void
    {
        $this->assertSame('01234567897', PartitaIva::normalizza('01234567897'));
        $this->assertSame('01234567897', PartitaIva::normalizza(' it 01234 567897 '));
        $this->assertSame('01234567897', PartitaIva::normalizza("IT01234567897\n"));
    }

    public function testValidazioneLuhnValidaENonValida(): void
    {
        $this->assertTrue(PartitaIva::isValida('01234567897'));
        $this->assertTrue(PartitaIva::isValida('IT 01234567897'));
        // Carattere di controllo errato
        $this->assertFalse(PartitaIva::isValida('01234567890'));
        $this->assertFalse(PartitaIva::isValida('01234567898'));
        // Lunghezza errata o caratteri non numerici
        $this->assertFalse(PartitaIva::isValida('0123456789'));
        $this->assertFalse(PartitaIva::isValida('012345678970'));
        $this->assertFalse(PartitaIva::isValida('0123456789A'));
        $this->assertFalse(PartitaIva::isValida(''));
    }
}
