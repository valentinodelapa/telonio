<?php

namespace Imu\Tests\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Imu\Application\Entities\AliquotaDifferenziata;
use Imu\Application\Entities\CoefficienteFabbricatiD;
use Imu\Application\Entities\CostoContabileImu;
use Imu\Application\Enumerations\FattispecieImu;
use Imu\Application\Services\CompatibilitaDifferenziate;
use Imu\Application\Services\MesiPossesso;
use Imu\Application\Services\ValoreImmobile;

class MesiPossessoAndValoreImmobileTest extends SuiteTestCase
{

    public function testMesiPossessoAttribuzione(): void
    {
        // Anno intero
        $mesi = MesiPossesso::attribuisci(2025, [
            'intero' => [new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31')],
        ]);
        $this->assertSame(range(1, 12), $mesi['intero']);

        // Trasferimento a metà mese di giugno (30 giorni): cedente 1-15 (15 gg, non fino a fine mese), acquirente 16-30 (15 gg, fino a fine mese)
        $cedente = MesiPossesso::attribuisci(2025, [
            0 => [new \DateTimeImmutable('2025-06-01'), new \DateTimeImmutable('2025-06-15')],
        ]);
        $this->assertSame([], $cedente[0]);

        $acquirente = MesiPossesso::attribuisci(2025, [
            0 => [new \DateTimeImmutable('2025-06-16'), new \DateTimeImmutable('2025-06-30')],
        ]);
        $this->assertSame([6], $acquirente[0]);

        // Variazione interna nello stesso possesso a metà mese: a parità di giorni prevale il secondo periodo
        $variazione = MesiPossesso::attribuisci(2025, [
            'primo' => [new \DateTimeImmutable('2025-04-01'), new \DateTimeImmutable('2025-04-15')],
            'secondo' => [new \DateTimeImmutable('2025-04-16'), new \DateTimeImmutable('2025-04-30')],
        ]);
        $this->assertSame([], $variazione['primo']);
        $this->assertSame([4], $variazione['secondo']);

        // Mese di 31 giorni (gennaio): 16 giorni > metà
        $gennaio = MesiPossesso::attribuisci(2025, [
            'p1' => [new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-01-16')],
        ]);
        $this->assertSame([1], $gennaio['p1']);
    }

    public function testValoreImmobileFabbricatoTerrenoEContabile(): void
    {
        $dati = new DatiCatastali($this->dataMapper);
        $dati->causale = CausaleClassamento::costituzione;
        $this->assertSame(0.0, ValoreImmobile::fabbricato($dati));

        // Categoria A/2 (moltiplicatore 160), rendita 1000 => 1000 * 1.05 * 160 = 168000
        $dati->categoria = CategoriaCatastale::a2;
        $dati->rendita = 1000.0;
        $this->assertSame(168000.0, ValoreImmobile::fabbricato($dati));

        // Categoria C/1 (moltiplicatore 55), rendita 1000 => 1000 * 1.05 * 55 = 57750
        $dati->categoria = CategoriaCatastale::c1;
        $this->assertSame(57750.0, ValoreImmobile::fabbricato($dati));

        // Terreno con RD 100 => 100 * 1.25 * 135 = 16875
        $dati->redditoDominicale = 100.0;
        $this->assertSame(16875.0, ValoreImmobile::terreno($dati));

        // Contabile con ripiego all'anno precedente e costi antecedenti al 1982
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 10;

        $c1982 = new CoefficienteFabbricatiD($this->dataMapper);
        $c1982->annoImposta = 2024;
        $c1982->annoCosto = 1982;
        $c1982->coefficiente = 3.0;

        $c2020 = new CoefficienteFabbricatiD($this->dataMapper);
        $c2020->annoImposta = 2024;
        $c2020->annoCosto = 2020;
        $c2020->coefficiente = 1.2;

        $c2024 = new CoefficienteFabbricatiD($this->dataMapper);
        $c2024->annoImposta = 2024;
        $c2024->annoCosto = 2024;
        $c2024->coefficiente = 1.05;

        $costo1980 = new CostoContabileImu($this->dataMapper);
        $costo1980->anno = 1980;
        $costo1980->importo = 10000.0;

        $costo2020 = new CostoContabileImu($this->dataMapper);
        $costo2020->anno = 2020;
        $costo2020->importo = 50000.0;

        $costo2025 = new CostoContabileImu($this->dataMapper);
        $costo2025->anno = 2025;
        $costo2025->importo = 20000.0;

        // Anno 2025 non ha coefficienti, ripiega su 2024; acquistoNellAnno = false => ignora costo 2025
        $this->testAdapter->accodaSelect(
            new TestResultSet([]), // 2025 vuoto
            new TestResultSet([$c1982, $c2020, $c2024]), // 2024
            new TestResultSet([$costo1980, $costo2020, $costo2025])
        );
        $esito = (new ValoreImmobile($this->dataMapper))->contabile($immobile, 2025, false);
        // 10000 * 3.0 + 50000 * 1.2 = 30000 + 60000 = 90000
        $this->assertSame(['valore' => 90000.0, 'annoDecreto' => 2024, 'costi' => 2], $esito);

        // Con acquistoNellAnno = true, include anche costo 2025 rivalutato con coeff 2024 (1.05) => +21000 = 111000
        $this->testAdapter->accodaSelect(
            new TestResultSet([]),
            new TestResultSet([$c1982, $c2020, $c2024]),
            new TestResultSet([$costo1980, $costo2020, $costo2025])
        );
        $esitoAcquisto = (new ValoreImmobile($this->dataMapper))->contabile($immobile, 2025, true);
        $this->assertSame(['valore' => 111000.0, 'annoDecreto' => 2024, 'costi' => 3], $esitoAcquisto);
    }

    public function testCompatibilitaDifferenziate(): void
    {
        $diff = new AliquotaDifferenziata($this->dataMapper);
        $diff->fattispecie = FattispecieImu::altriFabbricati;
        $diff->aliquota = 0.56;
        $diff->descrizione = 'Abitazione locata o in comodato - Tipo contratto: Locazione ai sensi dell\'art. 2, comma 3, della Legge n.431/1998 e s.m.i. Categoria catastale: - A/2 - A/3';

        // Categoria e utilizzo corrispondenti, nessuna condizione da verificare => verificata = true
        $esito = CompatibilitaDifferenziate::verifica($diff, FattispecieImu::altriFabbricati, CategoriaCatastale::a2, UtilizzoImmobile::locatoCanoneConcordato);
        $this->assertTrue($esito['compatibile']);
        $this->assertTrue($esito['verificata']);
        $this->assertSame([], $esito['daVerificare']);

        // Fattispecie diversa => incompatibile
        $esitoFattispecie = CompatibilitaDifferenziate::verifica($diff, FattispecieImu::gruppoD, CategoriaCatastale::a2, UtilizzoImmobile::locatoCanoneConcordato);
        $this->assertFalse($esitoFattispecie['compatibile']);

        // Categoria diversa => incompatibile
        $esitoCat = CompatibilitaDifferenziate::verifica($diff, FattispecieImu::altriFabbricati, CategoriaCatastale::c1, UtilizzoImmobile::locatoCanoneConcordato);
        $this->assertFalse($esitoCat['compatibile']);

        // Condizioni da verificare (es. ONLUS, parentela, contratto registrato) e gruppi di categorie
        $diffCond = new AliquotaDifferenziata($this->dataMapper);
        $diffCond->fattispecie = FattispecieImu::altriFabbricati;
        $diffCond->aliquota = 0.46;
        $diffCond->descrizione = 'Immobili di categoria A10, B, C concessi in comodato d\'uso gratuito a parenti con contratto registrato - Requisiti del soggetto passivo: ONLUS';

        // Per persona fisica ($ente = false) viene esclusa perché richiede ONLUS
        $esitoNonEnte = CompatibilitaDifferenziate::verifica($diffCond, FattispecieImu::altriFabbricati, CategoriaCatastale::c2, UtilizzoImmobile::comodato, false, true);
        $this->assertFalse($esitoNonEnte['compatibile']);

        // Per ente ($ente = true) è compatibile ma ha condizioni da verificare => verificata = false
        $esitoEnte = CompatibilitaDifferenziate::verifica($diffCond, FattispecieImu::altriFabbricati, CategoriaCatastale::c2, UtilizzoImmobile::comodato, true, true);
        $this->assertTrue($esitoEnte['compatibile']);
        $this->assertFalse($esitoEnte['verificata']);
        $this->assertContains('requisiti del soggetto passivo (ONLUS o terzo settore)', $esitoEnte['daVerificare']);
        $this->assertContains('contratto registrato', $esitoEnte['daVerificare']);
        $this->assertContains('grado di parentela del comodatario', $esitoEnte['daVerificare']);
    }
}
