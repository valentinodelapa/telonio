<?php

namespace Telonio\Tests\Application\Services;

use Telonio\Application\Enumerations\CanaleAggiornamenti;
use Telonio\Application\Services\Aggiornamenti\ArchivioRelease;
use Telonio\Application\Services\Aggiornamenti\ControlloAggiornamenti;
use Telonio\Application\Services\Aggiornamenti\PianoAggiornamentoDatabase;
use Telonio\Application\Services\Aggiornamenti\Release;
use Telonio\Application\Services\Aggiornamenti\Versione;
use PHPUnit\Framework\TestCase;

class AggiornamentiTest extends TestCase
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

    private function creaRelease(string $versione, string $data = '2026-10-29'): Release
    {
        return new Release(Versione::daTesto($versione), 'v' . $versione, 'https://github.com/valentinodelapa/telonio/releases/tag/v' . $versione,
                $data, 'https://github.com/valentinodelapa/telonio/releases/download/v' . $versione . '/telonio-v' . $versione . '.zip',
                str_repeat('a', 64), null);
    }

    private function creaControllo(string $installata, ?CanaleAggiornamenti $canale = null, ?ArchivioRelease $archivio = null, ?string $fileCache = null): ControlloAggiornamenti
    {
        return new ControlloAggiornamenti($archivio ?? $this->createStub(ArchivioRelease::class), Versione::daTesto($installata), $canale,
                $fileCache ?? $this->percorsoTemporaneo(), true);
    }

    private function percorsoTemporaneo(): string
    {
        $percorso = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'telonio_test_' . bin2hex(random_bytes(6));
        $this->percorsiTemporanei[] = $percorso;
        return $percorso;
    }

    public function testVersioneOrdinamentoSemver(): void
    {
        $ordinate = ['0.9.0-alpha', '0.9.0-alpha.2', '0.9.0-beta', '0.9.0-rc', '0.9.0-rc.1', '0.9.0', '0.9.1-alpha', '0.10.0-alpha', '1.0.0'];
        for ($indice = 1; $indice < count($ordinate); $indice++) {
            $this->assertTrue(Versione::daTesto($ordinate[$indice])->isSuccessivaA(Versione::daTesto($ordinate[$indice - 1])),
                    $ordinate[$indice] . ' dopo ' . $ordinate[$indice - 1]);
        }
        $this->assertSame(0, Versione::daTesto('v0.9.0-alpha')->confronta(Versione::daTesto('0.9.0-alpha')));
        $this->assertSame('0.9.0-alpha', (string) Versione::daTesto('v0.9.0-alpha'));
        $this->assertSame(CanaleAggiornamenti::stabile, Versione::daTesto('1.2.3')->getCanale());
        $this->assertNull(Versione::tenta('0.9'));
        $this->assertNull(Versione::tenta('0.9.0-dev'));
        $this->expectException(\InvalidArgumentException::class);
        Versione::daTesto('latest');
    }

    public function testCanaliAccettanoLeVersioniPiuStabili(): void
    {
        $versioni = ['0.9.0-alpha' => 'alpha', '0.9.0-beta' => 'beta', '0.9.0-rc.1' => 'rc', '0.9.0' => 'stabile'];
        foreach (CanaleAggiornamenti::cases() as $canale) {
            foreach ($versioni as $versione => $canaleVersione) {
                $this->assertSame(CanaleAggiornamenti::from($canaleVersione)->getStabilita() >= $canale->getStabilita(),
                        $canale->accetta(Versione::daTesto($versione)), $canale->value . ' / ' . $versione);
            }
        }
        $this->assertSame(CanaleAggiornamenti::alpha, CanaleAggiornamenti::perVersione(Versione::daTesto('0.9.0-alpha')));
    }

    public function testArchivioReleaseLeggeSoloReleaseValide(): void
    {
        $allegato = fn(string $nome, bool $conImpronta = true) => [
            'name' => $nome, 'digest' => $conImpronta ? 'sha256:' . str_repeat('b', 64) : null, 'browser_download_url' => 'https://github.com/valentinodelapa/telonio/releases/download/x/' . $nome,
        ];
        $voce = fn(string $tag, array $allegati, bool $bozza = false) => [
            'tag_name' => $tag, 'draft' => $bozza, 'prerelease' => true, 'published_at' => '2026-10-29T08:44:03Z',
            'html_url' => 'https://github.com/valentinodelapa/telonio/releases/tag/' . $tag, 'assets' => $allegati,
        ];
        $json = json_encode([
            $voce('v0.9.0-alpha', [$allegato('telonio-v0.9.0-alpha.zip'), $allegato('telonio-v0.9.0-alpha.zip.intoto.jsonl')]),
            $voce('v0.7.0-alpha', [$allegato('comm-suite-v0.7.0-alpha.zip')]),
            $voce('v1.0.0', [$allegato('telonio-v1.0.0.zip')], true),
            $voce('v0.6.0-alpha', [$allegato('comm-suite-v0.6.0-alpha.zip', false)]),
            $voce('nightly', [$allegato('telonio-nightly.zip')]),
        ]);

        $release = (new ArchivioRelease())->leggi($json);

        $this->assertCount(2, $release);
        $this->assertSame('0.9.0-alpha', (string) $release[0]->versione);
        $this->assertSame('https://github.com/valentinodelapa/telonio/releases/download/x/telonio-v0.9.0-alpha.zip', $release[0]->urlArchivio);
        $this->assertSame(str_repeat('b', 64), $release[0]->sha256);
        $this->assertStringEndsWith('.zip.intoto.jsonl', $release[0]->urlAttestazione);
        $this->assertSame('2026-10-29', $release[0]->dataPubblicazione);
        $this->assertNull($release[1]->urlAttestazione);
        $this->expectException(\UnexpectedValueException::class);
        (new ArchivioRelease())->leggi('{"message":"Not Found"}');
    }

    public function testSceltaDellUltimaVersioneDelCanale(): void
    {
        $release = [$this->creaRelease('0.9.0-alpha'), $this->creaRelease('0.10.0-alpha'), $this->creaRelease('0.10.0-beta'), $this->creaRelease('0.9.1')];

        $this->assertSame('0.10.0-beta', (string) $this->creaControllo('0.9.0-alpha')->scegli($release)->versione);
        $this->assertSame('0.10.0-beta', (string) $this->creaControllo('0.9.0-alpha', CanaleAggiornamenti::beta)->scegli($release)->versione);
        $this->assertSame('0.9.1', (string) $this->creaControllo('0.9.0-alpha', CanaleAggiornamenti::stabile)->scegli($release)->versione);
        $this->assertNull($this->creaControllo('0.10.0-beta')->scegli($release));
        $this->assertNull($this->creaControllo('0.9.1')->scegli($release), 'canale stabile dalla versione installata');
        $this->assertSame('0.9.1', (string) $this->creaControllo('0.9.0')->scegli($release)->versione);
    }

    public function testVerificaConCacheEDopoUnErrore(): void
    {
        $risposte = [[$this->creaRelease('0.10.0-alpha')], new \RuntimeException('rete assente'), [$this->creaRelease('0.11.0-alpha')]];
        $archivio = $this->createMock(ArchivioRelease::class);
        $archivio->expects($this->exactly(3))->method('getRelease')->willReturnCallback(function () use (&$risposte) {
            $risposta = array_shift($risposte);
            return $risposta instanceof \Throwable ? throw $risposta : $risposta;
        });
        $cache = $this->percorsoTemporaneo();
        $controllo = $this->creaControllo('0.9.0-alpha', null, $archivio, $cache);

        $this->assertSame('0.10.0-alpha', (string) $controllo->verificaConCache(1000)->versione);
        $this->assertSame('0.10.0-alpha', (string) $controllo->verificaConCache(1000 + 43199)->versione, 'cache valida per 12 ore');
        $this->assertSame('0.10.0-alpha', (string) $controllo->verificaConCache(1000 + 43200)->versione, 'errore: resta la cache precedente');
        $this->assertSame('0.10.0-alpha', (string) $controllo->verificaConCache(1000 + 43200 + 3599)->versione, 'dopo un errore si riprova dopo un\'ora');
        $this->assertSame('0.11.0-alpha', (string) $controllo->verificaConCache(1000 + 43200 + 3600)->versione);

        $disattivato = new ControlloAggiornamenti($archivio, Versione::daTesto('0.9.0-alpha'), null, $this->percorsoTemporaneo(), false);
        $this->assertNull($disattivato->verificaConCache());
    }

    public function testVersioneInstallataDaComposerJson(): void
    {
        $file = $this->percorsoTemporaneo();
        file_put_contents($file, '{"name": "valentinodelapa/telonio", "version": "0.9.0-alpha"}');
        $this->assertSame('0.9.0-alpha', (string) ControlloAggiornamenti::leggiVersioneInstallata($file));

        $versioneReale = ControlloAggiornamenti::leggiVersioneInstallata(__DIR__ . '/../../../../composer.json');
        $this->assertInstanceOf(Versione::class, $versioneReale);
    }

    public function testCanaleDaVariabileDAmbiente(): void
    {
        $precedente = getenv('CANALE_AGGIORNAMENTI');
        try {
            putenv('CANALE_AGGIORNAMENTI=Beta');
            $this->assertSame(CanaleAggiornamenti::beta, ControlloAggiornamenti::leggiCanale());
            putenv('CANALE_AGGIORNAMENTI=');
            $this->assertNull(ControlloAggiornamenti::leggiCanale());
            putenv('CANALE_AGGIORNAMENTI=nightly');
            $controllo = new ControlloAggiornamenti($this->createStub(ArchivioRelease::class), Versione::daTesto('0.9.0-alpha'), null, $this->percorsoTemporaneo());
            $this->assertFalse($controllo->isAttivo(), 'configurazione non valida: avviso disattivato, nessun errore in scrivania');
            $this->expectException(\InvalidArgumentException::class);
            ControlloAggiornamenti::leggiCanale();
        } finally {
            putenv($precedente === false ? 'CANALE_AGGIORNAMENTI' : 'CANALE_AGGIORNAMENTI=' . $precedente);
        }
    }

    private function creaModulo(string $nome, array $file): string
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

    public function testPianoModuloNuovoEModuloInstallato(): void
    {
        $base = $this->creaModulo('Base', [
            'schema.sql' => "CREATE TABLE IF NOT EXISTS `studio` (id INT);\nCREATE TABLE IF NOT EXISTS `aggiornamento_database` (id INT);",
            'aggiornamento-0.5.sql' => 'ALTER TABLE studio ADD COLUMN IF NOT EXISTS x INT;',
        ]);
        $installato = $this->creaModulo('Installato', [
            'schema.sql' => "CREATE TABLE IF NOT EXISTS `soggetto` (id INT);\nCREATE TABLE `nuova` (id INT);",
            'aggiornamento-0.10.sql' => '-- 0.10',
            'aggiornamento-0.3.sql' => '-- 0.3',
            'aggiornamento-0.5.sql' => '-- 0.5',
            'dati-riferimento.sql' => 'INSERT ...',
            'note.sql' => '-- ignorato',
        ]);
        $nuovo = $this->creaModulo('Nuovo', [
            'schema.sql' => 'CREATE TABLE IF NOT EXISTS `adempimento` (id INT);',
            'aggiornamento-0.2.sql' => '-- 0.2',
            'dati-riferimento.sql' => 'INSERT ...',
        ]);
        $piano = new PianoAggiornamentoDatabase();

        $passi = $piano->pianifica(['Base' => $base, 'Installato' => $installato, 'Nuovo' => $nuovo, 'Assente' => $base . '_inesistente'],
                ['STUDIO', 'soggetto'], ['Installato/aggiornamento-0.3.sql']);
        $descrizioni = array_map(fn($passo) => ($passo->esegui ? 'esegue ' : 'registra ') . $passo->modulo . '/' . $passo->script, $passi);

        $this->assertSame([
            'esegue Base/aggiornamento-0.5.sql',
            'esegue Base/schema.sql',
            'registra Base/aggiornamento-0.5.sql',
            'esegue Installato/aggiornamento-0.5.sql',
            'esegue Installato/aggiornamento-0.10.sql',
            'esegue Installato/schema.sql',
            'registra Installato/aggiornamento-0.5.sql',
            'registra Installato/aggiornamento-0.10.sql',
            'esegue Installato/dati-riferimento.sql',
            'esegue Nuovo/schema.sql',
            'registra Nuovo/aggiornamento-0.2.sql',
            'esegue Nuovo/dati-riferimento.sql',
        ], $descrizioni);
        $this->assertSame(['soggetto', 'nuova'], $piano->getTabelle($installato . DIRECTORY_SEPARATOR . 'schema.sql'));
    }

    public function testPianoDeiModuliRealiSuDatabaseVuotoEAggiornato(): void
    {
        $radice = __DIR__ . '/../../../../';
        $moduli = [];
        foreach (\Config\MODULE_FOLDERS as $modulo) {
            $moduli[$modulo] = $radice . $modulo . DIRECTORY_SEPARATOR . 'Database';
        }
        $piano = new PianoAggiornamentoDatabase();

        $vuoto = $piano->pianifica($moduli, [], []);
        $this->assertSame('Telonio/schema.sql', $vuoto[0]->modulo . '/' . $vuoto[0]->script);
        $this->assertEmpty(array_filter($vuoto, fn($passo) => $passo->esegui && str_starts_with($passo->script, 'aggiornamento-')),
                'su un database vuoto gli aggiornamenti si registrano soltanto');

        $tabelle = [];
        foreach ($moduli as $cartella) {
            $tabelle = array_merge($tabelle, $piano->getTabelle($cartella . DIRECTORY_SEPARATOR . 'schema.sql'));
        }
        $this->assertContains('aggiornamento_database', $tabelle);
        $eseguiti = array_map(fn($passo) => $passo->modulo . '/' . $passo->script, array_filter($vuoto, fn($passo) => $passo->registra));
        $aggiornato = $piano->pianifica($moduli, $tabelle, $eseguiti);
        $this->assertEmpty(array_filter($aggiornato, fn($passo) => str_starts_with($passo->script, 'aggiornamento-')),
                'database aggiornato: restano solo schema e dati di riferimento');
    }
}
