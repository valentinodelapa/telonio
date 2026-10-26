<?php

namespace CommSuite\Tests\Application\HelperClasses;

use CommSuite\Application\HelperClasses\Calendario;
use CommSuite\Tests\SuiteTestCase;
use SismaFramework\Orm\CustomTypes\SismaDate;

class CalendarioTest extends SuiteTestCase
{

    public function testCalendarioGiorniFerialiEFestiviFissi(): void
    {
        // Mercoledì feriale (15/05/2024)
        $this->assertTrue(Calendario::isLavorativo(new \DateTimeImmutable('2024-05-15')));

        // Sabato e Domenica
        $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable('2024-05-18')));
        $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable('2024-05-19')));

        // Festività fisse anche se cadono in giorno infrasettimanale
        $festiviFissi = [
            '2024-01-01', // Lunedì - Capodanno
            '2025-01-06', // Lunedì - Epifania
            '2024-04-25', // Giovedì - Liberazione
            '2024-05-01', // Mercoledì - Festa del lavoro
            '2025-06-02', // Lunedì - Festa della Repubblica
            '2024-08-15', // Giovedì - Ferragosto
            '2024-11-01', // Venerdì - Ognissanti
            '2025-12-08', // Lunedì - Immacolata
            '2024-12-25', // Mercoledì - Natale
            '2024-12-26', // Giovedì - Santo Stefano
        ];
        foreach ($festiviFissi as $data) {
            $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable($data)), "Data $data dovrebbe essere non lavorativa");
        }
    }

    public function testCalendarioPasquaEPasquetta(): void
    {
        // Pasqua è sempre domenica (non lavorativa) e Lunedì dell'Angelo (Pasquetta) non lavorativo
        // 2024: Pasqua 31 marzo, Pasquetta 1 aprile
        $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable('2024-03-31')));
        $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable('2024-04-01')));
        $this->assertTrue(Calendario::isLavorativo(new \DateTimeImmutable('2024-04-02')));

        // 2025: Pasqua 20 aprile, Pasquetta 21 aprile
        $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable('2025-04-20')));
        $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable('2025-04-21')));
        $this->assertTrue(Calendario::isLavorativo(new \DateTimeImmutable('2025-04-22')));

        // 2026: Pasqua 5 aprile, Pasquetta 6 aprile
        $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable('2026-04-06')));
    }

    public function testPrimoGiornoLavorativoProroga(): void
    {
        // Giorno già lavorativo: resta invariato
        $feriale = Calendario::primoGiornoLavorativo(new SismaDate('2024-05-16'));
        $this->assertSame('2024-05-16', $feriale->format('Y-m-d'));

        // Scadenza 16/03/2024 (Sabato) -> Lunedì 18/03/2024
        $daSabato = Calendario::primoGiornoLavorativo(new SismaDate('2024-03-16'));
        $this->assertSame('2024-03-18', $daSabato->format('Y-m-d'));

        // Scadenza 16/06/2024 (Domenica) -> Lunedì 17/06/2024
        $daDomenica = Calendario::primoGiornoLavorativo(new SismaDate('2024-06-16'));
        $this->assertSame('2024-06-17', $daDomenica->format('Y-m-d'));

        // Scadenza 30/03/2024 (Sabato prima di Pasqua) -> Pasqua 31/03, Pasquetta 01/04 -> Martedì 02/04/2024
        $daSabatoPasquale = Calendario::primoGiornoLavorativo(new SismaDate('2024-03-30'));
        $this->assertSame('2024-04-02', $daSabatoPasquale->format('Y-m-d'));

        // Scadenza 25/12/2024 (Mercoledì Natale) -> 26/12 (Giovedì S. Stefano) -> Venerdì 27/12/2024
        $daNatale = Calendario::primoGiornoLavorativo(new SismaDate('2024-12-25'));
        $this->assertSame('2024-12-27', $daNatale->format('Y-m-d'));
    }

    public function testGiorniTra(): void
    {
        $da = new SismaDate('2024-05-16');
        $a = new SismaDate('2024-05-30');
        $this->assertSame(14, Calendario::giorniTra($da, $a));
        $this->assertSame(-14, Calendario::giorniTra($a, $da));
        $this->assertSame(0, Calendario::giorniTra($da, $da));
    }

    public function testQuattroOttobreFestivoDal2026(): void
    {
        // L. 151/2025: festa nazionale dal 2026 (04/10/2027 è lunedì)
        $this->assertTrue(Calendario::isLavorativo(new \DateTimeImmutable('2024-10-04')));
        $this->assertFalse(Calendario::isLavorativo(new \DateTimeImmutable('2027-10-04')));
        $this->assertSame('2027-10-05', Calendario::primoGiornoLavorativo(new SismaDate('2027-10-04'))->format('Y-m-d'));
    }

    public function testTermineFiscaleConProrogaDiAgosto(): void
    {
        // 16/08/2027 (lunedì) differito al 20/08/2027 (venerdì)
        $this->assertSame('2027-08-20', Calendario::termineFiscale(new SismaDate('2027-08-16'))->format('Y-m-d'));
        // 20/08/2022 è sabato: lunedì 22/08/2022
        $this->assertSame('2022-08-22', Calendario::termineFiscale(new SismaDate('2022-08-16'))->format('Y-m-d'));
        // senza proroga di agosto resta il 16/08/2027
        $this->assertSame('2027-08-16', Calendario::termineFiscale(new SismaDate('2027-08-16'), false)->format('Y-m-d'));
        // dopo il 20 agosto vale solo lo slittamento: 31/08/2025 domenica -> 01/09/2025
        $this->assertSame('2025-09-01', Calendario::termineFiscale(new SismaDate('2025-08-31'))->format('Y-m-d'));
    }
}
