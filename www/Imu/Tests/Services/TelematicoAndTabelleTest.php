<?php

namespace Imu\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Entities\Recapito;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoRecapito;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Deleghe\Application\Entities\Intermediario;
use Imu\Application\Entities\DichiarazioneImu;
use Imu\Application\Entities\RigoDichiarazioneImu;
use Imu\Application\Entities\UtilizzoMistoEnc;
use Imu\Application\Enumerations\CaratteristicaDichiarata;
use Imu\Application\Enumerations\CodiceCarica;
use Imu\Application\Enumerations\ModelloDichiarazione;
use Imu\Application\Enumerations\TipologiaDichiarazione;
use Imu\Application\Services\TabelleRiferimento\CircolareTerreniMontani;
use Imu\Application\Services\TabelleRiferimento\ImportazioneProspetti;
use Imu\Application\Services\TabelleRiferimento\LettoreProspetto;
use Imu\Application\Services\TabelleRiferimento\ProspettiMef;
use Imu\Application\Services\TabelleRiferimento\TabelleImu;
use Imu\Application\Services\Telematico\GeneratoreDichiarazioneImu;
use Imu\Application\Services\Telematico\TracciatoDichiarazioneImu;
use SismaFramework\Orm\CustomTypes\SismaDate;

class TelematicoAndTabelleTest extends SuiteTestCase
{

    private function creaComune(string $codice = 'H501', string $denominazione = 'ROMA', string $sigla = 'RM'): Comune
    {
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->codiceCatastale = $codice;
        $comune->denominazione = $denominazione;
        $comune->siglaProvincia = $sigla;
        $comune->validoDal = new SismaDate('1900-01-01');
        return $comune;
    }

    private function creaItalia(): Nazione
    {
        $nazione = new Nazione($this->dataMapper);
        $nazione->id = 1;
        $nazione->codiceIso = 'IT';
        $nazione->codiceIso3 = 'ITA';
        $nazione->denominazione = 'ITALIA';
        return $nazione;
    }

    private function creaPersonaFisica(Comune $comune): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 1;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->cognome = 'ROSSI';
        $soggetto->nome = 'MARIO';
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->sesso = Sesso::maschio;
        $soggetto->dataNascita = new SismaDate('1980-01-01');
        $soggetto->comuneNascita = $comune;
        return $soggetto;
    }

    public function testTracciatoSpaziamentoEGeneratoreOrdinarioEEnc(): void
    {
        $spostati = TracciatoDichiarazioneImu::sposta(['numeroOrdine' => [90, 4, 'NU']], 499, 'secondo');
        $this->assertSame(['secondoNumeroOrdine' => [589, 4, 'NU']], $spostati);

        $comune = $this->creaComune();
        $italia = $this->creaItalia();
        $soggetto = $this->creaPersonaFisica($comune);

        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->id = 1;
        $indirizzo->soggetto = $soggetto;
        $indirizzo->tipoIndirizzo = TipoIndirizzo::domicilioFiscale;
        $indirizzo->indirizzo = 'VIA NAZIONALE';
        $indirizzo->numeroCivico = '15';
        $indirizzo->cap = '00184';
        $indirizzo->comune = $comune;
        $indirizzo->nazione = $italia;
        $indirizzo->validoDal = new SismaDate('2020-01-01');

        $recapito = new Recapito($this->dataMapper);
        $recapito->id = 1;
        $recapito->soggetto = $soggetto;
        $recapito->tipoRecapito = TipoRecapito::email;
        $recapito->valore = 'mario.rossi@example.com';
        $recapito->principale = true;

        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 10;
        $immobile->studio = $this->studioPredefinito;
        $immobile->comune = $comune;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->foglio = '12';
        $immobile->particella = '345';
        $immobile->subalterno = '6';
        $immobile->indirizzo = 'VIA APPIA';
        $immobile->numeroCivico = '100';

        $dc = new DatiCatastali($this->dataMapper);
        $dc->causale = CausaleClassamento::costituzione;
        $dc->id = 1;
        $dc->immobile = $immobile;
        $dc->categoria = CategoriaCatastale::a2;
        $dc->classe = '3';
        $dc->rendita = 800.0;
        $dc->validoDal = new SismaDate('2020-01-01');

        $dichiarazione = new DichiarazioneImu($this->dataMapper);
        $dichiarazione->id = 5;
        $dichiarazione->studio = $this->studioPredefinito;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->anno = 2024;
        $dichiarazione->codiceComune = 'H501';
        $dichiarazione->denominazioneComune = 'ROMA';
        $dichiarazione->modello = ModelloDichiarazione::ordinario;
        $dichiarazione->tipologia = TipologiaDichiarazione::nuova;
        $dichiarazione->dataDichiarazione = new SismaDate('2025-05-10');

        $rigo = new RigoDichiarazioneImu($this->dataMapper);
        $rigo->id = 1;
        $rigo->dichiarazioneImu = $dichiarazione;
        $rigo->immobile = $immobile;
        $rigo->quadro = 'A';
        $rigo->numeroOrdine = 1;
        $rigo->progressivoImmobile = 1;
        $rigo->caratteristica = CaratteristicaDichiarata::fabbricatoRendita;
        $rigo->valore = 134400.0;
        $rigo->percentualePossesso = 100.0;
        $rigo->riduzione = '3';
        $rigo->esenzione = '0';
        $rigo->inizioTermine = 'I';
        $rigo->dataVariazione = new SismaDate('2024-03-01');

        $this->assertSame('IMU_2024_H501_RSSMRA80A01H501U.txt', GeneratoreDichiarazioneImu::getNomeFile($dichiarazione));

        $this->testAdapter->impostaSelectCallback(function (string $query) use ($rigo, $indirizzo, $recapito, $dc): TestResultSet {
            if (str_contains($query, 'rigo_dichiarazione_imu')) {
                return new TestResultSet([$rigo]);
            }
            if (str_contains($query, 'indirizzo')) {
                return new TestResultSet([$indirizzo]);
            }
            if (str_contains($query, 'recapito')) {
                return new TestResultSet([$recapito]);
            }
            if (str_contains($query, 'dati_catastali')) {
                return new TestResultSet([$dc]);
            }
            return new TestResultSet([]);
        });

        $generatore = new GeneratoreDichiarazioneImu($this->dataMapper);
        $this->assertSame([], $generatore->getErrori($dichiarazione));
        $file = $generatore->genera($dichiarazione);
        // 4 record da 1900 caratteri (A, B, D, Z)
        $this->assertSame(1900 * 4, strlen($file));
        $this->assertStringContainsString('TAT00', $file);
        $this->assertStringContainsString('RSSMRA80A01H501U', $file);

        // Modello ENC con quadro B
        $ente = new Soggetto($this->dataMapper);
        $ente->id = 2;
        $ente->studio = $this->studioPredefinito;
        $ente->tipoSoggetto = TipoSoggetto::ente;
        $ente->nominativo = 'FONDAZIONE ALFA';
        $ente->codiceFiscale = '90012340581';

        $dichEnc = new DichiarazioneImu($this->dataMapper);
        $dichEnc->id = 6;
        $dichEnc->studio = $this->studioPredefinito;
        $dichEnc->soggetto = $ente;
        $dichEnc->anno = 2024;
        $dichEnc->codiceComune = 'H501';
        $dichEnc->denominazioneComune = 'ROMA';
        $dichEnc->modello = ModelloDichiarazione::entiNonCommerciali;
        $dichEnc->dichiarante = $soggetto;
        $dichEnc->codiceCarica = CodiceCarica::rappresentanteLegale;
        $dichEnc->dataDichiarazione = new SismaDate('2025-05-10');
        $dichEnc->impostaDovuta = 1200.0;
        $dichEnc->rateVersate = 1000.0;

        $rigoB = clone $rigo;
        $rigoB->dichiarazioneImu = $dichEnc;
        $rigoB->quadro = 'B';

        $utilizzo = new UtilizzoMistoEnc($this->dataMapper);
        $utilizzo->id = 1;
        $utilizzo->soggetto = $ente;
        $utilizzo->immobile = $immobile;
        $utilizzo->anno = 2024;
        $utilizzo->attivita = 'DI';
        $utilizzo->esente = false;
        $utilizzo->didatticaSuperficie = 25.0;
        $utilizzo->didatticaCorrispettivoMedio = 2000.0;
        $utilizzo->didatticaCostoMedioStudente = 1600.0;

        $this->testAdapter->impostaSelectCallback(function (string $query) use ($rigoB, $indirizzo, $recapito, $dc, $utilizzo): TestResultSet {
            if (str_contains($query, 'rigo_dichiarazione_imu')) {
                return new TestResultSet([$rigoB]);
            }
            if (str_contains($query, 'indirizzo')) {
                return new TestResultSet([$indirizzo]);
            }
            if (str_contains($query, 'recapito')) {
                return new TestResultSet([$recapito]);
            }
            if (str_contains($query, 'dati_catastali')) {
                return new TestResultSet([$dc]);
            }
            if (str_contains($query, 'utilizzo_misto_enc')) {
                return new TestResultSet([$utilizzo]);
            }
            return new TestResultSet([]);
        });

        $this->assertSame('IMUENC_2024_H501_90012340581.txt', GeneratoreDichiarazioneImu::getNomeFile($dichEnc));
        $fileEnc = $generatore->genera($dichEnc);
        // Record A, B_ENC, D_ENC (quadro B), E_ENC, Z_ENC = 5 record da 1900 caratteri
        $this->assertSame(1900 * 5, strlen($fileEnc));
        $this->assertStringContainsString('TAS00', $fileEnc);
    }

    public function testLettoreProspettoProspettiMefImportazioneProspettiETabelleImu(): void
    {
        $testoProspetto = <<<TXT
Prospetto aliquote IMU - Comune di ROMA
ID Prospetto 9876 riferito all'anno 2025
Approvato con delibera n. 42 del 15/12/2024
Comune oggetto di fusione: NO
Abitazione principale di categoria catastale A/1, A/8 e A/9 e relative pertinenze    0,6%
Assimilazione all'abitazione principale dell'unità immobiliare posseduta da anziani o disabili    SI
Fabbricati rurali ad uso strumentale (inclusa la categoria catastale D/10)    0,1%
Fabbricati appartenenti al gruppo catastale D (esclusa la categoria catastale D/10)    1,06%
Terreni agricoli    0,76%
Aree fabbricabili    1,06%
Altri fabbricati (fabbricati diversi dall'abitazione principale e dai fabbricati appartenenti al gruppo catastale D)    1,06%
Altri fabbricati (fabbricati diversi dall'abitazione         Abitazione locata a canone concordato            0,56%
principale e dai fabbricati appartenenti al gruppo           Categoria catastale: - A/2 - A/3
catastale D)
Elenco esenzioni e/o agevolazioni indicate dal comune:
- Immobili concessi in comodato al comune
Precisazioni
Documento generato il 20/12/2024
TXT;

        $lettore = new LettoreProspetto();
        $letto = $lettore->leggi($testoProspetto);
        $this->assertSame('ROMA', $letto['comune']);
        $this->assertSame(9876, $letto['idProspetto']);
        $this->assertSame(2025, $letto['anno']);
        $this->assertSame('42', $letto['numeroDelibera']);
        $this->assertSame('2024-12-15', $letto['dataDelibera']);
        $this->assertTrue($letto['assimilazioneAnziani']);
        $this->assertSame(0.6, $letto['aliquote']['AP']);
        $this->assertSame(1.06, $letto['aliquote']['AL']);
        $this->assertCount(1, $letto['differenziate']);
        $this->assertSame('AL', $letto['differenziate'][0]['fattispecie']);
        $this->assertSame(0.56, $letto['differenziate'][0]['aliquota']);
        $this->assertStringContainsString('comodato al comune', (string) $letto['esenzioni']);

        // ProspettiMef con cartella di copia temporanea
        $cartellaTemp = sys_get_temp_dir() . '/imu_test_' . uniqid();
        mkdir($cartellaTemp, 0775, true);
        $csvContent = "CODICE_CATASTALE;COMUNE;NUMERO;DATA_DELIBERA;DATA_PUBBLICAZIONE;PR;REGIONE;FILE_DELIBERA;NOTE\n"
                . "H501;ROMA;42;15/12/2024;20/12/2024;RM;LAZIO;prospetto_h501.pdf; Prima pubblicazione \n";
        file_put_contents($cartellaTemp . '/elenco-2025.csv', $csvContent);

        $mef = new ProspettiMef($cartellaTemp);
        $elenco = $mef->getElenco(2025);
        $this->assertArrayHasKey('H501', $elenco);
        $this->assertSame('42', $elenco['H501'][0]['numeroDelibera']);
        $this->assertSame('2024-12-20', $elenco['H501'][0]['dataPubblicazione']);

        // ImportazioneProspetti con ProspettiMef stubbato
        $fonteStub = new class($elenco, $testoProspetto) extends ProspettiMef {
            public function __construct(private array $elencoFisso, private string $testoFisso)
            {
                parent::__construct(null);
            }

            #[\Override]
            public function getElenco(int $anno): array
            {
                return $this->elencoFisso;
            }

            #[\Override]
            public function getTesto(string $codiceCatastale, int $anno, string $file): array
            {
                return [$this->testoFisso, 'https://example.com/' . $file];
            }
        };

        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        $importazione = new ImportazioneProspetti($this->dataMapper, $fonteStub, $lettore);
        $messaggi = [];
        $esitoImp = $importazione->importa(2025, ['H501', 'F205'], function (string $m) use (&$messaggi): void {
            $messaggi[] = $m;
        });
        $this->assertSame(1, $esitoImp['importati']);
        $this->assertSame(['F205'], $esitoImp['non_pubblicati']);
        $this->assertSame([], $esitoImp['errori']);

        // CircolareTerreniMontani da copia locale (5000 voci in 80 province)
        $righeCircolare = [];
        for ($p = 1; $p <= 80; $p++) {
            $righeCircolare[] = 'PROVINCIA DI PROV' . $p;
            for ($c = 1; $c <= 63; $c++) {
                $righeCircolare[] = 'COMUNE ' . $p . ' ' . $c . ($c === 1 ? ' [PD]' : '');
            }
        }
        file_put_contents($cartellaTemp . '/circolare-9-1993.txt', implode("\n", $righeCircolare));
        $vociCircolare = (new CircolareTerreniMontani($cartellaTemp))->leggi();
        $this->assertCount(80 * 63, $vociCircolare);
        $this->assertTrue($vociCircolare[0]['parziale']);

        // TabelleImu::leggiCoefficienti()
        $tabelle = new TabelleImu($this->dataMapper);
        $coefficienti = $tabelle->leggiCoefficienti();
        $this->assertNotEmpty($coefficienti);
        $this->assertArrayHasKey('anno_imposta', $coefficienti[0]);

        @unlink($cartellaTemp . '/elenco-2025.csv');
        @unlink($cartellaTemp . '/circolare-9-1993.txt');
        @rmdir($cartellaTemp);
    }
}
