<?php

namespace GestioneStudio\Tests;

use GestioneStudio\Application\Services\TabelleRiferimento\CalendarioFiscale;
use GestioneStudio\Console\Commands\CalendarioFiscaleCommand;

class CalendarioFiscaleTest extends GestioneStudioTestCase
{

    private ?string $cartella = null;

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->cartella !== null) {
            array_map('unlink', glob($this->cartella . '*'));
            rmdir($this->cartella);
        }
        parent::tearDown();
    }

    private function creaSorgenti(string $adempimenti, string $termini, string $proroghe = ''): CalendarioFiscale
    {
        $this->cartella = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'calendario-' . uniqid() . DIRECTORY_SEPARATOR;
        mkdir($this->cartella);
        file_put_contents($this->cartella . 'adempimenti.csv', implode(';', CalendarioFiscale::COLONNE_ADEMPIMENTI) . "\n" . $adempimenti);
        file_put_contents($this->cartella . 'termini.csv', implode(';', CalendarioFiscale::COLONNE_TERMINI) . "\n" . $termini);
        file_put_contents($this->cartella . 'proroghe.csv', implode(';', CalendarioFiscale::COLONNE_PROROGHE) . "\n" . $proroghe);
        return new CalendarioFiscale($this->cartella);
    }

    public function testSorgentiDelModulo(): void
    {
        $dati = (new CalendarioFiscale())->leggi();
        $this->assertCount(23, $dati['adempimenti']);
        $this->assertCount(70, $dati['termini']);
        $perCodice = array_column($dati['adempimenti'], null, 'codice');
        // fonte normativa con il punto e virgola, tra virgolette nel CSV
        $this->assertSame('art. 18, D.Lgs. 241/1997; art. 8, D.P.R. 602/1973', $perCodice['RITENUTE']['fonte']);
        // dal 2027 testi unici: Testo unico IVA e testo unico adempimenti e accertamento
        $this->assertSame(['art. 51, c. 1, D.Lgs. 141/2026', '2027-01-01'], [$perCodice['LIPE']['nuova_fonte'], $perCodice['LIPE']['nuova_fonte_dal']]);
        $this->assertSame('art. 111, c. 1, D.Lgs. 10/2026', $perCodice['IVA_ACCONTO']['nuova_fonte']);
        $this->assertNull($perCodice['IVA_MENSILE']['nuova_fonte'], 'il D.P.R. 100/1998 resta in vigore');
        // testo unico versamenti e riscossione e testo unico tributi erariali minori, dal 2027 (D.L. 200/2025, art. 4)
        $this->assertSame('artt. 8 e 32, D.Lgs. 33/2025', $perCodice['RITENUTE']['nuova_fonte']);
        $this->assertStringStartsWith('art. 16 della Tariffa (allegato 4) del D.Lgs. 174/2024', $perCodice['LIBRI_SOCIALI']['nuova_fonte']);
        $this->assertNull($perCodice['SALDO_PRIMO_ACCONTO']['nuova_fonte'], 'il D.P.R. 435/2001 resta in vigore');
        $adempimento = new \GestioneStudio\Application\Entities\Adempimento();
        $adempimento->fonte = $perCodice['LIPE']['fonte'];
        $adempimento->nuovaFonte = $perCodice['LIPE']['nuova_fonte'];
        $adempimento->nuovaFonteDal = new \SismaFramework\Orm\CustomTypes\SismaDate('2027-01-01');
        $this->assertStringStartsWith('art. 21-bis', $adempimento->getFonte(new \DateTimeImmutable('2026-11-30')));
        $this->assertSame('art. 51, c. 1, D.Lgs. 141/2026', $adempimento->getFonte(new \DateTimeImmutable('2027-03-02')), 'LIPE del quarto trimestre 2026');
        foreach ($dati['adempimenti'] as $adempimento) {
            $this->assertNotNull($adempimento['fonte']);
            $this->assertNull($adempimento['studio_id']);
        }
        $termini = array_column($dati['termini'], null, 'id');
        // IVA mensile di dicembre: 16 gennaio dell'anno successivo
        $this->assertSame(['mese' => 1, 'giorno' => 16, 'anni_successivi' => 1], array_intersect_key($termini[112], array_flip(['mese', 'giorno', 'anni_successivi'])));
        // LIPE del 2° trimestre al 30 settembre (art. 3, c. 1, D.L. 73/2022), del 4° a fine febbraio
        $this->assertSame(['mese' => 9, 'giorno' => 30], array_intersect_key($termini[602], array_flip(['mese', 'giorno'])));
        $this->assertSame(['mese' => 2, 'giorno' => 0, 'anni_successivi' => 1], array_intersect_key($termini[604], array_flip(['mese', 'giorno', 'anni_successivi'])));
        $this->assertSame(1001, CalendarioFiscale::getIdTermine(10, 1));
        // proroga 2026 dei versamenti per i soggetti ISA (art. 1-sexies D.L. 63/2026)
        $proroghe = array_column($dati['proroghe'], null, 'termine_adempimento_id');
        $this->assertSame(['anno' => 2025, 'data' => '2026-07-20', 'condizione' => 'ISA'], array_intersect_key($proroghe[1501], array_flip(['anno', 'data', 'condizione'])));
        $this->assertStringContainsString('1-sexies', $proroghe[1501]['provvedimento']);
        $this->assertSame('2026-07-20', $proroghe[2201]['data']);
        // ambiti con i regimi fiscali
        $this->assertSame(['IVM', 'IVT', 'IVO', 'MAN'], [$perCodice['IVA_MENSILE']['ambito'], $perCodice['IVA_TRIMESTRALE']['ambito'], $perCodice['LIPE']['ambito'], $perCodice['INTRASTAT_MENSILE']['ambito']]);
    }

    public function testIstruzioniSqlIdempotenti(): void
    {
        $calendario = $this->creaSorgenti("1;IVA;IVA mensile;VER;IVA;1;art. 1 D.P.R. 100/1998\n", "1;1;gennaio;2;16;0\n", "1;1;2026;2026-02-20;ISA;D.L. di prova;Nota\n");
        $istruzioni = implode("\n", iterator_to_array($calendario->istruzioni($calendario->leggi()), false));
        $this->assertStringContainsString("INSERT INTO `adempimento`", $istruzioni);
        $this->assertStringContainsString("ON DUPLICATE KEY UPDATE", $istruzioni);
        $this->assertStringContainsString("UPDATE `adempimento` SET `attivo` = 0 WHERE `studio_id` IS NULL AND `id` NOT IN (1);", $istruzioni);
        $this->assertStringContainsString("NOT IN (101);", $istruzioni);
        $this->assertStringContainsString("DELETE FROM `proroga_adempimento` WHERE `studio_id` IS NULL;", $istruzioni);
        $this->assertStringContainsString("(101, NULL, 2026, '2026-02-20', 'ISA', 'D.L. di prova', 'Nota')", $istruzioni);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function sorgentiNonValide(): array
    {
        $adempimento = "1;IVA;IVA mensile;VER;IVA;1;fonte\n";
        $termine = "1;1;gennaio;2;16;0\n";
        return [
            'id da studio' => ["1000;IVA;IVA;VER;IVA;1;fonte\n", $termine, '', 'id'],
            'codice duplicato' => [$adempimento . "2;IVA;Altro;VER;IVA;1;fonte\n", $termine . "2;1;anno;3;16;0\n", '', 'codice'],
            'categoria' => ["1;IVA;IVA;XXX;IVA;1;fonte\n", $termine, '', 'categoria'],
            'senza fonte' => ["1;IVA;IVA;VER;IVA;1;\n", $termine, '', 'fonte'],
            'nuova fonte senza data' => ["1;IVA;IVA;VER;IVA;1;fonte;art. 51;\n", $termine, '', 'insieme'],
            'data della nuova fonte' => ["1;IVA;IVA;VER;IVA;1;fonte;art. 51;01/01/2027\n", $termine, '', 'insieme'],
            'adempimento inesistente' => [$adempimento, "9;1;anno;3;16;0\n", '', 'inesistente'],
            'giorno' => [$adempimento, "1;1;anno;4;31;0\n", '', 'giorno'],
            'ordine duplicato' => [$adempimento, $termine . $termine, '', 'ordine'],
            'senza termini' => [$adempimento . "2;ALTRO;Altro;VER;IVA;1;fonte\n", $termine, '', 'senza termini'],
            'proroga senza termine' => [$adempimento, $termine, "1;2;2026;2026-02-20;TUT;D.L.;\n", 'termine inesistente'],
            'proroga data' => [$adempimento, $termine, "1;1;2026;20/02/2026;TUT;D.L.;\n", 'data'],
            'proroga condizione' => [$adempimento, $termine, "1;1;2026;2026-02-20;XXX;D.L.;\n", 'condizione'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sorgentiNonValide')]
    public function testSorgentiNonValideFermanoLImportazione(string $adempimenti, string $termini, string $proroghe, string $messaggio): void
    {
        $calendario = $this->creaSorgenti($adempimenti, $termini, $proroghe);
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage($messaggio);
        $calendario->leggi();
    }

    public function testIntestazioneNonValida(): void
    {
        $calendario = $this->creaSorgenti('', '');
        file_put_contents($this->cartella . 'adempimenti.csv', "id;codice\n");
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('intestazione');
        $calendario->leggi();
    }

    public function testComandoScriveLoScriptSql(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cal');
        $comando = new CalendarioFiscaleCommand();
        $this->assertTrue($comando->checkCompatibility('calendario-fiscale'));
        $this->assertFalse($comando->checkCompatibility('tabelle-imu'));
        $comando->setOptions(['sql' => $file]);
        ob_start();
        $esito = $comando->run();
        $output = ob_get_clean();
        $sql = file_get_contents($file);
        unlink($file);
        $this->assertTrue($esito);
        $this->assertStringContainsString('Adempimenti: 23, termini: 70', $output);
        $this->assertStringContainsString('Righe: adempimento 23, termine_adempimento 70', $sql);
        $this->assertStringContainsString("'REDDITI_SC'", $sql);
    }
}
