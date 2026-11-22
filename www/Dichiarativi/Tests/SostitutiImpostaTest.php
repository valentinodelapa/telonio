<?php

namespace Dichiarativi\Tests;

use Contabilita\Application\Enumerations\CausaleCu;
use Deleghe\Application\Services\Telematico\RecordF24;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Sostituti\ControlliSostituti;
use Dichiarativi\Application\Services\Sostituti\Proposta770;
use Dichiarativi\Application\Services\Sostituti\PropostaCu;
use Dichiarativi\Application\Services\Telematico\GeneratoreSostituti;
use Dichiarativi\Application\Services\Telematico\TracciatoSostituti;

/**
 * Certificazione Unica e modello 770 dei sostituti d'imposta: compensi
 * ricavati dalle ritenute per causale, controlli bloccanti delle
 * specifiche, righi del prospetto ST, record della fornitura.
 */
class SostitutiImpostaTest extends DichiarativiTestCase
{

    private function crea(ModelloDichiarativo $modello): Dichiarazione
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = $modello;
        return $dichiarazione;
    }

    private function quadri(ModelloDichiarativo $modello): QuadriDichiarazione
    {
        return new QuadriDichiarazione($this->crea($modello), $this->dataMapper, $this->getCatalogo($modello, 2026));
    }

    public function testModelliGestitiEFornitura(): void
    {
        $this->assertTrue(ModelloDichiarativo::certificazioneUnica->isGestito());
        $this->assertTrue(ModelloDichiarativo::modello770->isSostituti());
        $this->assertFalse(ModelloDichiarativo::modello770->isRedditi());
        $this->assertSame('CUR26', ModelloDichiarativo::certificazioneUnica->getCodiceFornitura(2025));
        $this->assertSame('77026', ModelloDichiarativo::modello770->getCodiceFornitura(2025));
        $this->assertSame('2026-10-31', ModelloDichiarativo::modello770->getScadenza(2025)->format('Y-m-d'));
    }

    public function testCausaliAliquoteEQuote(): void
    {
        $this->assertSame(20.0, CausaleCu::professionista->getAliquota());
        $this->assertSame(100.0, CausaleCu::professionista->getQuotaImponibile());
        $this->assertSame(23.0, CausaleCu::agenteMonomandatario->getAliquota());
        $this->assertSame(50.0, CausaleCu::agentePlurimandatario->getQuotaImponibile());
        $this->assertSame(78.0, CausaleCu::venditaDomicilio->getQuotaImponibile());
        $this->assertStringStartsWith('M - ', CausaleCu::occasionale->getLabel());
    }

    public function testCompensiDalleRitenute(): void
    {
        $this->assertSame(['lordo' => 1000.0, 'imponibile' => 1000.0, 'nonSoggette' => 0.0], PropostaCu::getCompensi(CausaleCu::professionista, 200.0));
        // provvigioni: ritenuta del 23% sul 50%
        $this->assertSame(['lordo' => 2000.0, 'imponibile' => 1000.0, 'nonSoggette' => 1000.0], PropostaCu::getCompensi(CausaleCu::agenteMonomandatario, 230.0));
    }

    public function testCertificazioneProvvigioniEControlli(): void
    {
        $quadri = $this->quadri(ModelloDichiarativo::certificazioneUnica);
        PropostaCu::proponiSostituto($quadri, ['DA001001' => '01234560785', 'DA001002' => 'AZIENDA SRL', 'DA003001' => '2026-03-10', 'DA003002' => '1']);
        $quadri->proponi('DA002001', 'RSSMRA80A01H501U');
        PropostaCu::proponiCompensi($quadri, CausaleCu::agenteMonomandatario, 230.0);
        $this->assertSame('Q', $quadri->get('AU001001'));
        $this->assertSame('2000.00', $quadri->get('AU001004'));
        $this->assertSame('21', $quadri->get('AU001006'));
        $this->assertSame('1000.00', $quadri->get('AU001007'));
        $this->assertSame('1000.00', $quadri->get('AU001008'));
        $this->assertSame('230.00', $quadri->get('AU001009'));
        $this->assertSame([], ControlliSostituti::getErroriCu($quadri));
        $this->assertSame(['Certificazioni' => 1, 'Compensi lordi (punto 4)' => 2000.0, 'Imponibile (punto 8)' => 1000.0, "Ritenute a titolo d'acconto (punto 9)" => 230.0], PropostaCu::getRiepilogo($quadri));
        // imponibile incoerente oltre la tolleranza di 2 euro e codice fiscale non valido
        $quadri->imposta('AU001008', '1500');
        $quadri->imposta('DA002001', 'RSSMRA80A01H501X');
        $errori = implode(' ', ControlliSostituti::getErroriCu($quadri));
        $this->assertStringContainsString('imponibile (punto 8)', $errori);
        $this->assertStringContainsString('codice fiscale del percipiente', $errori);
        $this->assertSame(['Nessuna certificazione: proporre le certificazioni dalle ritenute o compilarle a mano.'], ControlliSostituti::getErroriCu($this->quadri(ModelloDichiarativo::certificazioneUnica)));
    }

    public function testRecordCertificazione(): void
    {
        $quadri = $this->quadri(ModelloDichiarativo::certificazioneUnica);
        $record = GeneratoreSostituti::certificazione('H', '01234560785', 'RSSMRA80A01H501U', 3, ['AU001001' => 'A', 'AU001004' => '1000.00', 'AU001008' => '1000.00', 'AU001009' => '200.00'], $quadri, null);
        $this->assertCount(1, $record);
        $this->assertSame(1900, strlen($record[0]));
        $this->assertSame('H01234560785     00000001RSSMRA80A01H501U00003', substr($record[0], 0, 46));
        $this->assertSame(str_repeat('0', 17) . str_repeat('0', 6), substr($record[0], 46, 23));
        $this->assertSame('AU001001A               ', substr($record[0], 89, 24));
        $this->assertSame('AU001004         1000,00', substr($record[0], 113, 24));
        $this->assertSame([], GeneratoreSostituti::certificazione('H', '01234560785', 'RSSMRA80A01H501U', 3, [], $quadri, null));
    }

    public function testOrdineRecordCertificazioni(): void
    {
        $quadri = $this->quadri(ModelloDichiarativo::certificazioneUnica);
        $valori = [
            4 => ['DA002001' => 'VRDLGU70B02F205X', 'DA002002' => 'VERDI', 'AU001001' => 'B', 'AU001004' => '2000.00'],
            1 => ['DA002001' => 'RSSMRA80A01H501U', 'DA002002' => 'ROSSI', 'AU001001' => 'A', 'AU001004' => '1000.00'],
        ];
        $record = GeneratoreSostituti::certificazioni('01234560785', $valori, $quadri, 0);
        // ogni record D seguito dai suoi record H, per progressivo crescente
        $this->assertSame(['D00001', 'H00001', 'D00004', 'H00004'], array_map(fn(string $riga) => $riga[0] . substr($riga, 41, 5), $record));
        $this->assertSame(substr($record[2], 1, 45), substr($record[3], 1, 45));
    }

    public function testProspettoSt(): void
    {
        $quadri = $this->quadri(ModelloDichiarativo::modello770);
        Proposta770::proponiRighi($quadri, [
            ['mese' => 3, 'anno' => 2025, 'ritenute' => 200.0, 'versato' => 200.0, 'data' => '2025-04-16'],
            ['mese' => 11, 'anno' => 2025, 'ritenute' => 460.0, 'versato' => 0.0, 'data' => null],
        ]);
        $this->assertSame('32025', $quadri->get('ST002001'));
        $this->assertSame(['     032025'], array_map(fn(string $valore) => substr($valore, 5), $quadri->getCampo('ST002001')->formato->formatta('32025')));
        $this->assertSame('1040', $quadri->get('ST002011'));
        $this->assertSame('2025-04-16', $quadri->get('ST002014'));
        $this->assertSame('112025', $quadri->get('ST003001'));
        $this->assertNull($quadri->get('ST003007'));
        $this->assertSame([], ControlliSostituti::getErrori770($quadri));
        $this->assertSame(['Ritenute operate (ST col. 2)' => 660.0, 'Importi versati (ST col. 7)' => 200.0], Proposta770::getRiepilogo($quadri));
        $this->assertSame(['Rigo ST3: ritenute non versate.'], (new ControlliSostituti($this->dataMapper))->getAvvertenze($quadri, $this->crea(ModelloDichiarativo::modello770)));
        $quadri->imposta('ST003011', null);
        $this->assertSame(['rigo ST3: codice tributo mancante.'], ControlliSostituti::getErrori770($quadri));
        $this->assertSame(['Prospetto ST vuoto: nessuna ritenuta da dichiarare.'], ControlliSostituti::getErrori770($this->quadri(ModelloDichiarativo::modello770)));
    }

    public function testFrontespizio770(): void
    {
        $record = (string) (new RecordF24(TracciatoSostituti::RECORD_B_770))->imposta('tipoRecord', 'B')->imposta('tipologiaInvio', 1)->imposta('quadroSt', 1)->imposta('autonomo', 1)->imposta('firmaDichiarante', 1);
        $this->assertSame(1900, strlen($record));
        $this->assertSame('1', $record[1129]);
        $this->assertSame('1', $record[1140]);
        $this->assertSame('0', $record[1141]);
        $this->assertSame('1', $record[1147]);
        $this->assertSame('1', $record[1197]);
        // caselle e campi numerici non compilati a zero (lo spazio è un errore bloccante)
        foreach (TracciatoSostituti::RECORD_B_770 as $nome => [$posizione, $lunghezza, $tipo]) {
            if ($tipo === 'NU') {
                $this->assertDoesNotMatchRegularExpression('/\D/', substr($record, $posizione - 1, $lunghezza), $nome);
            }
        }
        $this->assertSame('00', substr($record, 1496, 2));
        $this->assertSame(str_repeat('0', 11), substr($record, 1761, 11));
    }
}
