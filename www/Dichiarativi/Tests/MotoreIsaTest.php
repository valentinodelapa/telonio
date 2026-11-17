<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Services\Isa\MotoreIsa;
use PHPUnit\Framework\TestCase;

/**
 * Motore ufficiale degli ISA ("Il tuo ISA 2026 CPB" in modalità batch,
 * installato nell'immagine PHP): calcolo di un modello EM01A minimo (record
 * P e Q come nelle specifiche tecniche ISA 2026), esito in XML e stampa del
 * modello.
 */
class MotoreIsaTest extends TestCase
{

    private MotoreIsa $motore;

    #[\Override]
    protected function setUp(): void
    {
        $this->motore = new MotoreIsa();
        if ($this->motore->isDisponibile(2026) === false) {
            $this->markTestSkipped('Motore ISA 2026 non installato (stage "iltuoisa" del Dockerfile)');
        }
    }

    /**
     * Record di 1.900 caratteri: testa di 108 caratteri, corpo, spazio
     * riservato e chiusura.
     */
    private static function record(string $tipo, string $corpo): string
    {
        $testa = $tipo . str_pad('FRRLCU78E52H501I', 16) . '00000001' . str_repeat(' ', 64) . '471102' . '2026' . '01' . '03' . 'EM01A';
        return str_pad($testa . $corpo, 1876) . str_repeat(' ', 21) . "A\r\n";
    }

    /**
     * @param string[] $senza codici dei campi da togliere
     */
    private static function ingresso(array $senza = []): string
    {
        $campi = [
            'Y0100101' => str_pad('471102', 18), 'Y0100301' => str_pad('H501', 18),
            'B0100001' => str_pad('01', 18, ' ', STR_PAD_LEFT), 'B0100101' => str_pad('H501', 18), 'B0100301' => str_pad('00000000060', 18, ' ', STR_PAD_LEFT),
            'B0100401' => str_pad('002', 18, ' ', STR_PAD_LEFT),
            'C0100801' => str_pad('100', 18, ' ', STR_PAD_LEFT), 'C0103201' => str_pad('060', 18, ' ', STR_PAD_LEFT), 'C0104401' => str_pad('040', 18, ' ', STR_PAD_LEFT),
            'F0100101' => str_pad('00000182000', 18, ' ', STR_PAD_LEFT), 'F0101001' => str_pad('00000120000', 18, ' ', STR_PAD_LEFT),
            'F0102001' => str_pad('00000036500', 18, ' ', STR_PAD_LEFT), 'F0102301' => str_pad('00000182000', 18, ' ', STR_PAD_LEFT),
            'IFU00101' => str_pad('003', 18, ' ', STR_PAD_LEFT),
        ];
        $corpo = '';
        foreach (array_diff_key($campi, array_flip($senza)) as $codice => $valore) {
            $corpo .= $codice . $valore;
        }
        return self::record('P', str_pad('FERRI', 30) . str_pad('LUCIA', 30)) . self::record('Q', $corpo);
    }

    public function testCalcoloEsitoEStampa(): void
    {
        $elaborato = $this->motore->elabora(2026, self::ingresso());
        $this->assertStringContainsString('Isa elaborato', $elaborato['report']);
        // punteggio di affidabilità tra 1 e 10 nel record Q restituito
        $this->assertMatchesRegularExpression('~IIISAAFF\s+(10|0[1-9]),\d\d~', $elaborato['output']);
        $this->assertSame(0, strlen($elaborato['output']) % 1900);

        $esito = $this->motore->esito(2026, self::ingresso());
        $this->assertStringContainsString('<codiceIndicatore>', $esito['xml']);

        $this->assertStringStartsWith('%PDF', $this->motore->stampaModello(2026, $elaborato['output']));
    }

    public function testControlliDelSoftwareNelReport(): void
    {
        // senza la tipologia di esercizio (B04) il software non elabora e lo spiega nel report
        $errato = $this->motore->elabora(2026, self::ingresso(['B0100401']));
        $this->assertStringContainsString('Isa non elaborato', $errato['report']);
        $this->assertStringContainsString('B04 deve essere compilato', $errato['report']);
    }

    public function testErroreSenzaMotore(): void
    {
        $this->expectException(\RuntimeException::class);
        (new MotoreIsa('/inesistente'))->elabora(2026, self::ingresso());
    }
}
