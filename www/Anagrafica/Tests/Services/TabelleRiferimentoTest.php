<?php

namespace Anagrafica\Tests\Services;

use Anagrafica\Application\Services\TabelleRiferimento\ArchivioComuniAnpr;
use Anagrafica\Application\Services\TabelleRiferimento\ClassificazioniAtecoIstat;
use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use Anagrafica\Application\Services\TabelleRiferimento\LettoreXlsx;
use Anagrafica\Application\Services\TabelleRiferimento\StatiEsteriAnpr;
use CommSuite\Tests\SuiteTestCase;

class TabelleRiferimentoTest extends SuiteTestCase
{

    private array $fileTemporanei = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->fileTemporanei as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->fileTemporanei = [];
        parent::tearDown();
    }

    private function creaFileTemporaneo(string $contenuto, string $suffisso = '.tmp'): string
    {
        $percorso = tempnam(sys_get_temp_dir(), 'anag_test_') . $suffisso;
        file_put_contents($percorso, $contenuto);
        $this->fileTemporanei[] = $percorso;
        return $percorso;
    }

    private function creaXlsxTemporaneo(string $nomeFoglio, array $righe): string
    {
        $percorso = tempnam(sys_get_temp_dir(), 'xlsx_test_') . '.xlsx';
        $this->fileTemporanei[] = $percorso;

        $zip = new \ZipArchive();
        $zip->open($percorso, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . htmlspecialchars($nomeFoglio, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>');

        $sharedStrings = [];
        $xmlRows = '';
        foreach ($righe as $indiceRiga => $celle) {
            $numRiga = $indiceRiga + 1;
            $xmlRows .= '<row r="' . $numRiga . '">';
            foreach (array_values($celle) as $indiceColonna => $valore) {
                $colLettera = $this->letteraColonna($indiceColonna);
                $rif = $colLettera . $numRiga;
                if (is_int($valore)) {
                    $xmlRows .= '<c r="' . $rif . '"><v>' . $valore . '</v></c>';
                } else {
                    $idx = count($sharedStrings);
                    $sharedStrings[] = (string) $valore;
                    $xmlRows .= '<c r="' . $rif . '" t="s"><v>' . $idx . '</v></c>';
                }
            }
            $xmlRows .= '</row>';
        }

        $xmlShared = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        foreach ($sharedStrings as $str) {
            $xmlShared .= '<si><t>' . htmlspecialchars($str, ENT_XML1) . '</t></si>';
        }
        $xmlShared .= '</sst>';

        $zip->addFromString('xl/sharedStrings.xml', $xmlShared);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetData>' . $xmlRows . '</sheetData></worksheet>');
        $zip->close();

        return $percorso;
    }

    private function letteraColonna(int $indice): string
    {
        $lettera = '';
        $n = $indice + 1;
        while ($n > 0) {
            $resto = ($n - 1) % 26;
            $lettera = chr(65 + $resto) . $lettera;
            $n = intdiv($n - 1, 26);
        }
        return $lettera;
    }

    public function testGeneratoreSqlGeneraUpsertConEscapeENull(): void
    {
        $generatore = new GeneratoreSql();
        $colonne = ['id_anpr', 'denominazione', 'sigla_provincia'];
        $righe = [
            ['id_anpr' => 1, 'denominazione' => "L'Aquila", 'sigla_provincia' => 'AQ'],
            ['id_anpr' => 2, 'denominazione' => 'Città\\Test', 'sigla_provincia' => null],
        ];

        $istruzioni = iterator_to_array($generatore->generaUpsert('comune', $colonne, $righe));

        $this->assertCount(1, $istruzioni);
        $this->assertStringContainsString("INSERT INTO `comune` (`id_anpr`, `denominazione`, `sigla_provincia`) VALUES", $istruzioni[0]);
        $this->assertStringContainsString("(1, 'L''Aquila', 'AQ')", $istruzioni[0]);
        $this->assertStringContainsString("(2, 'Città\\\\Test', NULL)", $istruzioni[0]);
        $this->assertStringContainsString("ON DUPLICATE KEY UPDATE `denominazione` = VALUES(`denominazione`), `sigla_provincia` = VALUES(`sigla_provincia`);", $istruzioni[0]);
    }

    public function testLettoreXlsxLeggiEFoglioNonTrovato(): void
    {
        $file = $this->creaXlsxTemporaneo('FoglioTest', [
            ['CODICE', 'DESCRIZIONE'],
            ['A01', ' Voce Uno '],
            ['A02', 'Voce Due'],
        ]);

        $lettore = new LettoreXlsx();
        $righe = $lettore->leggi($file, 'FoglioTest', ['CODICE', 'DESCRIZIONE']);

        $this->assertCount(2, $righe);
        $this->assertSame(['CODICE' => 'A01', 'DESCRIZIONE' => 'Voce Uno'], $righe[0]);
        $this->assertSame(['CODICE' => 'A02', 'DESCRIZIONE' => 'Voce Due'], $righe[1]);

        $this->expectException(\UnexpectedValueException::class);
        $lettore->leggi($file, 'Inesistente', ['CODICE']);
    }

    public function testLettoreXlsxColonneMancanti(): void
    {
        $file = $this->creaXlsxTemporaneo('Foglio1', [
            ['SOLO_QUESTA'],
            ['Valore'],
        ]);

        $lettore = new LettoreXlsx();
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('colonne mancanti: RICHIESTA');
        $lettore->leggi($file, null, ['RICHIESTA']);
    }

    public function testArchivioComuniAnprLeggiValidoEdEscludeNd(): void
    {
        $linee = ["\u{FEFF}ID,DATAISTITUZIONE,DATACESSAZIONE,CODISTAT,CODCATASTALE,DENOMINAZIONE_IT,SIGLAPROVINCIA"];
        $linee[] = '99999,1861-01-01,1920-01-01,,ND,Comune Antico,RM';
        for ($i = 1; $i <= 7001; $i++) {
            $cod = sprintf('A%03d', $i % 1000);
            $linee[] = "$i,1900-01-01,9999-12-31,058001,$cod,Comune $i,RM";
        }
        $csv = $this->creaFileTemporaneo(implode("\n", $linee) . "\n\n", '.csv');

        $servizio = new ArchivioComuniAnpr();
        $righe = $servizio->leggi($csv);

        $this->assertCount(7001, $righe);
        $this->assertSame(1, $servizio->getEscluse());
        $this->assertNull($righe[1]['valido_al']);
        $this->assertSame('Comune 1', $righe[1]['denominazione']);
    }

    public function testArchivioComuniAnprRifiutaIncompleto(): void
    {
        $csv = $this->creaFileTemporaneo(
            "ID,DATAISTITUZIONE,DATACESSAZIONE,CODISTAT,CODCATASTALE,DENOMINAZIONE_IT,SIGLAPROVINCIA\n"
            . "1,1900-01-01,9999-12-31,058001,H501,Roma,RM\n",
            '.csv'
        );

        $servizio = new ArchivioComuniAnpr();
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Archivio comuni incompleto');
        $servizio->leggi($csv);
    }

    public function testStatiEsteriAnprLeggiConXlsxEIso(): void
    {
        $iso = $this->creaFileTemporaneo("ITA;IT\nFRA;FR\n", '.csv');
        $xlsx = $this->creaXlsxTemporaneo('Stati', [
            ['ID', 'DENOMINAZIONE', 'DATAINIZIOVALIDITA', 'DATAFINEVALIDITA', 'CODISO3166_1_ALPHA3', 'CODAT', 'TIPO'],
            ['1', 'ITALIA', '01/01/1900 00:00:00', '31/12/9999 00:00:00', 'ITA', '', 'STATO'],
            ['2', 'FRANCIA', '36526', '31/12/9999', 'FRA', 'Z110', 'STATO'],
            ['3', 'IGNORATO', '01/01/1900', '31/12/9999', 'XXX', 'Z999', 'NON_IMPORTATO'],
        ]);

        $servizio = new StatiEsteriAnpr();
        $righe = $servizio->leggi($xlsx, $iso);

        $this->assertCount(2, $righe);
        $this->assertSame('IT', $righe[1]['codice_iso']);
        $this->assertNull($righe[1]['codice_catastale']);
        $this->assertNull($righe[1]['valido_al']);
        $this->assertSame('FR', $righe[2]['codice_iso']);
        $this->assertSame('Z110', $righe[2]['codice_catastale']);
        $this->assertSame('2000-01-01', $righe[2]['valido_dal']);
    }

    public function testClassificazioniAtecoIstatLeggiConStubLettoreXlsx(): void
    {
        $righe2025 = [
            ['CODICE_ATECO_2025' => 'M', 'TITOLO_ITALIANO_ATECO_2025' => 'Attività professionali', 'GERARCHIA_ATECO_2025' => '1', 'CODICE_PADRE_ATECO_2025' => ''],
        ];
        $righe2022 = [
            ['CODICE_ATECO_2022' => 'M', 'TITOLO_ATECO_2022' => 'Attività professionali', 'GERARCHIA_ATECO_2022' => '1', 'CODICE_ATECO_2025_RAPPRESENTATIVO' => '', 'CODICE_ATECO_2025_RAPPRESENTATIVO_SISTEMA_CAMERALE' => ''],
        ];
        for ($i = 1; $i <= 1000; $i++) {
            $cod = sprintf('69.20.%02d', $i);
            $righe2025[] = [
                'CODICE_ATECO_2025' => $cod,
                'TITOLO_ITALIANO_ATECO_2025' => 'Contabilità ' . $i,
                'GERARCHIA_ATECO_2025' => '6',
                'CODICE_PADRE_ATECO_2025' => 'M',
            ];
            $righe2022[] = [
                'CODICE_ATECO_2022' => $cod,
                'TITOLO_ATECO_2022' => 'Contabilità 2022 ' . $i,
                'GERARCHIA_ATECO_2022' => '6',
                'CODICE_ATECO_2025_RAPPRESENTATIVO' => $cod,
                'CODICE_ATECO_2025_RAPPRESENTATIVO_SISTEMA_CAMERALE' => '',
            ];
        }

        $lettoreStub = $this->createStub(LettoreXlsx::class);
        $lettoreStub->method('leggi')->willReturnOnConsecutiveCalls($righe2025, $righe2022);

        $servizio = new ClassificazioniAtecoIstat($lettoreStub);
        $risultato = $servizio->leggi('ateco2025.xlsx', 'ateco2022.xlsx');

        $this->assertCount(2002, $risultato);
        $this->assertSame('2022', $risultato[1]['classificazione']);
        $this->assertSame('69.20.01', $risultato[1]['codice_raccordo']);
    }
}
