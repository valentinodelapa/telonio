<?php

namespace CommSuite\Tests\Application\Services;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Enumerations\ModuloSuite;
use CommSuite\Application\Interfaces\FonteScadenze;
use CommSuite\Application\Services\RaccoltaScadenze;
use CommSuite\Application\Services\Scadenza;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

class RaccoltaScadenzeTest extends SuiteTestCase
{

    private function creaFonte(array $scadenze): FonteScadenze
    {
        return new class($scadenze) implements FonteScadenze {

            public function __construct(private DataMapper|array $scadenze)
            {

            }

            public function getScadenze(Studio $studio, SismaDate $dal, SismaDate $al, ?BaseEntity $soggetto = null): array
            {
                return $this->scadenze;
            }
        };
    }

    public function testRaccoglieLeScadenzeDeiModuliInOrdineDiData(): void
    {
        $f24 = new Scadenza(ModuloSuite::deleghe, 'S1/2026-11-16', new SismaDate('2026-11-16'), 'Versamenti F24', importo: 10.0);
        $imu = new Scadenza(ModuloSuite::imu, 'dichiarazione-1', new SismaDate('2026-06-30'), 'Dichiarazione IMU', completata: true);
        $redditi = new Scadenza(ModuloSuite::gestioneStudio, 'T1001/2025/S1', new SismaDate('2026-11-16'), 'Dichiarazione dei redditi');
        $raccolta = new RaccoltaScadenze($this->dataMapper, ['Deleghe' => $this->creaFonte([$f24]), 'Imu' => $this->creaFonte([$imu]), 'GestioneStudio' => $this->creaFonte([$redditi])]);

        $scadenze = $raccolta->raccogli($this->studioTest, new SismaDate('2026-01-01'), new SismaDate('2026-12-31'));
        // per data e, a parità, per descrizione
        $this->assertSame([$imu, $redditi, $f24], $scadenze);
        $this->assertSame([$imu], $raccolta->raccogli($this->studioTest, new SismaDate('2026-01-01'), new SismaDate('2026-12-31'), modulo: ModuloSuite::imu));
        $this->assertSame('Deleghe:S1/2026-11-16', $f24->getChiave());
    }

    public function testScopreLeFontiDeiModuliDisponibili(): void
    {
        $fonti = (new RaccoltaScadenze($this->dataMapper))->getFonti();
        $this->assertSame(['Deleghe', 'Imu', 'Successioni', 'GestioneStudio'], array_keys($fonti));
        foreach ($fonti as $fonte) {
            $this->assertInstanceOf(FonteScadenze::class, $fonte);
        }
        $this->assertSame('Anagrafica\\Application\\Services\\ScadenzeModulo', RaccoltaScadenze::getClasseFonte(ModuloSuite::anagrafica));
    }

    public function testRiferimentoDellaScadenzaObbligatorioEBreve(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Scadenza(ModuloSuite::deleghe, str_repeat('x', Scadenza::LUNGHEZZA_RIFERIMENTO + 1), new SismaDate(), 'Troppo lungo');
    }
}
