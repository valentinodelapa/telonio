<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\TabelleRiferimento\LettoreSpecificheIsa;
use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;
use PHPUnit\Framework\TestCase;

/**
 * Catalogo dei modelli ISA: formati delle specifiche tecniche ISA 2026
 * (esempi del "Documento esplicativo dei controlli del formato dei campi"),
 * lettura dei file delle specifiche e della tabella 1 delle istruzioni,
 * sorgenti del modulo ed etichette dei righi.
 */
class CatalogoIsaTest extends TestCase
{

    public function testFormatiIsa(): void
    {
        $casi = [
            ['VAL11', '182.000', '182000', '       00000182000', '182.000'],
            ['VALS11', '-36500', '-36500', '      -00000036500', '-36.500'],
            ['NU11', '60', '60', '       00000000060', '60'],
            ['PC3', '60', '60', '               060', '60'],
            ['PCD2', '68,1', '68.1', '            068,10', '68,1'],
            ['CBN3', '2', '2', '               002', '2'],
            ['CAT', 'h501', 'H501', 'H501              ', 'H501'],
            ['CA', '47.11.02', '471102', '471102            ', '471102'],
            ['NUC18', '2,12345', '2.12345', ' 2,123450000000000', '2,12345'],
            ['NUC18', '-0,5', '-0.5', '-0,500000000000000', '-0,5'],
            ['VAL11D2', '1,5', '1.5', '    00000000001,50', '1,50'],
            ['CB', '1', '1', '                 1', 'X'],
        ];
        foreach ($casi as [$formato, $inserito, $canonico, $campoValore, $mostrato]) {
            $caso = FormatoCampo::from($formato);
            $this->assertSame($canonico, $caso->normalizza($inserito), $formato);
            $this->assertSame([$campoValore], $caso->formatta($canonico, FormatoCampo::LUNGHEZZA_ISA), $formato);
            $this->assertSame($mostrato, $caso->mostra($canonico), $formato);
        }
        $this->assertTrue(FormatoCampo::importoIsa->isIsa());
        $this->assertFalse(FormatoCampo::positivo->isIsa());
        // percentuali fino a 100, importi positivi, zero non trasmesso
        $this->assertNull(FormatoCampo::importoIsa->normalizza('0'));
        foreach ([[FormatoCampo::percentualeIsa, '101'], [FormatoCampo::importoIsa, '-5'], [FormatoCampo::codiceCatastale, 'H50'], [FormatoCampo::numeroIsa2, '123']] as [$caso, $valore]) {
            try {
                $caso->normalizza($valore);
                $this->fail($caso->value . ' ' . $valore . ' accettato');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        // i formati dei modelli Redditi e IVA restano a 16 caratteri
        $this->assertSame(['           12345'], FormatoCampo::positivo->formatta('12345'));
    }

    public function testLetturaSpecificheEdElenco(): void
    {
        $lettore = new LettoreSpecificheIsa();
        $testo = "\"\";\"\";\"RECORD TIPO P\"\n\"Da\";\"A\"\n\f\"\";\"\";\"RECORD TIPO Q\"\n"
                . "\"Progressivo\";\"Codice campo\";\"Descrizione\";\"Formato\";\"Valore\";\"Note\"\n"
                . "\"1\";\"Y0100101\";\"Codice attività\";\"CA\";\"\";\"\"\n"
                . "\"31\";\"B0100401\";\"Tipologia di esercizio (1 = negozio tradizionale)\";\"CBN3\";\"0, 1, 2\";\"\"\n"
                . "\"40\";\"C0100801\";\"Commercio al dettaglio   in negozi\";\"PC\";\"\";\"\"\n"
                . "\"191\";\"IDF00101\";\"Coefficiente individuale\";\"NUC18\";\"\";\"valore fornito\"\n"
                . "\"300\";\"IIE00101\";\"Ricavi per addetto - Punteggio\";\"NU2D2\";\"\";\"\"\n\f\"#\";\"CONTROLLI IMPRESE\"\n";
        $campi = $lettore->leggiCampi($testo);
        // i risultati del calcolo (IIE...) restano fuori, le variabili precalcolate (IDF) dentro
        $this->assertSame(['Y0100101', 'B0100401', 'C0100801', 'IDF00101'], array_keys($campi));
        $this->assertSame('Commercio al dettaglio in negozi', $campi['C0100801']['descrizione']);
        $this->assertSame('0, 1, 2', $campi['B0100401']['valori']);
        $this->assertSame('PC3', LettoreSpecificheIsa::FORMATI[$campi['C0100801']['formato']]);

        $istruzioni = "TABELLA 1 – ELENCO DEGLI INDICI SINTETICI DI AFFIDABILITÀ FISCALE IN VIGORE\n           COMMERCIO\n"
                . " EM01A - Commercio al dettaglio alimentare\n         47.11.01 - Ipermercati\n         47.11.02 - Commercio al dettaglio non specializzato\n"
                . "                    con prevalenza di prodotti alimentari\n DM04U - Commercio all'ingrosso di prodotti farmaceutici\n         46.46.10 - Commercio all'ingrosso di medicinali\n";
        $elenco = $lettore->leggiElenco($istruzioni);
        $this->assertSame(['47.11.01', '47.11.02'], $elenco['EM01A']['attivita']);
        $this->assertSame('Commercio al dettaglio alimentare', $elenco['EM01A']['descrizione']);
        $this->assertCount(2, $elenco);
    }

    public function testSorgentiIsaDelModulo(): void
    {
        $dati = (new TabelleDichiarativi())->leggi();
        $isa = array_filter($dati['campi'], fn(array $campo) => $campo['modello'] === 'ISA');
        $this->assertGreaterThan(30000, count($isa));
        $em01a = array_column(array_filter($isa, fn(array $campo) => $campo['isa'] === 'EM01A'), null, 'codice');
        $this->assertSame('VALS11', $em01a['F0102001']['formato']);
        $this->assertSame('F', $em01a['F0102001']['quadro']);
        $this->assertSame('I', $em01a['IDF00101']['quadro']);
        // tutti i formati delle sorgenti ISA sono nel catalogo dei formati
        $this->assertSame([], array_values(array_filter(array_unique(array_column($isa, 'formato')), fn(string $formato) => FormatoCampo::tryFrom($formato) === null)));
        $indici = array_column($dati['isa'], null, 'codice');
        $this->assertCount(173, $indici);
        $this->assertStringContainsString('47.11.02', $indici['EM01A']['attivita']);
        // ogni ISA delle specifiche ha la sua riga nell'elenco
        $this->assertSame([], array_values(array_diff(array_unique(array_column($isa, 'isa')), array_keys($indici))));
        $istruzioni = iterator_to_array((new TabelleDichiarativi())->istruzioni($dati), false);
        $this->assertNotEmpty(array_filter($istruzioni, fn(string $istruzione) => str_starts_with($istruzione, 'INSERT INTO `indice_isa`')));
    }

    public function testEtichetteDeiRighi(): void
    {
        $campo = new CampoModello();
        $campo->modello = ModelloDichiarativo::isa;
        $campo->quadro = 'F';
        $campo->codice = 'F0101702';
        $this->assertSame('F17', $campo->getEtichettaRigo());
        $this->assertSame('2', $campo->getColonna());
        $campo->quadro = 'I';
        $campo->codice = 'IDF00101';
        $this->assertSame('IDF001', $campo->getEtichettaRigo());
        $redditi = new CampoModello();
        $redditi->modello = ModelloDichiarativo::redditiPf;
        $redditi->quadro = 'RG';
        $redditi->codice = 'RG002002';
        $this->assertSame('RG2', $redditi->getEtichettaRigo());
        $this->assertSame('2', $redditi->getColonna());
    }
}
