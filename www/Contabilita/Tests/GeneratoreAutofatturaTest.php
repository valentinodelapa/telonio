<?php

namespace Contabilita\Tests;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\FatturaPa\GeneratoreAutofattura;
use Contabilita\Application\Services\FatturaPa\LetturaFatturaPa;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Autofattura degli acquisti da agricoltori esonerati: file valido sullo
 * schema FatturaPA, emesso dal cessionario per conto del cedente.
 */
class GeneratoreAutofatturaTest extends ContabilitaTestCase
{

    private function registrazione(bool $esonerato = true)
    {
        $controparte = new Controparte($this->dataMapper);
        $controparte->id = 9;
        $controparte->denominazione = 'Fiore Rosa';
        $controparte->partitaIva = '09990010002';
        $controparte->codiceFiscale = 'FRORSO70A41L840L';
        $controparte->indirizzo = 'Via dei Campi 2';
        $controparte->cap = '36100';
        $controparte->comune = 'Vicenza';
        $controparte->provincia = 'VI';
        $controparte->agricoltoreEsonerato = $esonerato;
        $registrazione = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-07-01');
        $registrazione->id = 123;
        $registrazione->controparte = $controparte;
        $registrazione->descrizione = 'Uva da vino';
        $registrazione->numeroDocumento = 'AF/1';
        $registrazione->dataDocumento = new SismaDate('2025-07-01');
        return $registrazione;
    }

    private function sede(): void
    {
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'Vicenza';
        $comune->siglaProvincia = 'VI';
        $sede = new Indirizzo($this->dataMapper);
        $sede->id = 1;
        $sede->soggetto = $this->azienda;
        $sede->tipoIndirizzo = TipoIndirizzo::sedeLegale;
        $sede->validoDal = new SismaDate('2020-01-01');
        $sede->indirizzo = 'Via Roma';
        $sede->numeroCivico = '1';
        $sede->cap = '36100';
        $sede->comune = $comune;
        $this->testAdapter->impostaSelectCallback(fn(string $cmd): TestResultSet => new TestResultSet(preg_match('/FROM .indirizzo./', $cmd) === 1 ? [$sede] : []));
    }

    public function testFileValidoConLaPercentualeDiCompensazione(): void
    {
        $this->sede();
        $registrazione = $this->registrazione();
        $riga = $this->creaRigaIva('10', 1000.0, 1, null, $registrazione);
        $xml = (new GeneratoreAutofattura($this->dataMapper))->componi($registrazione, [$riga]);
        $this->assertStringContainsString('<SoggettoEmittente>CC</SoggettoEmittente>', $xml);
        $this->assertStringContainsString('<TipoDocumento>TD01</TipoDocumento>', $xml);
        $this->assertStringContainsString('<RegimeFiscale>RF04</RegimeFiscale>', $xml);
        $this->assertStringContainsString('<AliquotaIVA>10.00</AliquotaIVA>', $xml);
        $letta = (new LetturaFatturaPa())->leggi($xml);
        $this->assertSame('Fiore Rosa', $letta->cedente->getDenominazione());
    }

    public function testSoloPerGliEsonerati(): void
    {
        $registrazione = $this->registrazione(false);
        $this->assertFalse(GeneratoreAutofattura::isAmmessa($registrazione));
        $this->expectException(\InvalidArgumentException::class);
        (new GeneratoreAutofattura($this->dataMapper))->componi($registrazione, [$this->creaRigaIva('10', 1000.0, 1, null, $registrazione)]);
    }

    public function testNumeroObbligatorio(): void
    {
        $registrazione = $this->registrazione();
        $registrazione->numeroDocumento = null;
        $this->expectExceptionMessage('numero e data');
        (new GeneratoreAutofattura($this->dataMapper))->componi($registrazione, [$this->creaRigaIva('10', 1000.0, 1, null, $registrazione)]);
    }

    public function testNomeFile(): void
    {
        $registrazione = $this->registrazione();
        $this->assertSame('IT' . $registrazione->soggetto->codiceFiscale . '_0003F.xml', GeneratoreAutofattura::getNomeFile($registrazione));
    }
}
