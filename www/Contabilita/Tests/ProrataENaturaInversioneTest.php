<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\ProrataIva;
use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Forms\RigaIvaForm;
use Contabilita\Application\Services\CalcoloLiquidazioneIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Pro-rata di detrazione provvisorio nelle liquidazioni periodiche,
 * tipologia dell'inversione contabile, qualifica e aliquota teorica delle
 * righe IVA.
 */
class ProrataENaturaInversioneTest extends ContabilitaTestCase
{

    public function testProrataProvvisorioRiduceLIvaDetraibile(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita);
        $vendita->id = 1;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto);
        $acquisto->id = 2;
        $prorata = new ProrataIva($this->dataMapper);
        $prorata->id = 1;
        $prorata->soggetto = $this->azienda;
        $prorata->anno = 2026;
        $prorata->percentuale = 15;
        $this->rispondiConti(['riga_iva' => [$this->creaRigaIva('22', 1000.0, 1, null, $vendita), $this->creaRigaIva('22', 2000.0, 1, null, $acquisto)],
            'prorata_iva' => [$prorata]]);
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($this->azienda, new SismaDate('2026-02-01'), new SismaDate('2026-02-28'));
        $this->assertSame(220.0, $risultato->ivaEsigibile);
        // 15% di 440
        $this->assertSame(66.0, $risultato->ivaDetraibile);
        $this->assertSame(15, $risultato->percentualeDetrazione);
        $this->assertStringContainsString('Pro-rata provvisorio del 15%', $risultato->avvisi[0]);
        $this->assertSame(1500.0, $prorata->applica(10000.0));
    }

    public function testSenzaProrataDetrazionePerIntero(): void
    {
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto);
        $acquisto->id = 2;
        $riga = $this->creaRigaIva('RC22', 1000.0, 1, null, $acquisto);
        $riga->naturaInversione = NaturaIva::inversioneSubappaltoEdile;
        $this->rispondiConti(['riga_iva' => [$riga]]);
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($this->azienda, new SismaDate('2026-02-01'), new SismaDate('2026-02-28'));
        $this->assertSame(220.0, $risultato->ivaDetraibile);
        $this->assertSame(100, $risultato->percentualeDetrazione);
        $this->assertTrue($riga->naturaInversione->isInversioneContabile());
    }

    public function testQualificaEAliquotaTeoricaCompatibiliColCodiceIva(): void
    {
        $intento = new CodiceIva($this->dataMapper);
        $intento->id = 40;
        $intento->codice = 'N3.5';
        $intento->descrizione = 'Non imponibile con dichiarazione d\'intento';
        $intento->aliquota = 0.0;
        $intento->natura = NaturaIva::dichiarazioniIntento;
        $intento->detraibilita = 100.0;
        foreach ([[$this->codiciIva['N4'], ['qualifica' => 'E'], true], [$this->codiciIva['22'], ['qualifica' => 'E'], false], [$this->codiciIva['22'], ['qualifica' => 'A'], true],
            [$this->codiciIva['N4'], ['qualifica' => 'F'], false], [$intento, ['aliquotaTeorica' => '10'], true], [$this->codiciIva['22'], ['aliquotaTeorica' => '10'], false],
            [$intento, ['aliquotaTeorica' => '0'], false], [$this->codiciIva['22'], ['naturaInversione' => 'N6.3'], false]] as $indice => [$codice, $campi, $valido]) {
            $this->rispondiConti(['codice_iva' => [$codice]]);
            $form = new RigaIvaForm(new RigaIva($this->dataMapper), $this->dataMapper);
            $form->handleRequest($this->creaRequest(['submitted' => '1', 'codiceIva' => (string) $codice->id, 'imponibile' => '100,00', 'imposta' => '0,00'] + $campi));
            $this->assertSame($valido, $form->isValid(), 'caso ' . $indice);
        }
        $this->assertTrue(QualificaOperazione::beneAmmortizzabile->isAmmessa(null));
        $this->assertTrue(QualificaOperazione::fuoriCampoSenzaDetrazione->isAmmessa(NaturaIva::nonSoggetteTerritorialita));
    }
}
