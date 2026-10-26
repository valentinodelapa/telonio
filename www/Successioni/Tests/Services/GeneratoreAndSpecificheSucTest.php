<?php

namespace Successioni\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Entities\AllegatoSuccessione;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\ContenutoAllegato;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\RipartizionePassivita;
use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoAllegato;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipologiaTerreno;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Application\Enumerations\TipoTitolo;
use Successioni\Application\Enumerations\TipoUnita;
use Successioni\Application\Services\DatiDichiarazione;
use Successioni\Application\Services\Liquidazione;
use Successioni\Application\Services\TabelleRiferimento\SpecificheSuc;
use Successioni\Application\Services\Telematico\GeneratoreSuc;

class GeneratoreAndSpecificheSucTest extends SuiteTestCase
{

    public function testGeneratoreSucGeneraEValidaXsd(): void
    {
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'ROMA';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';

        $nazione = new Nazione($this->dataMapper);
        $nazione->id = 1;
        $nazione->codiceIso = 'IT';
        $nazione->denominazione = 'ITALIA';

        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        $defunto->sesso = Sesso::maschio;
        $defunto->dataNascita = new SismaDate('1950-01-01');
        $defunto->comuneNascita = $comune;
        $defunto->dataDecesso = new SismaDate('2025-03-10');

        $erede = $this->creaSoggettoFisico(11, $this->studioTest, 'RSSGPP80A01H501K', 'ROSSI', 'GIUSEPPE');
        $erede->sesso = Sesso::maschio;
        $erede->dataNascita = new SismaDate('1980-01-01');
        $erede->comuneNascita = $comune;

        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->id = 1;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->defunto = $defunto;
        $dichiarazione->comuneDecesso = $comune;
        $dichiarazione->statoCivile = StatoCivileDefunto::vedovo;
        $dichiarazione->presentatore = $erede;
        $dichiarazione->codiceCarica = CaricaDichiarante::erede;
        $dichiarazione->telefono = '06 1234567';
        $dichiarazione->email = 'giuseppe@example.it';
        $dichiarazione->tempisticaPagamento = TempisticaPagamento::nonContestuale;
        $dichiarazione->dataDichiarazione = new SismaDate('2025-06-01');

        $conto = new ContoCorrente($this->dataMapper);
        $conto->id = 1;
        $conto->soggetto = $erede;
        $conto->iban = 'IT60X0542811101000000123456';
        $dichiarazione->contoAddebito = $conto;

        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->id = 1;
        $beneficiario->dichiarazioneSuccessione = $dichiarazione;
        $beneficiario->soggetto = $erede;
        $beneficiario->tipoBeneficiario = TipoBeneficiario::erede;
        $beneficiario->gradoParentela = GradoParentela::figlio;

        $immobileFab = new Immobile($this->dataMapper);
        $immobileFab->id = 1;
        $immobileFab->studio = $this->studioTest;
        $immobileFab->tipoCatasto = TipoCatasto::fabbricati;
        $immobileFab->comune = $comune;
        $immobileFab->foglio = '12';
        $immobileFab->particella = '345';
        $immobileFab->subalterno = '6';
        $immobileFab->indirizzo = 'VIA NAZIONALE';
        $immobileFab->numeroCivico = '10';

        $catastoFab = new DatiCatastali($this->dataMapper);
        $catastoFab->id = 1;
        $catastoFab->immobile = $immobileFab;
        $catastoFab->categoria = CategoriaCatastale::a2;
        $catastoFab->classe = '3';
        $catastoFab->consistenza = 5.5;
        $catastoFab->rendita = 800.0;
        $catastoFab->validoDal = new SismaDate('2015-01-01');

        $beneFab = new BeneSuccessione($this->dataMapper);
        $beneFab->id = 1;
        $beneFab->dichiarazioneSuccessione = $dichiarazione;
        $beneFab->quadro = QuadroBene::fabbricati;
        $beneFab->immobile = $immobileFab;
        $beneFab->codiceDiritto = CodiceDiritto::proprieta;
        $beneFab->valore = 92400;

        $devFab = new DevoluzioneSuccessione($this->dataMapper);
        $devFab->id = 1;
        $devFab->beneSuccessione = $beneFab;
        $devFab->beneficiarioSuccessione = $beneficiario;
        $devFab->quotaNumeratore = 1;
        $devFab->quotaDenominatore = 1;
        $devFab->codiceDiritto = CodiceDiritto::proprieta;
        $devFab->valoreQuota = 92400.0;
        $devFab->agevolazione = Agevolazione::primaCasa;

        $beneEr = new BeneSuccessione($this->dataMapper);
        $beneEr->id = 2;
        $beneEr->dichiarazioneSuccessione = $dichiarazione;
        $beneEr->quadro = QuadroBene::altriBeni;
        $beneEr->tipoCespite = TipoCespite::crediti;
        $beneEr->descrizione = 'CONTO CORRENTE N. 123';
        $beneEr->codiceDiritto = CodiceDiritto::proprieta;
        $beneEr->valore = 15000;

        $devEr = new DevoluzioneSuccessione($this->dataMapper);
        $devEr->id = 2;
        $devEr->beneSuccessione = $beneEr;
        $devEr->beneficiarioSuccessione = $beneficiario;
        $devEr->quotaNumeratore = 1;
        $devEr->quotaDenominatore = 1;
        $devEr->codiceDiritto = CodiceDiritto::proprieta;
        $devEr->valoreQuota = 15000.0;

        $passivita = new PassivitaSuccessione($this->dataMapper);
        $passivita->id = 1;
        $passivita->dichiarazioneSuccessione = $dichiarazione;
        $passivita->tipoPassivita = TipoPassivita::speseFunerarie;
        $passivita->descrizione = 'SPESE FUNEBRI';
        $passivita->estremiDocumento = 'FATTURA 10';
        $passivita->dataDocumento = new SismaDate('2025-03-12');
        $passivita->valore = 1000;

        $rip = new RipartizionePassivita($this->dataMapper);
        $rip->id = 1;
        $rip->passivitaSuccessione = $passivita;
        $rip->beneficiarioSuccessione = $beneficiario;
        $rip->quotaNumeratore = 1;
        $rip->quotaDenominatore = 1;
        $rip->valoreQuota = 1000.0;

        $contenutoAll = new ContenutoAllegato($this->dataMapper);
        $contenutoAll->id = 1;
        $contenutoAll->contenuto = base64_encode('%PDF-1.4 <pdfaid:part>1</pdfaid:part><pdfaid:conformance>B</pdfaid:conformance>');

        $allegato1 = new AllegatoSuccessione($this->dataMapper);
        $allegato1->id = 1;
        $allegato1->dichiarazioneSuccessione = $dichiarazione;
        $allegato1->tipoAllegato = TipoAllegato::alberoGenealogico;
        $allegato1->nomeFile = 'albero.pdf';
        $allegato1->tipoMime = 'application/pdf';
        $allegato1->dimensione = 80;
        $allegato1->contenutoAllegato = $contenutoAll;

        $allegato2 = new AllegatoSuccessione($this->dataMapper);
        $allegato2->id = 2;
        $allegato2->dichiarazioneSuccessione = $dichiarazione;
        $allegato2->tipoAllegato = TipoAllegato::documentiPassivita;
        $allegato2->nomeFile = 'fattura.pdf';
        $allegato2->tipoMime = 'application/pdf';
        $allegato2->dimensione = 80;
        $allegato2->contenutoAllegato = $contenutoAll;

        $this->adapter->impostaSelectCallback(function (string $query, array $bindValues) use ($beneficiario, $beneFab, $beneEr, $devFab, $devEr, $catastoFab, $passivita, $rip, $allegato1, $allegato2): TestResultSet {
            if (str_contains($query, 'FROM `beneficiario_successione`')) {
                return new TestResultSet([$beneficiario]);
            }
            if (str_contains($query, 'FROM `bene_successione`')) {
                return new TestResultSet([$beneFab, $beneEr]);
            }
            if (str_contains($query, 'FROM `devoluzione_successione`')) {
                return in_array(1, $bindValues, true) ? new TestResultSet([$devFab]) : new TestResultSet([$devEr]);
            }
            if (str_contains($query, 'FROM `dati_catastali`')) {
                return new TestResultSet([$catastoFab]);
            }
            if (str_contains($query, 'FROM `passivita_successione`')) {
                return new TestResultSet([$passivita]);
            }
            if (str_contains($query, 'FROM `ripartizione_passivita`')) {
                return new TestResultSet([$rip]);
            }
            if (str_contains($query, 'FROM `allegato_successione`')) {
                return new TestResultSet([$allegato1, $allegato2]);
            }
            return new TestResultSet();
        });

        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        $risultato = (new Liquidazione())->liquida($dati);

        $generatore = new GeneratoreSuc();
        $xml = $generatore->genera($dati, $risultato);

        $this->assertStringContainsString('CodiceFornitura="SUC13"', $xml);
        $this->assertStringContainsString('suc:Frontespizio', $xml);
        $this->assertStringContainsString('suc:QuadroEA', $xml);
        $this->assertStringContainsString('suc:QuadroEC', $xml);
        $this->assertStringContainsString('suc:QuadroED', $xml);
        $this->assertStringContainsString('suc:QuadroEE', $xml);
        $this->assertStringContainsString('suc:QuadroEF', $xml);
        $this->assertStringContainsString('suc:QuadroEG', $xml);
        $this->assertStringContainsString('suc:QuadroEH', $xml);
        $this->assertStringContainsString('suc:QuadroER', $xml);

        // Validazione XSD ufficiale dell'Agenzia
        $erroriXsd = GeneratoreSuc::valida($xml);
        $this->assertEmpty($erroriXsd, 'Errori XSD: ' . implode('; ', $erroriXsd));

        $this->assertSame('SUC13_RSSMRA50A01H501U_1.xml', GeneratoreSuc::getNomeFile($dati));
        $this->assertSame('ROSSI MARIO', GeneratoreSuc::testo('  Rossi   Mario  ', 50));
    }

    public function testSpecificheSucVerificaEnumELetturaTabelle(): void
    {
        $specifiche = new SpecificheSuc();
        $cartellaXsd = SpecificheSuc::getCartellaXsd();
        $this->assertDirectoryExists($cartellaXsd);

        // Tutti gli enum del modulo devono coincidere con gli XSD
        $differenze = $specifiche->verificaEnum($cartellaXsd);
        $this->assertEmpty($differenze, 'Differenze enum XSD: ' . implode('; ', $differenze));

        $uffici = $specifiche->leggiUffici($cartellaXsd);
        $this->assertGreaterThan(500, count($uffici));
        $this->assertArrayHasKey('codice', $uffici[0]);
        $this->assertArrayHasKey('denominazione', $uffici[0]);

        $trascrizioni = $specifiche->leggiUfficiTrascrizione($cartellaXsd);
        $this->assertGreaterThan(100, count($trascrizioni));

        $comuni = $specifiche->leggiComuniCatastali($cartellaXsd);
        $this->assertGreaterThan(600, count($comuni));
    }
}
