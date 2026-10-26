<?php

namespace Successioni\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
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
use Successioni\Application\Enumerations\CaricaDichiarante;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\StatoCivileDefunto;
use Successioni\Application\Enumerations\StatoDichiarazioneSuccessione;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoAllegato;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Application\Services\ControlloDichiarazione;
use Successioni\Application\Services\CopiaDichiarazione;
use Successioni\Application\Services\DatiDichiarazione;
use Successioni\Application\Services\Liquidazione;
use Successioni\Application\Services\PropostaAttivo;
use Successioni\Application\Services\PropostaVolture;
use Successioni\Application\Services\RiepilogoScrivania;
use Successioni\Application\Services\VerificaAllegato;

class ControlloProposteAndAllegatiTest extends SuiteTestCase
{

    public function testVerificaAllegatoPdfATiffAndErrori(): void
    {
        $servizio = new VerificaAllegato();

        // File oltre 5 MB
        $grande = str_repeat('A', AllegatoSuccessione::DIMENSIONE_MASSIMA + 10);
        $esitoGrande = $servizio->verifica($grande, 'grande.pdf');
        $this->assertNotNull($esitoGrande['errore']);

        // Formato non ammesso
        $esitoTxt = $servizio->verifica('Testo semplice', 'nota.txt');
        $this->assertNull($esitoTxt['tipoMime']);
        $this->assertStringContainsString('Formato non ammesso', (string) $esitoTxt['errore']);

        // PDF senza PDF/A
        $esitoPdfNormale = $servizio->verifica('%PDF-1.4 contenuto normale', 'doc.pdf');
        $this->assertSame('application/pdf', $esitoPdfNormale['tipoMime']);
        $this->assertStringContainsString('non dichiara la conformità PDF/A', (string) $esitoPdfNormale['errore']);

        // PDF/A-2b non ammesso
        $esitoPdfA2 = $servizio->verifica('%PDF-1.4 <pdfaid:part>2</pdfaid:part><pdfaid:conformance>B</pdfaid:conformance>', 'doc.pdf');
        $this->assertStringContainsString('PDF/A-2b', (string) $esitoPdfA2['errore']);

        // PDF/A-1b valido
        $esitoPdfA1b = $servizio->verifica('%PDF-1.4 <pdfaid:part>1</pdfaid:part><pdfaid:conformance>B</pdfaid:conformance>', 'doc.pdf');
        $this->assertSame('application/pdf', $esitoPdfA1b['tipoMime']);
        $this->assertNull($esitoPdfA1b['errore']);

        // Costruzione di un TIFF little-endian con tag 258 (1 bit), 259 (4 = CCITT Group IV), 282/283 (200 DPI), 296 (2 = inch)
        // Header (8 byte): "II*\0" + offset IFD = 8
        // IFD: 5 voci (2 byte) + 5 * 12 = 60 byte + 4 byte fine + 16 byte per le due frazioni (offset 74 e 82)
        $ifd = pack('v', 5)
                . pack('vvVV', 258, 3, 1, 1) // BitsPerSample = 1
                . pack('vvVV', 259, 3, 1, 4) // Compression = 4 (CCITT Group IV)
                . pack('vvVV', 282, 5, 1, 74) // XResolution offset 74
                . pack('vvVV', 283, 5, 1, 82) // YResolution offset 82
                . pack('vvVV', 296, 3, 1, 2) // ResolutionUnit = 2 (inch)
                . pack('V', 0)
                . pack('VV', 200, 1) // 200/1 DPI
                . pack('VV', 200, 1);
        $tiffValido = "II*\0" . pack('V', 8) . $ifd;
        $esitoTiff = $servizio->verifica($tiffValido, 'scansione.tif');
        $this->assertSame('image/tif', $esitoTiff['tipoMime']);
        $this->assertNull($esitoTiff['errore']);

        // TIFF con compressione errata
        $ifdErr = pack('v', 1) . pack('vvVV', 259, 3, 1, 1) . pack('V', 0);
        $esitoTiffErr = $servizio->verifica("II*\0" . pack('V', 8) . $ifdErr, 'scansione.tiff');
        $this->assertStringContainsString('CCITT Group IV', (string) $esitoTiffErr['errore']);
    }

    public function testPropostaAttivoAndPropostaVoltureAndCopiaDichiarazione(): void
    {
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'ROMA';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';

        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        $defunto->dataDecesso = new SismaDate('2025-03-10');
        $defunto->sesso = Sesso::maschio;
        $defunto->dataNascita = new SismaDate('1950-01-01');
        $defunto->comuneNascita = $comune;

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
        $dichiarazione->email = 'giuseppe@example.it';
        $dichiarazione->tempisticaPagamento = TempisticaPagamento::nonContestuale;
        $dichiarazione->dataDichiarazione = new SismaDate('2025-06-01');

        $conto = new ContoCorrente($this->dataMapper);
        $conto->id = 1;
        $conto->soggetto = $erede;
        $conto->iban = 'IT60X0542811101000000123456';
        $dichiarazione->contoAddebito = $conto;

        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 1;
        $immobile->studio = $this->studioTest;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = $comune;
        $immobile->foglio = '1';
        $immobile->particella = '10';

        $possessoDefunto = new Possesso($this->dataMapper);
        $possessoDefunto->id = 1;
        $possessoDefunto->immobile = $immobile;
        $possessoDefunto->soggetto = $defunto;
        $possessoDefunto->titolo = TitoloPossesso::proprieta;
        $possessoDefunto->quota = 100.0;
        $possessoDefunto->validoDal = new SismaDate('2010-01-01');

        $catasto = new DatiCatastali($this->dataMapper);
        $catasto->id = 1;
        $catasto->immobile = $immobile;
        $catasto->categoria = CategoriaCatastale::a2;
        $catasto->rendita = 500.0;
        $catasto->validoDal = new SismaDate('2010-01-01');

        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->id = 1;
        $beneficiario->dichiarazioneSuccessione = $dichiarazione;
        $beneficiario->soggetto = $erede;
        $beneficiario->gradoParentela = GradoParentela::figlio;

        $beniSalvati = [];
        $devoluzioniSalvate = [];
        $this->adapter->impostaSelectCallback(function (string $query) use (&$beniSalvati, &$devoluzioniSalvate, $possessoDefunto, $catasto, $beneficiario): TestResultSet {
            if (str_contains($query, 'FROM `possesso`')) {
                return new TestResultSet([$possessoDefunto]);
            }
            if (str_contains($query, 'FROM `dati_catastali`')) {
                return new TestResultSet([$catasto]);
            }
            if (str_contains($query, 'FROM `beneficiario_successione`')) {
                return new TestResultSet([$beneficiario]);
            }
            if (str_contains($query, 'FROM `bene_successione`')) {
                return new TestResultSet($beniSalvati);
            }
            if (str_contains($query, 'FROM `devoluzione_successione`')) {
                return new TestResultSet($devoluzioniSalvate);
            }
            return new TestResultSet();
        });

        $propostaAttivo = new PropostaAttivo($this->dataMapper);
        $inseriti = $propostaAttivo->importaImmobili($dichiarazione);
        $this->assertSame(1, $inseriti);

        $beneCreato = new BeneSuccessione($this->dataMapper);
        $beneCreato->id = 1;
        $beneCreato->dichiarazioneSuccessione = $dichiarazione;
        $beneCreato->quadro = QuadroBene::fabbricati;
        $beneCreato->immobile = $immobile;
        $beneCreato->codiceDiritto = CodiceDiritto::proprieta;
        $beneCreato->valore = 63000;
        $beniSalvati = [$beneCreato];

        $this->assertTrue($propostaAttivo->proponiDevoluzioni($dichiarazione));

        $devCreata = new DevoluzioneSuccessione($this->dataMapper);
        $devCreata->id = 1;
        $devCreata->beneSuccessione = $beneCreato;
        $devCreata->beneficiarioSuccessione = $beneficiario;
        $devCreata->quotaNumeratore = 1;
        $devCreata->quotaDenominatore = 1;
        $devCreata->codiceDiritto = CodiceDiritto::proprieta;
        $devCreata->valoreQuota = 63000.0;
        $devoluzioniSalvate = [$devCreata];

        $allegato = new AllegatoSuccessione($this->dataMapper);
        $allegato->id = 1;
        $allegato->dichiarazioneSuccessione = $dichiarazione;
        $allegato->tipoAllegato = TipoAllegato::alberoGenealogico;
        $allegato->nomeFile = 'albero.pdf';
        $allegato->tipoMime = 'application/pdf';
        $allegato->dimensione = 100;
        $contenuto = new ContenutoAllegato($this->dataMapper);
        $contenuto->id = 1;
        $contenuto->contenuto = base64_encode('%PDF-1.4');
        $allegato->contenutoAllegato = $contenuto;

        $this->adapter->impostaSelectCallback(function (string $query, array $bindValues) use ($beniSalvati, $devoluzioniSalvate, $possessoDefunto, $catasto, $beneficiario, $allegato): TestResultSet {
            if (str_contains($query, 'FROM `possesso`')) {
                return in_array(10, $bindValues, true) ? new TestResultSet([$possessoDefunto]) : new TestResultSet();
            }
            if (str_contains($query, 'FROM `dati_catastali`')) {
                return new TestResultSet([$catasto]);
            }
            if (str_contains($query, 'FROM `beneficiario_successione`')) {
                return new TestResultSet([$beneficiario]);
            }
            if (str_contains($query, 'FROM `bene_successione`')) {
                return new TestResultSet($beniSalvati);
            }
            if (str_contains($query, 'FROM `devoluzione_successione`')) {
                return new TestResultSet($devoluzioniSalvate);
            }
            if (str_contains($query, 'FROM `allegato_successione`')) {
                return new TestResultSet([$allegato]);
            }
            return new TestResultSet();
        });

        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        $risultato = (new Liquidazione())->liquida($dati);

        // Controllo dichiarazione valida
        $controllo = new ControlloDichiarazione();
        $errori = $controllo->verifica($dati, $risultato);
        $this->assertEmpty($errori);

        // Proposta e applicazione volture
        $volture = new PropostaVolture($this->dataMapper);
        $operazioni = $volture->proponi($dati);
        $this->assertCount(2, $operazioni);
        $this->assertSame('chiusura', $operazioni[0]['tipo']);
        $this->assertSame('nuovo', $operazioni[1]['tipo']);

        // Per applica(), il secondo possesso cerca possessi esistenti dell'erede (nessuno)
        $chiamatePossesso = 0;
        $this->adapter->impostaSelectCallback(function (string $query) use (&$chiamatePossesso, $possessoDefunto): TestResultSet {
            if (str_contains($query, 'FROM `possesso`')) {
                $chiamatePossesso++;
                return $chiamatePossesso === 1 ? new TestResultSet([$possessoDefunto]) : new TestResultSet();
            }
            return new TestResultSet();
        });
        $applicate = $volture->applica($dati);
        $this->assertSame(2, $applicate);
        $this->assertNotNull($dichiarazione->voltureApplicate);

        // Copia dichiarazione (sostitutiva)
        $dichiarazione->stato = StatoDichiarazioneSuccessione::presentata;
        $dichiarazione->dataPresentazione = new SismaDate('2025-05-01');
        $dichiarazione->registrazioneVolume = '88888';
        $dichiarazione->registrazioneNumero = '123';
        $copia = (new CopiaDichiarazione($this->dataMapper))->crea($dati, $risultato);
        $this->assertSame(StatoDichiarazioneSuccessione::bozza, $copia->stato);
        $this->assertSame(2025, $copia->annoPrima);
        $this->assertSame('88888', $copia->volumePrima);
        $this->assertSame('123', $copia->numeroPrima);
    }

    public function testRiepilogoScrivania(): void
    {
        $this->adapter->impostaSelectCallback(fn() => TestResultSet::conteggio(3));
        $riepilogo = new RiepilogoScrivania($this->dataMapper);
        $indicatori = $riepilogo->getIndicatori($this->studioTest);
        $this->assertSame(3, $indicatori['Dichiarazioni in bozza']);
        $this->assertSame(3, $indicatori['Dichiarazioni presentate']);
    }
}
