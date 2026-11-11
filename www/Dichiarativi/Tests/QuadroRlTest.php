<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Entities\CertificazioneAutonomo;
use Dichiarativi\Application\Services\Redditi\CalcoloQuadroRl;
use Telonio\Tests\SuiteTestCase;

/**
 * Quadro RL dalle Certificazioni Uniche del lavoro autonomo: redditi
 * diversi occasionali con le spese, dilettanti oltre 10.000 euro,
 * lavoro autonomo non abituale con le deduzioni forfetarie.
 */
class QuadroRlTest extends SuiteTestCase
{

    private function certificazione(string $causale, float $lordo, float $ritenute = 0, float $spese = 0, float $ritenuteImposta = 0): CertificazioneAutonomo
    {
        $certificazione = new CertificazioneAutonomo($this->dataMapper);
        $certificazione->causale = $causale;
        $certificazione->ammontareLordo = $lordo;
        $certificazione->ritenuteAcconto = $ritenute;
        $certificazione->ritenuteImposta = $ritenuteImposta;
        $certificazione->speseInerenti = $spese;
        return $certificazione;
    }

    public function testRedditiDiversiOccasionali(): void
    {
        $calcolo = new CalcoloQuadroRl();
        $campi = $calcolo->calcola([$this->certificazione('M', 3000, 600, 500), $this->certificazione('V1', 1000, 0, 1500), $this->certificazione('A', 5000, 1000)], false);
        $this->assertSame(3000, $campi['RL015002']);
        $this->assertSame(500, $campi['RL015003']);
        // spese non oltre i corrispettivi
        $this->assertSame(1000, $campi['RL014003']);
        $this->assertSame(4000, $campi['RL018001']);
        $this->assertSame(1500, $campi['RL018002']);
        $this->assertSame(2500, $campi['RL019001']);
        $this->assertSame(600, $campi['RL020001']);
        $this->assertCount(1, $calcolo->getAvvisi());
    }

    public function testDilettantiELavoroAutonomoNonAbituale(): void
    {
        $calcolo = new CalcoloQuadroRl();
        $campi = $calcolo->calcola([$this->certificazione('N', 35000, 998.66, 0, 4751.34), $this->certificazione('B', 10000, 1500)], true);
        $this->assertSame(35000, $campi['RL021001']);
        $this->assertSame(20658, $campi['RL022001']);
        $this->assertSame(4342, $campi['RL022002']);
        $this->assertSame(5750, $campi['RL023001']);
        $this->assertSame(999, $campi['RL023002']);
        // nato dopo il 1990: deduzione forfetaria del 40 per cento
        $this->assertSame(10000, $campi['RL030001']);
        $this->assertSame(4000, $campi['RL031001']);
        $this->assertSame(6000, $campi['RL032001']);
        $this->assertSame(1500, $campi['RL033001']);
    }
}
