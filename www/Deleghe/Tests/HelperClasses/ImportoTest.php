<?php

namespace Deleghe\Tests\HelperClasses;

use Telonio\Tests\SuiteTestCase;
use Deleghe\Application\HelperClasses\Importo;

class ImportoTest extends SuiteTestCase
{

    public function testImportoEuroENormalizza(): void
    {
        $this->assertSame('1.050,25', Importo::euro(1050.25));
        $this->assertSame('0,00', Importo::euro(0.0, false));
        $this->assertSame('', Importo::euro(0.0, true));
        $this->assertSame('', Importo::euro(0.004, true));
        $this->assertSame('12,30', Importo::euro(12.3, true));

        $this->assertSame('1050.25', Importo::normalizza('1.050,25'));
        $this->assertSame('1050.25', Importo::normalizza(' € 1.050,25 '));
        $this->assertSame('1050.25', Importo::normalizza('1050.25'));
        $this->assertSame('', Importo::normalizza('   '));
    }
}
