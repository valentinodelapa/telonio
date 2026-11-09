<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Entities\AddizionaleComunale;
use Dichiarativi\Application\Entities\AddizionaleRegionale;
use Dichiarativi\Application\Services\Redditi\CalcoloAddizionali;
use Dichiarativi\Application\Services\TabelleRiferimento\LettoreAddizionaliComunali;
use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;
use Telonio\Tests\SuiteTestCase;

/**
 * Addizionali all'IRPEF: lettura delle sorgenti (tabella regionale delle
 * istruzioni Redditi PF 2026 ed elenco comunale dell'Agenzia) e calcolo,
 * con i valori della colonna "imposta dovuta" della tabella regionale.
 */
class AddizionaliTest extends SuiteTestCase
{

    /** @var array{regionali: array, comunali: array}|null */
    private static ?array $sorgenti = null;

    private function getSorgenti(): array
    {
        return self::$sorgenti ??= (new TabelleDichiarativi())->leggi();
    }

    private function regione(string $codice): AddizionaleRegionale
    {
        foreach ($this->getSorgenti()['regionali'] as $riga) {
            if ($riga['codice'] === $codice) {
                $tabella = new AddizionaleRegionale($this->dataMapper);
                $tabella->anno = $riga['anno'];
                $tabella->codice = $riga['codice'];
                $tabella->regione = $riga['regione'];
                $tabella->scaglioni = $riga['scaglioni'];
                $tabella->modalita = $riga['modalita'];
                $tabella->esenzioneReddito = $riga['esenzione_reddito'] === null ? null : (float) $riga['esenzione_reddito'];
                $tabella->sogliaAliquota = $riga['soglia_aliquota'];
                $tabella->detrazioni = $riga['detrazioni'];
                $tabella->detrazioneCrescente = $riga['detrazione_crescente'];
                $tabella->deduzione = $riga['deduzione'];
                $tabella->casiParticolari = $riga['casi_particolari'];
                return $tabella;
            }
        }
        $this->fail('Regione ' . $codice . ' assente');
    }

    private function comune(string $codice): AddizionaleComunale
    {
        foreach ($this->getSorgenti()['comunali'] as $riga) {
            if ($riga['codice_catastale'] === $codice) {
                $tabella = new AddizionaleComunale($this->dataMapper);
                $tabella->anno = $riga['anno'];
                $tabella->codiceCatastale = $codice;
                $tabella->aliquotaUnica = $riga['aliquota_unica'] === null ? null : (float) $riga['aliquota_unica'];
                $tabella->aliquote = $riga['aliquote'];
                $tabella->tipologia = $riga['tipologia'];
                $tabella->esenzione = $riga['esenzione'] === null ? null : (float) $riga['esenzione'];
                $tabella->tipizzazione = $riga['tipizzazione'];
                $tabella->esenzioneCasi = $riga['esenzione_casi'] === null ? null : (float) $riga['esenzione_casi'];
                $tabella->casiParticolari = $riga['casi_particolari'];
                return $tabella;
            }
        }
        $this->fail('Comune ' . $codice . ' assente');
    }

    public function testSorgenti(): void
    {
        $sorgenti = $this->getSorgenti();
        $this->assertCount(21, $sorgenti['regionali']);
        $this->assertGreaterThan(7800, count($sorgenti['comunali']));
        $this->assertTrue(TabelleDichiarativi::isScaglioni('15000:1.73|28000:2.96|:3.33'));
        $this->assertFalse(TabelleDichiarativi::isScaglioni('28000:1.73|15000:2.96|:3.33'));
        $this->assertFalse(TabelleDichiarativi::isScaglioni('15000:1.73'));
    }

    public function testImpostaDovutaDellaTabellaRegionale(): void
    {
        $calcolo = new CalcoloAddizionali();
        $importo = fn(string $regione, float $imponibile, ?float $complessivo = null) => $calcolo->regionale($this->regione($regione), $imponibile, $complessivo ?? $imponibile)['importo'];
        // Campania: 644,3 a 28.000 e 1.348,3 a 50.000 (colonna "imposta dovuta")
        $this->assertSame(644.3, $importo('05', 28000));
        $this->assertSame(1348.3, $importo('05', 50000));
        $this->assertSame(768.3, $importo('10', 50000));
        $this->assertSame(467.6, $importo('01', 28000));
        // Friuli-Venezia Giulia: aliquota dello scaglione sull'intero reddito
        $this->assertSame(105.0, $importo('07', 15000));
        $this->assertSame(246.0, $importo('07', 20000));
        // Lazio: 1,73% sull'intero fino a 28.000, detrazione di 60 euro tra 28.001 e 35.000
        $this->assertSame(346.0, $importo('08', 20000));
        $this->assertSame(699.0, $importo('08', 30000));
        // Umbria: 1,23% fino a 28.000, detrazione di 150 euro tra 28.001 e 50.000
        $this->assertSame(246.0, $importo('19', 20000));
        $this->assertSame(876.5, $importo('19', 40000));
        // Bolzano: 615 + 1,73% oltre 50.000, detrazioni di 430,50 e 125 x 10.000 / 25.000
        $this->assertSame(307.5, $importo('03', 60000));
        $this->assertSame(0.0, $importo('03', 30000));
        // Trento: deduzione di 30.000 fino a 30.000; Valle d'Aosta: esenzione fino a 15.000 di reddito complessivo
        $this->assertSame(0.0, $importo('18', 25000));
        $this->assertSame(0.0, $importo('20', 14000, 15000));
        $this->assertSame(184.5, $importo('20', 15000, 15001));
        $this->assertNotEmpty($calcolo->regionale($this->regione('21'), 20000, 20000)['avvisi']);
    }

    public function testAddizionaleComunale(): void
    {
        $calcolo = new CalcoloAddizionali();
        // Torino: 0,8 / 0,8 / 1,1 / 1,2 per scaglioni, esenzione fino a 11.790
        $torino = $this->comune('L219');
        $this->assertSame(356.0, $calcolo->comunale($torino, 40000)['importo']);
        $this->assertSame(106.8, $calcolo->accontoComunale($torino, 40000)['importo']);
        $this->assertTrue($calcolo->comunale($torino, 11790)['esente']);
        // Milano: 0,8% con esenzione fino a 23.000, sull'intero imponibile oltre la soglia
        $milano = $this->comune('F205');
        $this->assertSame(0.0, $calcolo->comunale($milano, 23000)['importo']);
        $this->assertSame(184.01, $calcolo->comunale($milano, 23001)['importo']);
        // tre scaglioni (tipologia 2): Abbadia Lariana 0,76 / 0,77 / 0,8
        $this->assertSame(round(28000 * 0.0076 + 22000 * 0.0077 + 10000 * 0.008, 2), $calcolo->comunale($this->comune('A005'), 60000)['importo']);
        // Palermo 1,014%; comune non in elenco: non dovuta
        $this->assertSame(304.2, $calcolo->comunale($this->comune('G273'), 30000)['importo']);
        $this->assertSame(0.0, $calcolo->comunale(null, 30000)['importo']);
        // casi particolari: avviso con la soglia (Altopascio, codice A, 10.000)
        $this->assertStringContainsString('codice A, soglia 10.000,00', $calcolo->comunale($this->comune('A241'), 30000)['avvisi'][0]);
    }

    public function testLettoreDellElenco(): void
    {
        // righe di prova nel formato di "pdftotext -tsv": comune con aliquota unica ed esenzione,
        // comune per scaglioni e cella dei casi particolari proseguita nella pagina seguente
        $parola = fn(int $pagina, float $x, float $y, string $testo) => implode("\t", ['5', $pagina, 1, 1, 1, 1, $x, $y, 10, 10, 100, $testo]);
        $tsv = implode("\n", [
            $parola(1, 27.73, 251.19, 'A001'), $parola(1, 85.99, 251.19, 'ABANO'), $parola(1, 305.72, 251.19, '0.8'), $parola(1, 672.1, 251.19, '12.000'),
            $parola(1, 27.73, 266.03, 'A034'), $parola(1, 346.08, 266.03, '0.38'), $parola(1, 401.96, 266.03, '0.4'), $parola(1, 455.2, 266.03, '0.45'), $parola(1, 509.75, 266.03, '0.47'), $parola(1, 591.81, 266.03, '1'),
            $parola(1, 27.73, 900.0, 'A241'), $parola(1, 305.72, 900.0, '0.704'), $parola(1, 672.1, 900.0, '15000'),
            $parola(2, 851.03, 250.0, 'Esenzione'), $parola(2, 900.0, 250.0, 'pensionati'), $parola(2, 672.1, 260.0, '10000'), $parola(2, 795.83, 260.0, 'A'),
            $parola(2, 27.73, 300.0, 'A242'), $parola(2, 305.72, 300.0, '0.6'),
            $parola(3, 219.0, 84.0, 'LEGENDA'), $parola(3, 300.0, 300.0, 'CODICE'),
        ]);
        $comuni = (new LettoreAddizionaliComunali())->leggi($tsv);
        $this->assertSame(['A001', 'A034', 'A241', 'A242'], array_keys($comuni));
        $this->assertSame(12000.0, $comuni['A001']['esenzione']);
        $this->assertSame([0.38, 0.4, 0.45, 0.47], $comuni['A034']['aliquote']);
        $this->assertSame(0.704, $comuni['A241']['aliquotaUnica']);
        $this->assertSame(10000.0, $comuni['A241']['esenzioneCasi']);
        $this->assertSame('A', $comuni['A241']['tipizzazione']);
        $this->assertSame('Esenzione pensionati', $comuni['A241']['casiParticolari']);
        $this->assertNull($comuni['A242']['casiParticolari']);
    }
}
