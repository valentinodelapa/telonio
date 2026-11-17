<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Stampa\LettoreModelloPdf;
use Dichiarativi\Application\Services\Stampa\PdfDichiarazione;
use setasign\Fpdi\Fpdi;
use Telonio\Tests\SuiteTestCase;

/**
 * Stampa sul modello ufficiale: lettura delle posizioni dei campi da una
 * pagina di prova disegnata come quelle dei modelli (righi separati da
 * linee, sigla a sinistra, numeri di colonna nell'angolo delle caselle
 * bianche, ",00" degli importi), rifinitura delle posizioni (celle dalle
 * tacche verticali, importi al ",00" o alla virgola dei centesimi) e PDF
 * con i valori nelle caselle e l'elenco dei campi senza posizione.
 */
class StampaModelloTest extends SuiteTestCase
{

    private string $cartella;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        if (trim((string) shell_exec('command -v pdftocairo')) === '') {
            $this->markTestSkipped('poppler-utils non disponibile');
        }
        $this->cartella = sys_get_temp_dir() . '/stampa-modello-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->cartella);
    }

    #[\Override]
    protected function tearDown(): void
    {
        array_map('unlink', glob($this->cartella . '*') ?: []);
        @rmdir($this->cartella);
        parent::tearDown();
    }

    /**
     * Pagina con il rigo RN1 (quattro importi numerati) e il rigo RN2 (un
     * importo senza numero di colonna).
     */
    private function creaPagina(string $file): void
    {
        $pdf = new Fpdi('P', 'pt', [LettoreModelloPdf::LARGHEZZA, LettoreModelloPdf::ALTEZZA]);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetFillColor(190, 200, 225);
        $pdf->Rect(100, 100, 460, 120, 'F');
        $pdf->SetDrawColor(20, 60, 110);
        $pdf->SetLineWidth(0.5);
        foreach ([100, 160, 200] as $y) {
            $pdf->Line(106, $y, 560, $y);
        }
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Text(107, 140, 'RN1');
        $pdf->Text(107, 185, 'RN2');
        $pdf->SetFillColor(255, 255, 255);
        foreach ([1 => 187.0, 2 => 266.0, 3 => 338.0, 4 => 410.0] as $colonna => $x) {
            $pdf->Rect($x, 130, 64.7, 12, 'F');
            $pdf->SetFont('Helvetica', '', 5);
            $pdf->Text($x + 1, 135, (string) $colonna);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->Text($x + 55, 140, ',00');
        }
        $pdf->Rect(480, 175, 79, 12, 'F');
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->Text(549, 185, ',00');
        $pdf->Output('F', $file);
    }

    public function testPosizioniDaiNumeriDiColonnaEDagliImporti(): void
    {
        $this->creaPagina($this->cartella . 'pagina.pdf');
        $posizioni = (new LettoreModelloPdf(['RN001001', 'RN001002', 'RN001003', 'RN001004', 'RN002001']))->leggi($this->cartella . 'pagina.pdf', '1');
        $perCodice = array_column($posizioni, null, 'codice');
        $this->assertSame(['RN001001', 'RN001002', 'RN001003', 'RN001004', 'RN002001'], array_values(array_intersect(['RN001001', 'RN001002', 'RN001003', 'RN001004', 'RN002001'], array_keys($perCodice))));
        // casella bianca della colonna 2: dal bordo sinistro al ",00", importo allineato a destra
        $this->assertEqualsWithDelta(266.0, $perCodice['RN001002']['x'], 0.5);
        $this->assertEqualsWithDelta(130.0, $perCodice['RN001002']['y'], 0.5);
        $this->assertLessThan(64.7, $perCodice['RN001002']['larghezza']);
        $this->assertTrue($perCodice['RN001002']['destra']);
        // rigo con un solo importo senza numero: colonna 1, nel rettangolo bianco
        $this->assertEqualsWithDelta(480.0, $perCodice['RN002001']['x'], 1.0);
        $this->assertTrue($perCodice['RN002001']['destra']);
    }

    /**
     * Pagina più larga di un A4 (come quelle del modello IVA TR) con: una
     * casella divisa in cinque celle da tacche verticali; un importo con il
     * numero di colonna sopra e il ",00" sotto (come nel rigo VF29); un
     * importo con i centesimi (virgola stampata e una tacca tra le due
     * celle dei centesimi). Con $centesimi = false il solo ",00", con true
     * la sola virgola.
     */
    private function creaPaginaCelle(string $file, bool $centesimi): void
    {
        $pdf = new Fpdi('P', 'pt', [602.5, LettoreModelloPdf::ALTEZZA]);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetDrawColor(150, 110, 200);
        $pdf->SetLineWidth(0.4);
        foreach ([214.4, 228.8, 243.2, 257.6] as $x) {
            $pdf->Line($x, 110, $x, 118);
        }
        $pdf->SetFont('Helvetica', '', 7);
        if ($centesimi) {
            $pdf->Text(424.3, 160, ',');
            $pdf->Line(438.2, 156, 438.2, 160);
        } else {
            $pdf->Text(300, 172, ',00');
        }
        $pdf->Output('F', $file);
    }

    public function testRifinituraCelleEImporti(): void
    {
        $this->creaPaginaCelle($this->cartella . 'celle.pdf', false);
        $lettore = new LettoreModelloPdf([]);
        $campi = array_column($lettore->rifinisci($this->cartella . 'celle.pdf', 1, [
            ['codice' => '#codiceAttivita', 'x' => 200.0, 'y' => 106.0, 'larghezza' => 72.0, 'altezza' => 12.0, 'celle' => [], 'destra' => false],
            ['codice' => 'VF029001', 'x' => 220.0, 'y' => 158.0, 'larghezza' => 90.0, 'altezza' => 12.0, 'celle' => [], 'destra' => false],
        ]), null, 'codice');
        // cinque celle: le tacche e i bordi esterni larghi come le celle vicine
        $this->assertEqualsWithDelta([200.0, 214.4, 228.8, 243.2, 257.6, 272.0], $campi['#codiceAttivita']['celle'], 0.3);
        // importo con il ",00" sotto la casella letta: finisce al ",00" e ne prende la linea
        $this->assertTrue($campi['VF029001']['destra']);
        $this->assertEqualsWithDelta(80.0, $campi['VF029001']['larghezza'], 1.0);
        $this->assertGreaterThan(158.0, $campi['VF029001']['y']);
        $this->assertSame(0.0, $campi['VF029001']['virgola']);

        $this->creaPaginaCelle($this->cartella . 'centesimi.pdf', true);
        $campo = $lettore->rifinisci($this->cartella . 'centesimi.pdf', 1, [
            ['codice' => 'TA030001', 'x' => 365.7, 'y' => 150.0, 'larghezza' => 86.3, 'altezza' => 12.0, 'celle' => [], 'destra' => false],
        ])[0];
        // virgola stampata: la parte intera prima, i centesimi in due celle fino alla fine della casella
        $this->assertEqualsWithDelta(424.3, $campo['virgola'], 0.5);
        $this->assertCount(3, $campo['celle']);
        $this->assertEqualsWithDelta(438.2, $campo['celle'][1], 0.5);
        $this->assertEqualsWithDelta(452.0, $campo['x'] + $campo['larghezza'], 1.0);
    }

    public function testPdfConCaratteriNelleCelleECentesimi(): void
    {
        $this->creaPaginaCelle($this->cartella . 'TR-2025-1.pdf', true);
        $celle = implode(' ', array_map(fn(int $i) => 100 + (10 * $i), range(0, 16)));
        file_put_contents($this->cartella . 'TR-2025.csv', "codice;fascicolo;pagina;x;y;larghezza;altezza;celle;destra;virgola\n"
            . "#CF;1;1;100;60;160;12;\"" . $celle . "\";0;0\n"
            . "TA030001;1;1;365.7;150.5;86.3;12;\"424.3 438.2 452\";1;424.3\n");
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 101;
        $soggetto->studio = $this->creaStudio(1);
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::ivaTr;
        $dichiarazione->anno = 2025;
        $dichiarazione->trimestre = 1;
        $campo = new CampoModello($this->dataMapper);
        $campo->modello = ModelloDichiarativo::ivaTr;
        $campo->anno = 2025;
        $campo->codice = 'TA030001';
        $campo->quadro = 'TA';
        $campo->descrizione = 'Operazioni non imponibili';
        $campo->formato = FormatoCampo::centesimi;
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper, ['TA030001' => $campo]);
        $quadri->imposta('TA030001', '50000.25');
        file_put_contents($this->cartella . 'stampa.pdf', (new PdfDichiarazione($this->dataMapper, $this->cartella))->genera($dichiarazione, $quadri));
        // pagina delle dimensioni del modello, senza ridimensionare
        $this->assertMatchesRegularExpression('~Page size:\s+602\.5 x 841\.89~', (string) shell_exec('pdfinfo ' . escapeshellarg($this->cartella . 'stampa.pdf')));
        preg_match_all('~<word xMin="([\d.]+)" yMin="[\d.]+" xMax="([\d.]+)" yMax="[\d.]+">([^<]*)</word>~', (string) shell_exec('pdftotext -bbox ' . escapeshellarg($this->cartella . 'stampa.pdf') . ' -'), $parole, PREG_SET_ORDER);
        $centri = [];
        $fini = [];
        foreach ($parole as [, $da, $a, $testo]) {
            $centri[$testo][] = ((float) $da + (float) $a) / 2;
            $fini[$testo][] = (float) $a;
        }
        // codice fiscale: un carattere al centro di ogni cella
        $this->assertEqualsWithDelta(105.0, $centri['R'][0], 0.6);
        $this->assertEqualsWithDelta(255.0, $centri['U'][0], 0.6);
        // importo con i centesimi: la parte intera prima della virgola, i centesimi nelle loro celle (le ultime cifre
        // "2" e "5" della pagina, dopo quelle del codice fiscale)
        $this->assertArrayHasKey('50.000', $fini);
        $this->assertLessThan(424.3, $fini['50.000'][0]);
        $this->assertEqualsWithDelta((424.3 + 438.2) / 2, max($centri['2'] ?? [0.0]), 0.6);
        $this->assertEqualsWithDelta((438.2 + 452) / 2, max($centri['5'] ?? [0.0]), 0.6);
    }

    public function testPdfConIValoriELElencoDeiCampiSenzaPosizione(): void
    {
        $this->creaPagina($this->cartella . 'RPF-2026-1.pdf');
        file_put_contents($this->cartella . 'RPF-2026.csv', "codice;fascicolo;pagina;x;y;larghezza;altezza;celle;destra\nRN001002;1;1;266;130;54;12;;1\nRN002001;1;1;480;175;68;12;;1\n");
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 100;
        $soggetto->studio = $this->creaStudio(1);
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::redditiPf;
        $dichiarazione->anno = 2025;
        $catalogo = [];
        foreach (['RN001002' => 'Credito per fondi comuni', 'RN002001' => 'Deduzione per abitazione principale', 'RX001001' => 'IRPEF a debito'] as $codice => $descrizione) {
            $campo = new CampoModello($this->dataMapper);
            $campo->modello = ModelloDichiarativo::redditiPf;
            $campo->anno = 2026;
            $campo->codice = $codice;
            $campo->quadro = substr($codice, 0, 2);
            $campo->descrizione = $descrizione;
            $campo->formato = FormatoCampo::positivo;
            $catalogo[$codice] = $campo;
        }
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper, $catalogo);
        $quadri->imposta('RN001002', 1234);
        $quadri->imposta('RX001001', 50);
        $stampa = new PdfDichiarazione($this->dataMapper, $this->cartella);
        $this->assertTrue($stampa->isDisponibile($dichiarazione));
        file_put_contents($this->cartella . 'stampa.pdf', $stampa->genera($dichiarazione, $quadri));
        $testo = (string) shell_exec('pdftotext -layout ' . escapeshellarg($this->cartella . 'stampa.pdf') . ' -');
        // pagina del quadro con l'importo nella casella, poi l'elenco con il campo senza posizione
        $this->assertSame(2, substr_count($testo, "\f"));
        $this->assertStringContainsString('1.234', $testo);
        $this->assertStringContainsString('Campi compilati senza una casella nel modello ufficiale', $testo);
        $this->assertMatchesRegularExpression('~RX001001\s+IRPEF a debito\s+50~', $testo);
        $this->assertSame('RPF_RSSMRA80A01H501U_2025.pdf', PdfDichiarazione::getNomeFile($dichiarazione));
    }
}
