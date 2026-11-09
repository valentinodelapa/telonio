<?php

namespace Dichiarativi\Tests;

use Deleghe\Application\Services\Telematico\RecordF24;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\CinquePerMille;
use Dichiarativi\Application\Enumerations\OttoPerMille;
use Dichiarativi\Application\Services\Redditi\PartitiDuePerMille;
use Dichiarativi\Application\Services\Telematico\GeneratoreRedditi;
use Dichiarativi\Application\Services\Telematico\TracciatoRedditi;
use Telonio\Tests\SuiteTestCase;

/**
 * Scelte dell'otto, del cinque e del due per mille nel record B del file
 * RPF26 (specifiche tecniche, par. 4.1-4.3 e campi da 66 a 101).
 */
class ScelteDestinazioneTest extends SuiteTestCase
{

    private function recordB(Dichiarazione $dichiarazione): string
    {
        $record = (new RecordF24(TracciatoRedditi::RECORD_B))->imposta('tipoRecord', 'B');
        $metodo = new \ReflectionMethod(GeneratoreRedditi::class, 'impostaScelte');
        $metodo->invoke(new GeneratoreRedditi($this->dataMapper), $record, $dichiarazione);
        return (string) $record;
    }

    private function campo(string $record, int $posizione, int $lunghezza = 1): string
    {
        return substr($record, $posizione - 1, $lunghezza);
    }

    public function testSceltaNonEffettuata(): void
    {
        $record = $this->recordB(new Dichiarazione($this->dataMapper));
        $this->assertSame('1', $this->campo($record, 702));
        $this->assertSame('1', $this->campo($record, 778));
        $this->assertSame('1', $this->campo($record, 784));
        $this->assertSame(str_repeat('0', 14), $this->campo($record, 688, 14));
    }

    public function testScelte(): void
    {
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->ottoPerMille = OttoPerMille::stato;
        $dichiarazione->finalitaStato = 3;
        $dichiarazione->cinquePerMille = CinquePerMille::sportDilettantistico;
        $dichiarazione->beneficiarioCinquePerMille = '01234560785';
        $dichiarazione->duePerMille = 'M20';
        $record = $this->recordB($dichiarazione);
        $this->assertSame('3', $this->campo($record, 687));
        $this->assertSame('1', $this->campo($record, 688));
        $this->assertSame('0', $this->campo($record, 702));
        $this->assertSame('1', $this->campo($record, 753));
        $this->assertSame('01234560785', $this->campo($record, 754, 11));
        $this->assertSame('0', $this->campo($record, 778));
        $this->assertSame('M20', $this->campo($record, 780, 3));
        $this->assertSame('1', $this->campo($record, 783));
        $this->assertSame('0', $this->campo($record, 784));

        // posizioni delle istituzioni dell'otto per mille e delle finalità del cinque per mille
        $this->assertSame(689, OttoPerMille::chiesaCattolica->getPosizione());
        $this->assertSame(701, OttoPerMille::chiesaInghilterra->getPosizione());
        $this->assertNull(CinquePerMille::comuneResidenza->getPosizioneCodiceFiscale());
        $this->assertSame(766, CinquePerMille::areeProtette->getPosizioneCodiceFiscale());
        $this->assertArrayHasKey('Y59', PartitiDuePerMille::getPartiti(2025));
        $this->assertSame([], PartitiDuePerMille::getPartiti(2030));
    }
}
