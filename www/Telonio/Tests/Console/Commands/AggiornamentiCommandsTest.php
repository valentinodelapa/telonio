<?php

namespace Telonio\Tests\Console\Commands;

use Telonio\Application\Enumerations\CanaleAggiornamenti;
use Telonio\Application\Services\Aggiornamenti\ArchivioRelease;
use Telonio\Application\Services\Aggiornamenti\ControlloAggiornamenti;
use Telonio\Application\Services\Aggiornamenti\DatabaseAmministrazione;
use Telonio\Application\Services\Aggiornamenti\Release;
use Telonio\Application\Services\Aggiornamenti\Versione;
use Telonio\Console\Commands\AggiornaDatabaseCommand;
use Telonio\Console\Commands\AggiornamentiCommand;
use PHPUnit\Framework\TestCase;
use SismaFramework\Console\BaseClasses\BaseCommand;

class AggiornamentiCommandsTest extends TestCase
{

    private array $percorsiTemporanei = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach (array_reverse($this->percorsiTemporanei) as $percorso) {
            is_dir($percorso) ? rmdir($percorso) : (is_file($percorso) && unlink($percorso));
        }
        $this->percorsiTemporanei = [];
    }

    private function percorsoTemporaneo(): string
    {
        $percorso = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'telonio_test_' . bin2hex(random_bytes(6));
        $this->percorsiTemporanei[] = $percorso;
        return $percorso;
    }

    /**
     * @return array{esito: bool, output: string}
     */
    private function esegui(BaseCommand $comando, array $options = []): array
    {
        $comando->setArguments([]);
        $comando->setOptions($options);
        ob_start();
        try {
            $esito = $comando->run();
            $output = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ['esito' => $esito, 'output' => $output];
    }

    private function creaControllo(array $release): ControlloAggiornamenti
    {
        $archivio = $this->createStub(ArchivioRelease::class);
        $archivio->method('getRelease')->willReturn($release);
        return new ControlloAggiornamenti($archivio, Versione::daTesto('0.9.0-alpha'), CanaleAggiornamenti::alpha, $this->percorsoTemporaneo(), true);
    }

    private function creaRelease(string $versione): Release
    {
        return new Release(Versione::daTesto($versione), 'v' . $versione, 'https://github.com/valentinodelapa/telonio/releases/tag/v' . $versione,
                '2026-11-02', 'https://github.com/valentinodelapa/telonio/releases/download/v' . $versione . '/telonio-v' . $versione . '.zip',
                str_repeat('c', 64), 'https://github.com/valentinodelapa/telonio/releases/download/v' . $versione . '/telonio-v' . $versione . '.zip.intoto.jsonl');
    }

    public function testAggiornamentiEsitoVariabiliECanaleNonValido(): void
    {
        $comando = new AggiornamentiCommand($this->creaControllo([$this->creaRelease('0.10.0-alpha'), $this->creaRelease('0.9.1')]));
        $this->assertTrue($comando->checkCompatibility('aggiornamenti'));

        $esito = $this->esegui($comando);
        $this->assertTrue($esito['esito']);
        $this->assertStringContainsString('Versione installata: 0.9.0-alpha (canale alpha)', $esito['output']);
        $this->assertStringContainsString('Disponibile: 0.10.0-alpha del 2026-11-02', $esito['output']);
        $this->assertStringContainsString('sh aggiorna.sh', $esito['output']);

        $shell = $this->esegui($comando, ['formato' => 'shell']);
        $this->assertStringContainsString("VERSIONE_INSTALLATA=0.9.0-alpha\nCANALE=alpha\nVERSIONE=0.10.0-alpha\nTAG=v0.10.0-alpha\n", str_replace("\r\n", "\n", $shell['output']));
        $this->assertStringContainsString('SHA256=' . str_repeat('c', 64), $shell['output']);
        $this->assertStringContainsString('ATTESTAZIONE=https://github.com/valentinodelapa/telonio/releases/download/v0.10.0-alpha/telonio-v0.10.0-alpha.zip.intoto.jsonl', $shell['output']);

        $stabile = $this->esegui($comando, ['formato' => 'shell', 'canale' => 'stabile']);
        $this->assertStringContainsString('VERSIONE=0.9.1' . PHP_EOL, $stabile['output']);

        $nessuno = $this->esegui(new AggiornamentiCommand($this->creaControllo([])), ['formato' => 'shell']);
        $this->assertStringContainsString('VERSIONE=' . PHP_EOL . 'TAG=' . PHP_EOL, $nessuno['output']);
        $this->assertStringContainsString('Nessun aggiornamento disponibile', $this->esegui(new AggiornamentiCommand($this->creaControllo([])))['output']);

        $errato = $this->esegui($comando, ['canale' => 'nightly']);
        $this->assertFalse($errato['esito']);
        $this->assertStringContainsString('Canale non valido: nightly', $errato['output']);
    }

    private function creaModulo(array $file): string
    {
        $cartella = $this->percorsoTemporaneo();
        mkdir($cartella);
        foreach ($file as $script => $contenuto) {
            $percorso = $cartella . DIRECTORY_SEPARATOR . $script;
            file_put_contents($percorso, $contenuto);
            $this->percorsiTemporanei[] = $percorso;
        }
        return $cartella;
    }

    public function testAggiornaDatabaseEseguePianoRegistraEliminaCache(): void
    {
        $moduli = [
            'Base' => $this->creaModulo(['schema.sql' => 'CREATE TABLE IF NOT EXISTS `studio` (id INT);']),
            'Anagrafica' => $this->creaModulo([
                'schema.sql' => 'CREATE TABLE IF NOT EXISTS `dati_fiscali` (id INT);',
                'aggiornamento-0.5.sql' => 'ALTER TABLE `dati_fiscali` ADD COLUMN IF NOT EXISTS x INT; SELECT ...;',
                'dati-riferimento.sql' => 'INSERT INTO comune ...;',
            ]),
        ];
        $eseguiti = [];
        $registrati = [];
        $database = $this->createStub(DatabaseAmministrazione::class);
        $database->method('getDatabase')->willReturn('telonio');
        $database->method('getTabelle')->willReturn(['studio', 'dati_fiscali']);
        $database->method('getScriptEseguiti')->willReturn([]);
        $database->method('esegui')->willReturnCallback(function (string $sql) use (&$eseguiti) {
            $eseguiti[] = $sql;
            return str_contains($sql, 'SELECT') ? [['colonne' => ['studio', 'nominativo'], 'righe' => [['Studio Demo', null]]]] : [];
        });
        $database->method('registra')->willReturnCallback(function (string $modulo, string $script) use (&$registrati) {
            $registrati[] = $modulo . '/' . $script;
        });
        $cache = $this->percorsoTemporaneo();
        file_put_contents($cache, '{}');
        $comando = new AggiornaDatabaseCommand($database, moduli: $moduli, fileCache: $cache);
        $this->assertTrue($comando->checkCompatibility('aggiorna-database'));

        $simulazione = $this->esegui($comando, ['simula' => true]);
        $this->assertTrue($simulazione['esito']);
        $this->assertStringContainsString('esegue    Anagrafica/Database/aggiornamento-0.5.sql', $simulazione['output']);
        $this->assertStringContainsString('registra  Anagrafica/Database/aggiornamento-0.5.sql', $simulazione['output']);
        $this->assertSame([], $eseguiti);
        $this->assertFileExists($cache);

        $esito = $this->esegui($comando);
        $this->assertTrue($esito['esito'], $esito['output']);
        $this->assertCount(4, $eseguiti);
        $this->assertSame(['Anagrafica/aggiornamento-0.5.sql'], $registrati);
        $this->assertStringContainsString('    studio | nominativo' . PHP_EOL . '    Studio Demo | NULL', $esito['output']);
        $this->assertStringContainsString('Database aggiornato.', $esito['output']);
        $this->assertFileDoesNotExist($cache);
    }

    public function testAggiornaDatabaseSiFermaAlPrimoErrore(): void
    {
        $moduli = ['Anagrafica' => $this->creaModulo([
                'schema.sql' => 'CREATE TABLE IF NOT EXISTS `soggetto` (id INT);',
                'aggiornamento-0.5.sql' => 'ALTER TABLE errata;',
                'dati-riferimento.sql' => 'INSERT ...;',
        ])];
        $database = $this->createStub(DatabaseAmministrazione::class);
        $database->method('getTabelle')->willReturn(['soggetto']);
        $database->method('getScriptEseguiti')->willReturn([]);
        $database->method('esegui')->willThrowException(new \RuntimeException('Errore all\'istruzione n. 1: Table \'errata\' doesn\'t exist'));
        $database->method('registra')->willThrowException(new \LogicException('non deve registrare'));
        $cache = $this->percorsoTemporaneo();
        file_put_contents($cache, '{}');

        $esito = $this->esegui(new AggiornaDatabaseCommand($database, moduli: $moduli, fileCache: $cache));

        $this->assertFalse($esito['esito']);
        $this->assertStringContainsString('Errore: Anagrafica/Database/aggiornamento-0.5.sql: Errore all\'istruzione n. 1', $esito['output']);
        $this->assertFileExists($cache, 'la cache resta finché l\'aggiornamento non è completato');
    }
}
