<?php

namespace Contabilita\Tests;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RegimeForfetario;

/**
 * Regime forfetario: soglie dei ricavi e compensi percepiti, periodi e
 * termini del versamento dell'IVA sugli acquisti in inversione contabile.
 */
class RegimeForfetarioTest extends ContabilitaTestCase
{

    public function testSoglie(): void
    {
        $this->assertNull(RegimeForfetario::verificaSoglie(85000.0, 2026));
        $this->assertStringContainsString('cessa dal 2027', RegimeForfetario::verificaSoglie(85000.01, 2026));
        $this->assertStringContainsString('art. 1, cc. 54 e 71, L. 190/2014', RegimeForfetario::verificaSoglie(90000.0, 2026));
        $this->assertStringContainsString('nel 2026 stesso', RegimeForfetario::verificaSoglie(100000.01, 2026));
        $this->assertStringContainsString('art. 238, c. 1, D.Lgs. 117/2026', RegimeForfetario::verificaSoglie(120000.0, 2027), 'dal 2027 il nuovo TUIR');
    }

    public function testPeriodiDiVersamento(): void
    {
        $this->assertSame('M09', RegimeForfetario::getPeriodo(new \DateTimeImmutable('2025-09-30')));
        $this->assertSame('T4', RegimeForfetario::getPeriodo(new \DateTimeImmutable('2025-10-01')), 'trimestrale dal 1° ottobre 2025');
        $this->assertSame('T2', RegimeForfetario::getPeriodo(new \DateTimeImmutable('2026-05-20')));
        $mensile = RegimeForfetario::descriviPeriodo('M05', 2025);
        $this->assertSame(['2025-06-16', '6005', false], [$mensile['scadenza']->format('Y-m-d'), $mensile['codice'], $mensile['trimestrale']]);
        // trimestri: 16 del secondo mese successivo, codice dell'ultimo mese; quarto trimestre a febbraio
        $this->assertSame(['2025-11-17', '6009'], [RegimeForfetario::descriviPeriodo('T3', 2025)['scadenza']->format('Y-m-d'), RegimeForfetario::descriviPeriodo('T3', 2025)['codice']], '16 novembre 2025 di domenica');
        $this->assertSame(['2026-02-16', '6012'], [RegimeForfetario::descriviPeriodo('T4', 2025)['scadenza']->format('Y-m-d'), RegimeForfetario::descriviPeriodo('T4', 2025)['codice']]);
        $this->assertSame('iva-forfetario-2025-T4', RegimeForfetario::getRiferimento(2025, 'T4'));
    }

    public function testIvaDovutaDagliAcquistiInInversioneContabile(): void
    {
        $gamma = $this->creaControparte(7, 'Gamma', null, $this->conti['13.01.0001']);
        $righe = [];
        $id = 300;
        foreach ([['2026-01-15', 'RC22', 1000.0], ['2026-02-10', 'RC22', 500.0], ['2026-02-12', '22', 900.0], ['2026-04-20', 'RC22', 100.0]] as [$data, $codice, $imponibile]) {
            $registrazione = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, $gamma, $data);
            $registrazione->id = $id++;
            $righe[] = $this->creaRigaIva($codice, $imponibile, 1, null, $registrazione);
        }
        $this->rispondiConti(['riga_iva' => $righe]);
        $periodi = (new RegimeForfetario($this->dataMapper))->getIvaDovuta($this->azienda, 2026);
        $this->assertSame(['T1', 'T2'], array_keys($periodi));
        $this->assertSame(330.0, $periodi['T1']['imposta'], 'solo le integrazioni, non l\'IVA addebitata dal fornitore');
        $this->assertSame(2, $periodi['T1']['documenti']);
        $this->assertSame('2026-05-18', $periodi['T1']['scadenza']->format('Y-m-d'), '16 maggio 2026 di sabato');
        $this->assertSame(22.0, $periodi['T2']['imposta']);
    }
}
