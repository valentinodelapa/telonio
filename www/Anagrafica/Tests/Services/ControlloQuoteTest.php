<?php

namespace Anagrafica\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Services\ControlloQuote;
use Telonio\Tests\SuiteTestCase;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ControlloQuoteTest extends SuiteTestCase
{

    private ControlloQuote $controllo;
    private Immobile $immobile;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->controllo = new ControlloQuote($this->dataMapper);

        $studio = $this->creaStudio();
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->codiceCatastale = 'H501';
        $comune->denominazione = 'Roma';

        $this->immobile = new Immobile($this->dataMapper);
        $this->immobile->id = 10;
        $this->immobile->studio = $studio;
        $this->immobile->tipoCatasto = TipoCatasto::fabbricati;
        $this->immobile->comune = $comune;
        $this->immobile->foglio = '1';
        $this->immobile->particella = '100';
    }

    private function creaSoggetto(int $id): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = $id;
        $soggetto->studio = $this->immobile->studio;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'SOGGETTO ' . $id;
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        return $soggetto;
    }

    private function creaPossesso(int $soggettoId, TitoloPossesso $titolo, float $quota, string $validoDal, ?string $validoAl = null): Possesso
    {
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $this->immobile;
        $possesso->soggetto = $this->creaSoggetto($soggettoId);
        $possesso->titolo = $titolo;
        $possesso->quota = $quota;
        $possesso->validoDal = new SismaDate($validoDal);
        $possesso->validoAl = $validoAl !== null ? new SismaDate($validoAl) : null;
        return $possesso;
    }

    public function testVerificaImmobileNuovoSenzaId(): void
    {
        $immobileNuovo = new Immobile($this->dataMapper);
        $possesso = new Possesso($this->dataMapper);
        $possesso->immobile = $immobileNuovo;
        $possesso->soggetto = $this->creaSoggetto(1);
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->quota = 100.0;
        $possesso->validoDal = new SismaDate('2024-01-01');

        $this->assertNull($this->controllo->verifica($possesso));
    }

    public function testVerificaComproprietaEntro100(): void
    {
        $esistente = $this->creaPossesso(1, TitoloPossesso::proprieta, 50.0, '2020-01-01');
        $nuovo = $this->creaPossesso(2, TitoloPossesso::proprieta, 50.0, '2024-01-01');
        $this->testAdapter->accodaSelect([$esistente]);

        $this->assertNull($this->controllo->verifica($nuovo));
    }

    public function testVerificaEccedePienaProprieta(): void
    {
        $esistente = $this->creaPossesso(1, TitoloPossesso::proprieta, 60.0, '2020-01-01');
        $nuovo = $this->creaPossesso(2, TitoloPossesso::proprieta, 50.0, '2024-01-01');
        $this->testAdapter->accodaSelect([$esistente]);

        $errore = $this->controllo->verifica($nuovo);
        $this->assertNotNull($errore);
        $this->assertStringContainsString('01/01/2024', $errore);
        $this->assertStringContainsString('110,00%', $errore);
    }

    public function testVerificaNudaProprietaEdUsufruttoCompatibiliAl100(): void
    {
        $nudoProprietario = $this->creaPossesso(1, TitoloPossesso::nudaProprieta, 100.0, '2020-01-01');
        $usufruttuario = $this->creaPossesso(2, TitoloPossesso::usufrutto, 100.0, '2020-01-01');
        $this->testAdapter->accodaSelect([$nudoProprietario]);

        $this->assertNull($this->controllo->verifica($usufruttuario));
    }

    public function testVerificaUsufruttoEPienaProprietaConfliggonoSuGodimento(): void
    {
        $pienoProprietario = $this->creaPossesso(1, TitoloPossesso::proprieta, 50.0, '2020-01-01');
        $usufruttuario = $this->creaPossesso(2, TitoloPossesso::usufrutto, 60.0, '2024-01-01');
        $this->testAdapter->accodaSelect([$pienoProprietario]);

        $errore = $this->controllo->verifica($usufruttuario);
        $this->assertNotNull($errore);
        $this->assertStringContainsString('110,00%', $errore);
    }

    public function testVerificaIgnoraAltraVersioneDelloStessoSoggettoETitolo(): void
    {
        $versionePrecedente = $this->creaPossesso(1, TitoloPossesso::proprieta, 100.0, '2020-01-01');
        $nuovaVersione = $this->creaPossesso(1, TitoloPossesso::proprieta, 50.0, '2024-01-01');
        $this->testAdapter->accodaSelect([$versionePrecedente]);

        $this->assertNull($this->controllo->verifica($nuovaVersione));
    }

    public function testVerificaIgnoraPossessiCessatiPrimaDellaDecorrenza(): void
    {
        $cessato = $this->creaPossesso(1, TitoloPossesso::proprieta, 100.0, '2020-01-01', '2023-12-31');
        $nuovo = $this->creaPossesso(2, TitoloPossesso::proprieta, 100.0, '2024-01-01');
        $this->testAdapter->accodaSelect([$cessato]);

        $this->assertNull($this->controllo->verifica($nuovo));
    }
}
