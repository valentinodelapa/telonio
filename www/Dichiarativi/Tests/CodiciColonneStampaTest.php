<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Services\Stampa\LettoreModelloPdf;
use PHPUnit\Framework\TestCase;

/**
 * Codici dei campi dal rigo e dalla colonna stampati sul modello: le
 * colonne con la lettera hanno nei cataloghi più forme, con la lettera in
 * fondo (RD01006A) o prima del numero (RF0640A1, RH001A12, NF001A01).
 */
class CodiciColonneStampaTest extends TestCase
{

    private function codice(LettoreModelloPdf $lettore, string $rigo, ?string $colonna): string
    {
        return (new \ReflectionMethod(LettoreModelloPdf::class, 'codice'))->invoke($lettore, $rigo, $colonna);
    }

    public function testFormeDelCodice(): void
    {
        $lettore = new LettoreModelloPdf(['RD01006A', 'RF0640A1', 'RH001A12', 'NF001A01', 'RN001001', 'RF012003']);
        $this->assertSame('RN001001', $this->codice($lettore, 'RN1', null));
        $this->assertSame('RF012003', $this->codice($lettore, 'RF12', '3'));
        // lettera in fondo, se il catalogo ha questa forma
        $this->assertSame('RD01006A', $this->codice($lettore, 'RD10', '6A'));
        // lettera prima del numero
        $this->assertSame('RF0640A1', $this->codice($lettore, 'RF64', '1A'));
        $this->assertSame('RH001A12', $this->codice($lettore, 'RH1', '12A'));
        $this->assertSame('NF001A01', $this->codice($lettore, 'NF1', '1A'));
        // nessuna delle due forme nel catalogo: quella con la lettera in fondo, che sarà scartata
        $this->assertSame('RG03201A', $this->codice($lettore, 'RG32', '1A'));
    }
}
