<?php

namespace Deleghe\Tests\Services;

use Telonio\Application\Enumerations\ModuloSuite;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Services\ScadenzeModulo;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ScadenzeModuloTest extends SuiteTestCase
{

    public function testVersamentiRaggruppatiPerSoggettoEScadenza(): void
    {
        $rossi = $this->creaSoggettoFisico(1, $this->studioTest, 'RSSMRA80A01H501U', 'ROSSI', 'MARIO');
        $bianchi = $this->creaSoggettoFisico(2, $this->studioTest, 'BNCLGU80A01H501X', 'BIANCHI', 'LUIGI');
        $trasmessa = $this->creaDelega(1, $rossi, StatoDelega::trasmessa);
        $bozza = $this->creaDelega(2, $bianchi, StatoDelega::bozza);
        $versamenti = [
            $this->creaVersamento(1, $rossi, '2026-06-16', 100.0, 0.0, $trasmessa, 'Imu'),
            $this->creaVersamento(2, $rossi, '2026-06-16', 50.0, 20.0, $trasmessa),
            $this->creaVersamento(3, $bianchi, '2026-06-16', 30.0, 0.0, $bozza),
            $this->creaVersamento(4, $bianchi, '2026-07-16', 40.0, 0.0, null, 'Ravvedimento'),
        ];
        $this->testAdapter->impostaSelectCallback(function (string $cmd): TestResultSet {
            return str_contains($cmd, 'COUNT(') ? TestResultSet::conteggio(0) : new TestResultSet([]);
        });
        $this->testAdapter->accodaSelect($versamenti);

        $scadenze = (new ScadenzeModulo($this->dataMapper))->getScadenze($this->studioTest, new SismaDate('2026-06-01'), new SismaDate('2026-07-31'));
        $this->assertCount(3, $scadenze);
        $this->assertSame(ModuloSuite::deleghe, $scadenze[0]->modulo);
        $this->assertSame('S1/2026-06-16', $scadenze[0]->riferimento);
        $this->assertSame('Versamenti F24 (IMU)', $scadenze[0]->descrizione);
        $this->assertSame(130.0, $scadenze[0]->importo);
        $this->assertTrue($scadenze[0]->completata);
        $this->assertSame('2 Trasmessa', $scadenze[0]->dettaglio);
        $this->assertSame('/contribuente/show/soggetto/1', $scadenze[0]->url);
        $this->assertFalse($scadenze[1]->completata);
        // versamento non in delega e non regolarizzato con ravvedimento
        $this->assertFalse($scadenze[2]->completata);
        $this->assertSame('Versamenti F24 (ravvedimento)', $scadenze[2]->descrizione);
        $this->assertSame('1 da inserire in delega', $scadenze[2]->dettaglio);
    }

    private function creaDelega(int $id, $soggetto, StatoDelega $stato): Delega
    {
        $delega = new Delega($this->dataMapper);
        $delega->id = $id;
        $delega->studio = $this->studioTest;
        $delega->soggetto = $soggetto;
        $delega->scadenza = new SismaDate('2026-06-16');
        $delega->stato = $stato;
        return $delega;
    }

    private function creaVersamento(int $id, $soggetto, string $scadenza, float $debito, float $credito, ?Delega $delega, ?string $origine = null): Versamento
    {
        $versamento = new Versamento($this->dataMapper);
        $versamento->id = $id;
        $versamento->studio = $this->studioTest;
        $versamento->soggetto = $soggetto;
        $versamento->scadenza = new SismaDate($scadenza);
        $versamento->sezione = SezioneF24::erario;
        $versamento->importoDebito = $debito;
        $versamento->importoCredito = $credito;
        $versamento->delega = $delega;
        $versamento->origine = $origine;
        return $versamento;
    }
}
