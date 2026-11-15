<?php

namespace Dichiarativi\Tests;

use Deleghe\Application\Services\Telematico\RecordF24;
use Dichiarativi\Application\Services\Telematico\TracciatoRedditi;
use PHPUnit\Framework\TestCase;

/**
 * Campi posizionali della fornitura RPF26: i campi numerici non compilati
 * valgono zero, non spazi (specifiche tecniche, par. 2.4; la procedura di
 * controllo scarta la fornitura con gli spazi nel record A).
 */
class TracciatoRedditiTest extends TestCase
{

    public function testRecordADichiarazioneInUnSoloInvio(): void
    {
        $record = (string) new RecordF24(TracciatoRedditi::RECORD_A);
        $this->assertSame('00000000', substr($record, 521, 8));
    }

    public function testRecordZConIConteggiDiTuttiITipiDiRecord(): void
    {
        $record = (string) (new RecordF24(TracciatoRedditi::RECORD_Z))->imposta('numeroRecordB', 1)->imposta('numeroRecordC', 2);
        $this->assertSame('000000001000000002000000000000000000000000000', substr($record, 15, 45));
    }

    public function testCampiNumericiDelRecordBAZeri(): void
    {
        $campi = TracciatoRedditi::RECORD_B;
        foreach (TracciatoRedditi::NUMERICI_B as $posizione => $lunghezza) {
            $campi['numerico' . $posizione] = [$posizione, $lunghezza, 'NU'];
        }
        $record = (string) (new RecordF24($campi))->imposta('partitaIva', null)->imposta('beneficiarioTS', null);
        foreach (TracciatoRedditi::NUMERICI_B as $posizione => $lunghezza) {
            $this->assertSame(str_repeat('0', $lunghezza), substr($record, $posizione - 1, $lunghezza), 'posizione ' . $posizione);
        }
        // i campi con nome sulle stesse posizioni sono numerici anch'essi
        foreach (TracciatoRedditi::RECORD_B as $nome => [$posizione, , $tipo]) {
            if (isset(TracciatoRedditi::NUMERICI_B[$posizione])) {
                $this->assertSame('NU', $tipo, $nome);
            }
        }
    }
}
