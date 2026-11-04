<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Telematico\RecordQuadri;
use Dichiarativi\Application\Services\Telematico\GeneratoreIva;

/**
 * Dati dei quadri con i valori proposti e manuali, record a struttura
 * variabile e controlli di rispondenza della dichiarazione IVA.
 */
class QuadriETelematicoTest extends DichiarativiTestCase
{

    public function testValoriPropostiEManuali(): void
    {
        $quadri = $this->creaQuadri();
        $quadri->avviaProposta('VE');
        $quadri->proponi('VE023001', 1000.4);
        $quadri->proponi('VE023002', 220);
        $this->assertSame('1000', $quadri->get('VE023001'));
        $this->assertFalse($quadri->isManuale('VE023001'));
        // il valore manuale prevale sulle proposte successive, che lo segnalano
        $quadri->imposta('VE023002', '221');
        $quadri->avviaProposta('VE');
        $quadri->proponi('VE023001', 2000);
        $quadri->proponi('VE023002', 440);
        $this->assertSame('2000', $quadri->get('VE023001'));
        $this->assertSame('221', $quadri->get('VE023002'));
        $this->assertTrue($quadri->isManuale('VE023002'));
        $this->assertCount(1, $quadri->getDifferenzeManuali());
        $this->assertStringContainsString('proposto 440', $quadri->getDifferenzeManuali()[0]);
        // un campo svuotato a mano resta vuoto
        $quadri->imposta('VE023001', '');
        $quadri->proponi('VE023001', 3000);
        $this->assertSame(0, $quadri->getImporto('VE023001'));
        // ripristinato torna proposto; i valori proposti non riproposti si eliminano
        $quadri->ripristina('VE023002');
        $quadri->avviaProposta('VE');
        $this->assertNull($quadri->get('VE023002'));
        $this->assertSame(['VE'], $quadri->getQuadriCompilati());
        $this->assertSame(2000 + 0, $quadri->somma('VE024001') + 2000);
        try {
            $quadri->proponi('ZZ999999', 1);
            $this->fail('Campo fuori catalogo');
        } catch (\InvalidArgumentException $errore) {
            $this->assertStringContainsString('ZZ999999', $errore->getMessage());
        }
        try {
            $quadri->imposta('VL033001', '-10');
            $this->fail('Importo negativo in un campo NP');
        } catch (\InvalidArgumentException $errore) {
            $this->assertStringContainsString('VL033001', $errore->getMessage());
        }
    }

    public function testCampiSenzaFormatoNonCompilabili(): void
    {
        $quadri = $this->creaQuadri();
        $this->assertNull($this->getCatalogoIva()['VP014001']->formato);
        $quadri->proponi('VP014001', 0);
        $this->assertNull($quadri->get('VP014001'));
        $this->expectException(\InvalidArgumentException::class);
        $quadri->imposta('VP014001', '150');
    }

    public function testRecordAStrutturaVariabile(): void
    {
        $valori = [1 => ['VE023001' => '30000', 'VE023002' => '6600', 'V1000001' => 'VAVEVFVJVHVLVXVO']];
        for ($rigo = 1; $rigo <= 13; $rigo++) {
            $valori[1][sprintf('VF%03d001', $rigo)] = (string) ($rigo * 100);
            $valori[1][sprintf('VF%03d002', $rigo)] = (string) $rigo;
        }
        for ($rigo = 1; $rigo <= 16; $rigo++) {
            $valori[1][sprintf('VH%03d001', $rigo)] = (string) $rigo;
            $valori[1][sprintf('VH%03d002', $rigo)] = (string) $rigo;
        }
        $valori[1]['VH001003'] = '1';
        // testo lungo: un elemento ogni 15 caratteri dopo il primo
        $valori[1]['VA004001'] = str_repeat('FONDO IMMOBILIARE ', 12);
        $record = RecordQuadri::genera('D', '01234567897', $valori, $this->getCatalogoIva());
        // 3 + 26 + 33 + 15 = 77 elementi: due record per lo stesso modulo
        $this->assertCount(1, array_unique(array_map(fn(string $record) => substr($record, 17, 8), $record)));
        $this->assertCount(2, $record);
        foreach ($record as $testo) {
            $this->assertSame(1900, strlen($testo));
            $this->assertSame('D01234567897     00000001', substr($testo, 0, 25));
            $this->assertSame("A\r\n", substr($testo, -3));
        }
        $elementi = str_split(substr($record[0], 89, 1800), 24);
        $this->assertSame('V1000001VAVEVFVJVHVLVXVO', $elementi[0]);
        $this->assertSame('VA004001FONDO IMMOBILIAR', $elementi[1]);
        $this->assertSame('VA004001+E FONDO IMMOBIL', $elementi[2]);
        $this->assertContains('VE023001           30000', $elementi);
        $this->assertContains('VH001003               1', array_merge($elementi, str_split(substr($record[1], 89, 1800), 24)));
        $this->expectException(\InvalidArgumentException::class);
        RecordQuadri::genera('D', '01234567897', [1 => ['VP014001' => '10']], $this->getCatalogoIva());
    }

    public function testControlliDiRispondenza(): void
    {
        $quadri = $this->creaQuadri();
        foreach (['VE023001' => 30000, 'VE023002' => 6600, 'VE024001' => 30000, 'VE024002' => 6600, 'VE026002' => 6600, 'VE050001' => 30000,
    'VF013001' => 10000, 'VF013002' => 2200, 'VF025001' => 10000, 'VF025002' => 2200, 'VF027002' => 2200, 'VF029004' => 10000, 'VF071002' => 2200,
    'VL001001' => 6600, 'VL002001' => 2200, 'VL003001' => 4400, 'VL030001' => 4000, 'VL030002' => 4000, 'VL030003' => 4000,
    'VL032001' => 400, 'VL038001' => 400, 'VX001001' => 400, 'VT001001' => 30000, 'VT001002' => 6600, 'VT001005' => 30000, 'VT001006' => 6600] as $codice => $valore) {
            $quadri->imposta($codice, $valore);
        }
        $controlli = new ControlliIva($this->dataMapper);
        $this->assertSame([], $controlli->getSegnalazioni($quadri));
        $quadri->imposta('VE023002', 6000);
        $segnalazioni = $controlli->getSegnalazioni($quadri);
        $this->assertNotEmpty($segnalazioni);
        $this->assertStringStartsWith('VE023002: indicato 6.000, risulta 6.600', $segnalazioni[0]);
        $this->assertContains('VE024002: indicato 6.600, risulta 6.000.', $segnalazioni);
        $quadri->imposta('VE023002', 6600);
        $quadri->imposta('VX005001', 6000);
        $this->assertNotEmpty($controlli->getAvvertenze($quadri));
        $this->assertSame('IVA26_01234567897.txt', GeneratoreIva::getNomeFile($quadri->getDichiarazione()));
    }
}
