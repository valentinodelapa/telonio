<?php

namespace Anagrafica\Tests\Services;

use Anagrafica\Application\Services\RiepilogoScrivania;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;

class RiepilogoScrivaniaTest extends SuiteTestCase
{

    public function testGetIndicatori(): void
    {
        $studio = $this->creaStudio();
        $this->testAdapter->accodaSelect(TestResultSet::conteggio(12));
        $this->testAdapter->accodaSelect(TestResultSet::conteggio(8));
        $this->testAdapter->accodaSelect(TestResultSet::conteggio(15));
        $this->testAdapter->accodaSelect(TestResultSet::conteggio(7));

        $servizio = new RiepilogoScrivania($this->dataMapper);
        $indicatori = $servizio->getIndicatori($studio);

        $this->assertSame([
            'Clienti' => 12,
            'Persone fisiche' => 8,
            'Totale soggetti' => 15,
            'Immobili' => 7,
        ], $indicatori);
    }
}
