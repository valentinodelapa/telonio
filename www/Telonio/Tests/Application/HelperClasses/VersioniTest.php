<?php

namespace Telonio\Tests\Application\HelperClasses;

use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Application\HelperClasses\Versioni;
use PHPUnit\Framework\TestCase;

class VersioniTest extends TestCase
{

    #[\Override]
    protected function tearDown(): void
    {
        Versioni::svuota();
    }

    public function testVersioniDellaSuiteEDeiModuliInstallati(): void
    {
        $radice = __DIR__ . '/../../../../';
        $composer = json_decode(file_get_contents($radice . 'composer.json'), true);
        $deleghe = json_decode(file_get_contents($radice . 'Deleghe/module.json'), true);

        $this->assertSame($composer['version'], Versioni::getSuite());
        $this->assertSame($deleghe['version'], Versioni::getModulo('Deleghe'));
        $this->assertSame($deleghe['version'], ModuloSuite::deleghe->getVersione());
        $this->assertNull(ModuloSuite::contabilita->getVersione(), 'modulo non disponibile');
        $this->assertNull(Versioni::getModulo('Inesistente'));
    }

    public function testFileSenzaVersioneEMemoriaDellaRichiesta(): void
    {
        $radice = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'telonio_versioni_' . bin2hex(random_bytes(4)) . DIRECTORY_SEPARATOR;
        mkdir($radice . 'Prova', 0777, true);
        try {
            file_put_contents($radice . 'composer.json', '{"name": "senza-versione"}');
            file_put_contents($radice . 'Prova' . DIRECTORY_SEPARATOR . 'module.json', '{"version": "1.2.3"}');
            $this->assertNull(Versioni::getSuite($radice));
            $this->assertSame('1.2.3', Versioni::getModulo('Prova', $radice));

            file_put_contents($radice . 'Prova' . DIRECTORY_SEPARATOR . 'module.json', '{"version": "1.2.4"}');
            $this->assertSame('1.2.3', Versioni::getModulo('Prova', $radice), 'letto una volta per richiesta');
            Versioni::svuota();
            $this->assertSame('1.2.4', Versioni::getModulo('Prova', $radice));
        } finally {
            unlink($radice . 'composer.json');
            unlink($radice . 'Prova' . DIRECTORY_SEPARATOR . 'module.json');
            rmdir($radice . 'Prova');
            rmdir($radice);
        }
    }
}
