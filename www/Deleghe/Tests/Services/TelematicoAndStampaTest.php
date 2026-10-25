<?php

namespace Deleghe\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Intermediario;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\CodiceIdentificativo;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\ObbligoCampo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Services\EsitoControllo;
use Deleghe\Application\Services\ModelloCompilato;
use Deleghe\Application\Services\Stampa\ModuloUfficialeF24;
use Deleghe\Application\Services\TabelleRiferimento\ArchivioTabelleF24;
use Deleghe\Application\Services\TabelleRiferimento\FonteAgenzia;
use Deleghe\Application\Services\TabelleRiferimento\LettoreFoglio;
use Deleghe\Application\Services\TabelleRiferimento\LettoreTabellaHtml;
use Deleghe\Application\Services\TabelleRiferimento\SorgentiModulo;
use Deleghe\Application\Services\TabelleRiferimento\TassiRiferimentoBce;
use Deleghe\Application\Services\TabelleRiferimento\UfficiAgenzia;
use Deleghe\Application\Services\Telematico\GeneratoreFileF24;
use Deleghe\Application\Services\Telematico\RecordF24;
use Deleghe\Application\Services\Telematico\TracciatoF24;
use SismaFramework\Orm\CustomTypes\SismaDate;

class TelematicoAndStampaTest extends SuiteTestCase
{

    private function creaPersonaFisicaCompleta(int $id = 1, string $cf = 'RSSMRA80A01H501U'): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = $id;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = $cf;
        $soggetto->cognome = 'Rossi';
        $soggetto->nome = 'Mario';
        $soggetto->nominativo = 'Rossi Mario';
        $soggetto->sesso = Sesso::from('M');
        $soggetto->dataNascita = new SismaDate('1980-01-01');
        $comune = new Comune($this->dataMapper);
        $comune->id = 10;
        $comune->codiceCatastale = 'H501';
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $soggetto->comuneNascita = $comune;
        return $soggetto;
    }

    public function testRecordF24AndTracciatoF24(): void
    {
        $record = new RecordF24(TracciatoF24::RECORD_Z);
        $record->imposta('tipoRecord', 'Z')
                ->imposta('numeroRecordV', 3)
                ->imposta('numeroRecordM', 2);

        $str = (string) $record;
        $this->assertSame(1900, strlen($str));
        $this->assertStringStartsWith('Z', $str);
        $this->assertStringEndsWith("A\r\n", $str);
        $this->assertSame('000000003', substr($str, 15, 9));
        $this->assertSame('000000002', substr($str, 24, 9));

        $ascii = RecordF24::ascii('  Città perché  ');
        $this->assertMatchesRegularExpression('/^[\x20-\x7E]+$/', $ascii);
        $this->assertStringContainsString('CITT', $ascii);

        $recA = new RecordF24(TracciatoF24::RECORD_A);
        $recA->impostaTroncato('cognome', 'COGNOMELUNGHISSIMOCHESUPERAVENTIQUATTROCARATTERI');
        $this->assertSame(24, strlen(trim(substr((string) $recA, 38, 24))));

        // Tracciato recordV per i diversi modelli
        $campiOrd = TracciatoF24::recordV(ModelloF24::ordinario);
        $this->assertArrayHasKey('ERA.1.codiceTributo', $campiOrd);
        $this->assertArrayHasKey('PRE.codiceEnte', $campiOrd);

        $campiElide = TracciatoF24::recordV(ModelloF24::elide);
        $this->assertArrayHasKey('ELI.1.elementiIdentificativi', $campiElide);
        $this->assertArrayHasKey('ELI.totaleDebito', $campiElide);

        $campiAccise = TracciatoF24::recordV(ModelloF24::accise);
        $this->assertArrayHasKey('ACC.1.codiceTributo', $campiAccise);
        $this->assertArrayHasKey('ACC.codiceUfficio', $campiAccise);

        // Errori RecordF24
        try {
            $record->imposta('campoInesistente', 'X');
            $this->fail('Attesa InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Campo sconosciuto', $e->getMessage());
        }

        try {
            $record->imposta('numeroRecordV', 'ABC');
            $this->fail('Attesa InvalidArgumentException per valore non numerico');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('valore numerico non valido', $e->getMessage());
        }

        try {
            $record->imposta('tipoRecord', 'ZZ');
            $this->fail('Attesa LengthException');
        } catch (\LengthException $e) {
            $this->assertStringContainsString('supera i 1 caratteri', $e->getMessage());
        }
    }

    public function testGeneratoreFileF24(): void
    {
        $soggetto = $this->creaPersonaFisicaCompleta();
        $conto = new ContoCorrente($this->dataMapper);
        $conto->id = 1;
        $conto->soggetto = $soggetto;
        $conto->iban = 'IT60X0542811101000000123456';
        $conto->attivo = true;

        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->id = 1;
        $intermediario->studio = $this->studioPredefinito;
        $intermediario->soggetto = $soggetto;
        $intermediario->telefono = '0612345678';
        $intermediario->email = 'studio@esempio.it';
        $intermediario->contoAddebito = $conto;
        $intermediario->predefinito = true;

        $delega = new Delega($this->dataMapper);
        $delega->id = 10;
        $delega->studio = $this->studioPredefinito;
        $delega->soggetto = $soggetto;
        $delega->modello = ModelloF24::ordinario;
        $delega->scadenza = new SismaDate('2026-06-16');
        $delega->stato = StatoDelega::confermata;
        $delega->contoAddebito = $conto;
        $delega->detrazioneImu = 200.0;

        $ctErario = new CodiceTributo($this->dataMapper);
        $ctErario->id = 1;
        $ctErario->sezione = SezioneCodiceTributo::erario;
        $ctErario->codice = '4001';
        $ctErario->modalita = ModalitaUtilizzo::entrambi;
        $ctErario->formatoRateazione = 'NNRR';
        $ctErario->annoRichiesto = true;
        $ctErario->codiceUfficio = ObbligoCampo::nonPrevisto;
        $ctErario->codiceAtto = ObbligoCampo::nonPrevisto;
        $ctErario->identificativoOperazione = ObbligoCampo::nonPrevisto;

        $rigo = new Versamento($this->dataMapper);
        $rigo->id = 100;
        $rigo->sezione = SezioneF24::erario;
        $rigo->codiceTributo = $ctErario;
        $rigo->rateazione = '0101';
        $rigo->annoRiferimento = 2025;
        $rigo->importoDebito = 250.0;
        $rigo->importoCredito = 0.0;

        $compilato = new ModelloCompilato();
        $compilato->aggiungi($rigo, 25000, 0);

        $esito = new EsitoControllo();
        $esito->debito = 25000;
        $esito->credito = 0;
        $esito->modelli = [$compilato];

        $generatore = new GeneratoreFileF24($this->dataMapper);
        $this->assertSame([], $generatore->verificaIntermediario($intermediario, true));
        $this->assertSame([], $generatore->verificaDelega($delega, $esito, false));

        $ind = new Indirizzo($this->dataMapper);
        $ind->id = 1;
        $ind->tipoIndirizzo = TipoIndirizzo::domicilioFiscale;
        $ind->comune = $soggetto->comuneNascita;
        $ind->indirizzo = 'Via Nazionale';
        $ind->numeroCivico = '1';
        $ind->cap = '00184';
        $ind->validoDal = new SismaDate('2020-01-01');

        // getDomicilio per recordA e per recordM
        $this->testAdapter->accodaSelect(
                new TestResultSet([$ind]),
                new TestResultSet([$ind]),
        );
        $flusso = $generatore->genera($intermediario, [['delega' => $delega, 'esito' => $esito]], false);
        $linee = explode("\r\n", rtrim($flusso, "\r\n"));
        $this->assertCount(4, $linee); // A, M, V, Z
        $this->assertStringStartsWith('A', $linee[0]);
        $this->assertStringStartsWith('M', $linee[1]);
        $this->assertStringStartsWith('V', $linee[2]);
        $this->assertStringStartsWith('Z', $linee[3]);
    }

    public function testModuloUfficialeF24(): void
    {
        $soggetto = $this->creaPersonaFisicaCompleta();
        $conto = new ContoCorrente($this->dataMapper);
        $conto->id = 1;
        $conto->soggetto = $soggetto;
        $conto->iban = 'IT60X0542811101000000123456';

        $ind = new Indirizzo($this->dataMapper);
        $ind->id = 1;
        $ind->tipoIndirizzo = TipoIndirizzo::domicilioFiscale;
        $ind->comune = $soggetto->comuneNascita;
        $ind->indirizzo = 'Via Roma';
        $ind->numeroCivico = '10';
        $ind->cap = '00100';
        $ind->validoDal = new SismaDate('2020-01-01');

        $ct = new CodiceTributo($this->dataMapper);
        $ct->codice = '3918';
        $ct->sezione = SezioneCodiceTributo::tributiLocali;
        $ct->codiceUfficio = ObbligoCampo::nonPrevisto;
        $ct->codiceAtto = ObbligoCampo::nonPrevisto;
        $ct->enteAccise = 'D';

        $rigoImu = new Versamento($this->dataMapper);
        $rigoImu->sezione = SezioneF24::tributiLocali;
        $rigoImu->codiceTributo = $ct;
        $rigoImu->codiceEnte = 'H501';
        $rigoImu->acconto = true;
        $rigoImu->numeroImmobili = 1;
        $rigoImu->annoRiferimento = 2026;
        $rigoImu->importoDebito = 300.0;

        foreach (ModelloF24::cases() as $tipoModello) {
            $delega = new Delega($this->dataMapper);
            $delega->id = 1;
            $delega->studio = $this->studioPredefinito;
            $delega->soggetto = $soggetto;
            $delega->modello = $tipoModello;
            $delega->scadenza = new SismaDate('2026-06-16');
            $delega->contoAddebito = $conto;
            $delega->detrazioneImu = 100.0;
            $delega->codiceUfficio = 'TJT';
            $delega->codiceAtto = '12345678901';
            $delega->identificativoOperazione = 'OP123';
            $delega->codiceFiscaleCoobbligato = 'BNCLRA85B41H501W';
            $delega->codiceIdentificativo = CodiceIdentificativo::erede;

            $compilato = new ModelloCompilato();
            $compilato->conDetrazioneImu = true;
            if ($tipoModello === ModelloF24::elide) {
                $rElide = clone $rigoImu;
                $rElide->sezione = SezioneF24::elide;
                $rElide->tipoElide = 'F';
                $rElide->elementiIdentificativi = 'ELIDE123';
                $compilato->aggiungi($rElide, 30000, 0);
            } elseif ($tipoModello === ModelloF24::accise) {
                $rAcc = clone $rigoImu;
                $rAcc->sezione = SezioneF24::accise;
                $rAcc->provincia = 'RM';
                $rAcc->codiceIdentificativoEnte = 'DITTA1';
                $rAcc->meseRiferimento = 6;
                $compilato->aggiungi($rAcc, 30000, 0);
            } else {
                $compilato->aggiungi($rigoImu, 30000, 0);
            }

            $modulo = new ModuloUfficialeF24($delega, $soggetto, $ind);
            $pagineSingola = $modulo->getPagine($compilato, false);
            $this->assertCount(1, $pagineSingola);
            $this->assertNotEmpty($pagineSingola[0]['campi']);

            $pagineTutte = $modulo->getPagine($compilato, true);
            $this->assertCount(ModuloUfficialeF24::PAGINE[$tipoModello->value], $pagineTutte);
        }
    }

    public function testTabelleRiferimentoServices(): void
    {
        // LettoreFoglio::normalizza (con riparazione testo corrotto Windows-1252 / halfwidth)
        $this->assertSame('Testo normale', LettoreFoglio::normalizza("  Testo\u{A0}  normale "));
        $this->assertNotEmpty(LettoreFoglio::normalizza("dï¿‚ï¾’imposta"));

        // LettoreTabellaHtml
        $html = '<html><body><table><thead><tr><th>Settore</th><th>Codice</th><th>Descrizione</th></tr></thead>'
                . '<tbody><tr><td>D</td><td>TJT</td><td>Direzione Provinciale I Roma</td></tr></tbody></table></body></html>';
        $lettoreHtml = new LettoreTabellaHtml();
        $righeHtml = $lettoreHtml->leggi($html, ['Settore', 'Codice', 'Descrizione'], 'test');
        $this->assertCount(1, $righeHtml);
        $this->assertSame('TJT', $righeHtml[0]['Codice']);

        // SorgentiModulo (legge i veri CSV del modulo in Database/sorgenti/)
        $sorgenti = new SorgentiModulo();
        $tassi = $sorgenti->leggiTassi();
        $this->assertNotEmpty($tassi);
        $this->assertArrayHasKey('valido_dal', $tassi[0]);

        // TassiRiferimentoBce su file temporaneo CSV
        $csvBce = sys_get_temp_dir() . '/test-bce-' . uniqid() . '.csv';
        $lineeBce = ["KEY,TIME_PERIOD,OBS_VALUE"];
        for ($i = 1; $i <= 22; $i++) {
            $lineeBce[] = sprintf("FM,%04d-%02d-01,%.2f", 2008 + intdiv($i, 2), ($i % 12) + 1, 1.0 + ($i * 0.25));
        }
        file_put_contents($csvBce, implode("\n", $lineeBce));
        try {
            $tassiBce = (new TassiRiferimentoBce())->leggi($csvBce);
            $this->assertCount(22, $tassiBce);
        } finally {
            @unlink($csvBce);
        }

        // UfficiAgenzia con mock di FonteAgenzia
        $righeUfficiHtml = '';
        for ($i = 0; $i < 305; $i++) {
            $cod = strtoupper(str_pad(base_convert((string) ($i + 100), 10, 36), 3, '0', STR_PAD_LEFT));
            $righeUfficiHtml .= "<tr><td>T</td><td>{$cod}</td><td>Ufficio {$cod}</td></tr>";
        }
        $htmlUffici = '<html><body><table><thead><tr><th>Settore</th><th>Codice</th><th>Descrizione</th></tr></thead><tbody>'
                . $righeUfficiHtml . '</tbody></table></body></html>';
        $fonteStub = new class extends FonteAgenzia {
            public string $html = '';
            public function leggi(string $pagina, array $parametri, bool $post = false): string
            {
                return $this->html;
            }
        };
        $fonteStub->html = $htmlUffici;
        $uffici = (new UfficiAgenzia($fonteStub, $lettoreHtml))->leggi();
        $this->assertGreaterThanOrEqual(300, count($uffici));

        // ArchivioTabelleF24 su cartella locale
        $dirArchivio = sys_get_temp_dir() . '/TabF24_20260301_' . uniqid();
        mkdir($dirArchivio, 0775, true);
        file_put_contents($dirArchivio . '/TAB_ERARIO.xls', 'dummy');
        try {
            $archivio = new ArchivioTabelleF24();
            $archivio->prepara($dirArchivio);
            $this->assertSame('20260301', $archivio->getData());
            $this->assertStringEndsWith('TAB_ERARIO.xls', $archivio->file('/^TAB_ERARIO\.xls$/'));
            $this->assertCount(1, $archivio->tuttiIFile('/\.xls$/'));
            $archivio->pulisci();
        } finally {
            @unlink($dirArchivio . '/TAB_ERARIO.xls');
            @rmdir($dirArchivio);
        }
    }
}
