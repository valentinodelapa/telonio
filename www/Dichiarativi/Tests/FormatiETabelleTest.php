<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\Frontespizio;
use Dichiarativi\Application\Services\TabelleRiferimento\LettoreSpecifiche;
use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;
use Anagrafica\Application\Enumerations\NaturaGiuridica;

/**
 * Formati dei campi, lettura delle specifiche tecniche e catalogo dei
 * modelli dalle sorgenti del modulo.
 */
class FormatiETabelleTest extends DichiarativiTestCase
{

    public function testNormalizzazioneEFormattazioneDegliImporti(): void
    {
        // arrotondamento all'unità di euro per eccesso da 50 centesimi
        $this->assertSame('1235', FormatoCampo::positivo->normalizza('1.234,50'));
        $this->assertSame('1234', FormatoCampo::positivo->normalizza(1234.49));
        $this->assertNull(FormatoCampo::positivo->normalizza('0,40'));
        $this->assertSame('-15', FormatoCampo::numerico->normalizza(-15.2));
        $this->assertSame(['             -15'], FormatoCampo::numerico->formatta('-15'));
        $this->assertSame(['           30000'], FormatoCampo::positivo->formatta('30000'));
        $this->assertSame('30.000', FormatoCampo::positivo->mostra('30000'));
        $this->expectException(\InvalidArgumentException::class);
        FormatoCampo::positivo->normalizza('-3');
    }

    public function testAltriFormati(): void
    {
        $this->assertSame('1', FormatoCampo::casella->normalizza(true));
        $this->assertNull(FormatoCampo::casella->normalizza(''));
        $this->assertSame(['               1'], FormatoCampo::casella->formatta('1'));
        $this->assertSame('2026-04-28', FormatoCampo::data->normalizza('28/04/2026'));
        $this->assertSame(['        28042026'], FormatoCampo::data->formatta('2026-04-28'));
        $this->assertSame('33.333', FormatoCampo::percentuale->normalizza('33,333'));
        $this->assertSame(['          33,333'], FormatoCampo::percentuale->formatta('33.333'));
        $this->assertSame('5', FormatoCampo::cifre2->normalizza('05'));
        $this->assertSame(['              05'], FormatoCampo::cifre2->formatta('5'));
        $this->assertSame(['01234567897     '], FormatoCampo::partitaIva->formatta('01234567897'));
        $this->assertSame(2, FormatoCampo::cifre2->getCifre());
        // testo oltre i 16 caratteri: elementi successivi che iniziano con "+"
        $elementi = FormatoCampo::alfanumerico->formatta('Società di gestione del risparmio');
        $this->assertSame(['SOCIETA DI GESTI', '+ONE DEL RISPARM', '+IO             '], $elementi);
        try {
            FormatoCampo::cifre1->normalizza('12');
            $this->fail('Due cifre in un campo N1');
        } catch (\InvalidArgumentException $errore) {
            $this->assertStringContainsString('N1', $errore->getMessage());
        }
    }

    public function testModelloEDichiarazione(): void
    {
        $this->assertSame('IVA26', ModelloDichiarativo::iva->getCodiceFornitura(2025));
        $this->assertSame('RPF26', ModelloDichiarativo::redditiPf->getCodiceFornitura(2025));
        $this->assertSame('2026-04-30', ModelloDichiarativo::iva->getScadenza(2025)->format('Y-m-d'));
        $this->assertSame('2026-10-31', ModelloDichiarativo::redditiPf->getScadenza(2025)->format('Y-m-d'));
        $this->assertSame('D', ModelloDichiarativo::iva->getTipoRecordQuadri());
        $this->assertTrue(ModelloDichiarativo::iva->isGestito());
        $dichiarazione = $this->creaDichiarazione();
        $this->assertSame('iva-2025', $dichiarazione->getRiferimento());
        $this->assertSame('IVA 2026 (anno 2025)', $dichiarazione->getDescrizione());
        $this->assertSame(2, Frontespizio::getCodiceNaturaGiuridica(NaturaGiuridica::societaResponsabilitaLimitata));
        $this->assertSame(25, Frontespizio::getCodiceNaturaGiuridica(NaturaGiuridica::societaAccomanditaSemplice));
        foreach (NaturaGiuridica::cases() as $natura) {
            $codice = Frontespizio::getCodiceNaturaGiuridica($natura);
            $this->assertArrayHasKey($codice, Frontespizio::TABELLA_NATURA_GIURIDICA, $natura->value);
        }
    }

    /**
     * Parole di una pagina di specifiche: intestazione della tabella,
     * titolo del quadro, un campo con la descrizione centrata su due righe,
     * una nota a tutta pagina e un campo il cui codice ha una cifra al
     * secondo carattere; la pagina successiva continua senza intestazione.
     */
    public function testLetturaDelleSpecifiche(): void
    {
        $parole = [
            [1, 30, 52, 'Quadro'], [1, 119, 59, 'Descrizione'], [1, 205, 63, 'Formato'], [1, 235, 63, 'Valori'], [1, 250, 63, 'Ammessi'],
            [1, 30, 80, 'QUADRO'], [1, 58, 80, 'VE'], [1, 68, 80, '-'], [1, 72, 80, 'Operazioni'], [1, 110, 80, 'attive'],
            [1, 64, 96, 'Imponibile'], [1, 100, 96, 'al'], [1, 110, 96, '22%'],
            [1, 30, 100, 'VE023001'], [1, 211, 100, 'NU'], [1, 300, 100, 'Deve'],
            [1, 64, 104, 'commerciali'],
            [1, 64, 120, 'Nota'], [1, 180, 120, 'che'], [1, 215, 120, 'attraversa'], [1, 280, 120, 'la'],
            [1, 30, 130, 'VE024001'], [1, 64, 130, 'Totale'], [1, 100, 130, 'imponibile'], [1, 211, 130, 'NU'],
            [2, 30, 40, 'V1000001'], [2, 64, 40, 'Quadri'], [2, 100, 40, 'compilati'], [2, 211, 40, 'AN'],
        ];
        $righe = ["level\tpage_num\tpar_num\tblock_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext"];
        foreach ($parole as [$pagina, $x, $y, $testo]) {
            $righe[] = implode("\t", [5, $pagina, 0, 0, 0, 0, $x, $y, strlen($testo) * 4, 6, 100, $testo]);
        }
        $lettore = new LettoreSpecifiche();
        $campi = $lettore->leggi(implode("\n", $righe));
        $this->assertSame(['VE023001', 'VE024001', 'V1000001'], array_keys($campi));
        $this->assertSame('Imponibile al 22% commerciali', $campi['VE023001']['descrizione']);
        $this->assertSame('NU', $campi['VE023001']['formato']);
        $this->assertSame('Totale imponibile', $campi['VE024001']['descrizione']);
        $this->assertSame(['descrizione' => 'Quadri compilati', 'formato' => 'AN', 'pagina' => 2], $campi['V1000001']);
        $this->assertSame(['VE' => 'Operazioni attive'], $lettore->getQuadri());
    }

    public function testCatalogoDalleSorgentiDelModulo(): void
    {
        $tabelle = new TabelleDichiarativi();
        $dati = $tabelle->leggi();
        $iva = array_column(array_filter($dati['campi'], fn(array $campo) => $campo['modello'] === 'IVA'), null, 'codice');
        $this->assertGreaterThan(1000, count($iva));
        $this->assertGreaterThan(4000, count($dati['campi']) - count($iva));
        foreach (['VE023001' => 'NU', 'VL033001' => 'NP', 'VA002001' => 'AN', 'V1000001' => 'AN', 'VH017002' => 'N1'] as $codice => $formato) {
            $this->assertSame($formato, $iva[$codice]['formato'], $codice);
        }
        $this->assertSame('VE', $iva['VE023001']['quadro']);
        $istruzioni = iterator_to_array($tabelle->istruzioni($dati), false);
        $this->assertStringStartsWith("DELETE FROM `campo_modello` WHERE `modello` = 'IVA' AND `anno` = 2026", $istruzioni[0]);
        $this->assertNotEmpty(array_filter($istruzioni, fn(string $istruzione) => str_starts_with($istruzione, 'INSERT INTO `quadro_modello`')));
    }

    public function testSorgentiNonValide(): void
    {
        $cartella = sys_get_temp_dir() . '/dichiarativi_' . uniqid() . '/';
        mkdir($cartella);
        file_put_contents($cartella . 'specifiche.csv', "modello;anno;url;provvedimento\nIVA;2026;https://esempio.it/a.pdf;Provvedimento\n");
        file_put_contents($cartella . 'quadri-IVA-2026.csv', "quadro;denominazione\nVE;Operazioni attive\n");
        file_put_contents($cartella . 'campi-IVA-2026.csv', "codice;formato;descrizione\nVE023001;XX;Imponibile\n");
        try {
            (new TabelleDichiarativi($cartella))->leggi();
            $this->fail('Formato sconosciuto accettato');
        } catch (\UnexpectedValueException $errore) {
            $this->assertStringContainsString('formato XX sconosciuto', $errore->getMessage());
        } finally {
            array_map('unlink', glob($cartella . '*'));
            rmdir($cartella);
        }
    }
}
