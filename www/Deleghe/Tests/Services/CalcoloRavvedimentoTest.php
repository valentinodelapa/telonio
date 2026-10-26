<?php

namespace Deleghe\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Tests\SuiteTestCase;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\TassoInteresseLegale;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\CircostanzaRavvedimento;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Services\CalcoloRavvedimento;
use Deleghe\Application\Services\RavvedimentoService;
use SismaFramework\Orm\CustomTypes\SismaDate;

class CalcoloRavvedimentoTest extends SuiteTestCase
{

    /**
     * @return TassoInteresseLegale[]
     */
    private function creaSerieTassiLegali(): array
    {
        $t2023 = new TassoInteresseLegale($this->dataMapper);
        $t2023->id = 1;
        $t2023->validoDal = new SismaDate('2023-01-01');
        $t2023->tasso = 5.0;
        $t2023->provvedimento = 'D.M. 13/12/2022';

        $t2024 = new TassoInteresseLegale($this->dataMapper);
        $t2024->id = 2;
        $t2024->validoDal = new SismaDate('2024-01-01');
        $t2024->tasso = 2.5;
        $t2024->provvedimento = 'D.M. 29/11/2023';

        $t2025 = new TassoInteresseLegale($this->dataMapper);
        $t2025->id = 3;
        $t2025->validoDal = new SismaDate('2025-01-01');
        $t2025->tasso = 2.0;
        $t2025->provvedimento = 'D.M. 10/12/2024';

        return [$t2023, $t2024, $t2025];
    }

    public function testCalcoloRavvedimentoSprintFino15GiorniPreRiformaEPostRiforma(): void
    {
        $calcolo = new CalcoloRavvedimento($this->dataMapper);

        // Pre-riforma (< 01/09/2024): base 30%, 10 giorni -> 30/2/15 * 10 = 10%, riduzione 1/10 -> 1%
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $esitoPre = $calcolo->calcola(
            1000.00,
            new SismaDate('2024-06-17'),
            new SismaDate('2024-06-27'),
            new SismaDate('2025-10-31')
        );
        $this->assertSame(10, $esitoPre['giorni']);
        $this->assertFalse($esitoPre['riforma']);
        $this->assertSame(10.0, $esitoPre['percentuale']);
        $this->assertSame(10, $esitoPre['riduzione']);
        $this->assertSame(10.00, $esitoPre['sanzione']);
        // Interessi: 1000 * 2.5% * 10 / 365 = 0.6849 -> 0.68
        $this->assertSame(0.68, $esitoPre['interessi']);

        // Post-riforma (>= 01/09/2024): base 25%, 6 giorni -> 25/2/15 * 6 = 5%, riduzione 1/10 -> 0.5%
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $esitoPost = $calcolo->calcola(
            1000.00,
            new SismaDate('2025-01-16'),
            new SismaDate('2025-01-22'),
            new SismaDate('2026-11-02')
        );
        $this->assertSame(6, $esitoPost['giorni']);
        $this->assertTrue($esitoPost['riforma']);
        $this->assertSame(5.0, $esitoPost['percentuale']);
        $this->assertSame(10, $esitoPost['riduzione']);
        $this->assertSame(5.00, $esitoPost['sanzione']);
        // Interessi: 1000 * 2.0% * 6 / 365 = 0.3287 -> 0.33
        $this->assertSame(0.33, $esitoPost['interessi']);
    }

    public function testCalcoloRavvedimentoScaglioni30e90GiorniEOltre(): void
    {
        $calcolo = new CalcoloRavvedimento($this->dataMapper);

        // 20 giorni post-riforma: percentuale 12.5%, riduzione 1/10 -> 1.25%
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $esito20 = $calcolo->calcola(1000.0, new SismaDate('2025-01-16'), new SismaDate('2025-02-05'), new SismaDate('2026-11-02'));
        $this->assertSame(20, $esito20['giorni']);
        $this->assertSame(12.5, $esito20['percentuale']);
        $this->assertSame(10, $esito20['riduzione']);
        $this->assertSame(12.50, $esito20['sanzione']);

        // 60 giorni post-riforma: percentuale 12.5%, riduzione 1/9
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $esito60 = $calcolo->calcola(900.0, new SismaDate('2025-01-16'), new SismaDate('2025-03-17'), new SismaDate('2026-11-02'));
        $this->assertSame(60, $esito60['giorni']);
        $this->assertSame(12.5, $esito60['percentuale']);
        $this->assertSame(9, $esito60['riduzione']);
        $this->assertSame(12.50, $esito60['sanzione']);

        // Oltre 90 giorni entro termine dichiarazione: percentuale 25%, riduzione 1/8
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $esitoEntroDich = $calcolo->calcola(800.0, new SismaDate('2024-09-16'), new SismaDate('2025-03-16'), new SismaDate('2025-10-31'));
        $this->assertSame(25.0, $esitoEntroDich['percentuale']);
        $this->assertSame(8, $esitoEntroDich['riduzione']);
        $this->assertSame(25.00, $esitoEntroDich['sanzione']);

        // Oltre termine dichiarazione post-riforma: sempre 1/7
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $esitoOltreDichPost = $calcolo->calcola(700.0, new SismaDate('2024-09-16'), new SismaDate('2027-01-16'), new SismaDate('2025-10-31'));
        $this->assertSame(7, $esitoOltreDichPost['riduzione']);
        $this->assertSame(25.00, $esitoOltreDichPost['sanzione']);

        // Pre-riforma oltre il termine della dichiarazione successiva: 1/6
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $esitoUltrabiennalePre = $calcolo->calcola(600.0, new SismaDate('2023-06-16'), new SismaDate('2025-11-15'), new SismaDate('2024-10-31'), new SismaDate('2025-10-31'));
        $this->assertSame(30.0, $esitoUltrabiennalePre['percentuale']);
        $this->assertSame(6, $esitoUltrabiennalePre['riduzione']);
        $this->assertSame(30.00, $esitoUltrabiennalePre['sanzione']);

        // Con circostanza schema d'atto post-riforma: riduzione fissa 1/6 anche entro 30 giorni
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $esitoSchemaAtto = $calcolo->calcola(600.0, new SismaDate('2025-01-16'), new SismaDate('2025-01-31'), new SismaDate('2026-11-02'), null, CircostanzaRavvedimento::schemaAtto);
        $this->assertSame(6, $esitoSchemaAtto['riduzione']);
    }

    public function testCalcoloInteressiMultiAnnoProRataTemporis(): void
    {
        $calcolo = new CalcoloRavvedimento($this->dataMapper);
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());

        // Scadenza 16/12/2023, versamento 15/01/2025:
        // Periodo 1 (2023): dal 17/12/2023 al 31/12/2023 = 15 gg al 5,0%
        // Periodo 2 (2024): dal 01/01/2024 al 31/12/2024 = 366 gg (bisestile) al 2,5%
        // Periodo 3 (2025): dal 01/01/2025 al 15/01/2025 = 15 gg al 2,0%
        [$interessi, $periodi] = $calcolo->calcolaInteressi(1000.0, new SismaDate('2023-12-16'), new SismaDate('2025-01-15'));

        $this->assertCount(3, $periodi);
        $this->assertSame(15, $periodi[0]['giorni']);
        $this->assertSame(5.0, $periodi[0]['tasso']);
        $this->assertSame(366, $periodi[1]['giorni']);
        $this->assertSame(2.5, $periodi[1]['tasso']);
        $this->assertSame(15, $periodi[2]['giorni']);
        $this->assertSame(2.0, $periodi[2]['tasso']);

        $atteso = round(1000.0 * (5.0 * 15 + 2.5 * 366 + 2.0 * 15) / 100 / 365, 2);
        $this->assertSame($atteso, $interessi);
    }

    public function testCalcoloRavvedimentoEccezioni(): void
    {
        $calcolo = new CalcoloRavvedimento($this->dataMapper);

        try {
            $calcolo->calcola(100.0, new SismaDate('2025-05-16'), new SismaDate('2025-05-16'), new SismaDate('2026-10-31'));
            $this->fail('Attesa InvalidArgumentException per ritardo nullo');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('ritardo', $e->getMessage());
        }

        // Tassi mancanti
        $this->testAdapter->accodaSelect([]);
        $this->expectException(\UnexpectedValueException::class);
        $calcolo->calcolaInteressi(100.0, new SismaDate('2025-01-16'), new SismaDate('2025-02-16'));
    }

    public function testRavvedimentoServiceRegistraConRighiDistintiEConSommaImu(): void
    {
        $studio = $this->creaStudio();
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 10;
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->nominativo = 'Rossi Mario';

        $delega = new Delega($this->dataMapper);
        $delega->id = 50;
        $delega->studio = $studio;
        $delega->soggetto = $soggetto;
        $delega->modello = ModelloF24::ordinario;
        $delega->stato = StatoDelega::bozza;
        $delega->scadenza = new SismaDate('2025-02-05');

        $tributo = new CodiceTributo($this->dataMapper);
        $tributo->id = 1;
        $tributo->sezione = SezioneCodiceTributo::erario;
        $tributo->codice = '4001';
        $tributo->descrizione = 'IRPEF Saldo';
        $tributo->modalita = ModalitaUtilizzo::debito;
        $tributo->formatoRateazione = '0000';
        $tributo->annoRichiesto = true;

        $codSanzione = new CodiceTributo($this->dataMapper);
        $codSanzione->id = 2;
        $codSanzione->sezione = SezioneCodiceTributo::erario;
        $codSanzione->codice = '8901';
        $codSanzione->descrizione = 'Sanzione IRPEF';
        $codSanzione->modalita = ModalitaUtilizzo::debito;
        $codSanzione->formatoRateazione = '0000';
        $codSanzione->annoRichiesto = true;

        $codInteressi = new CodiceTributo($this->dataMapper);
        $codInteressi->id = 3;
        $codInteressi->sezione = SezioneCodiceTributo::erario;
        $codInteressi->codice = '1989';
        $codInteressi->descrizione = 'Interessi IRPEF';
        $codInteressi->modalita = ModalitaUtilizzo::debito;
        $codInteressi->formatoRateazione = '0000';
        $codInteressi->annoRichiesto = true;

        $ravvedimento = new Ravvedimento($this->dataMapper);
        $ravvedimento->delega = $delega;
        $ravvedimento->sezione = SezioneF24::erario;
        $ravvedimento->codiceTributo = $tributo;
        $ravvedimento->codiceSanzione = $codSanzione;
        $ravvedimento->codiceInteressi = $codInteressi;
        $ravvedimento->annoRiferimento = 2024;
        $ravvedimento->importoTributo = 1000.00;
        $ravvedimento->scadenzaOriginale = new SismaDate('2025-01-16');
        $ravvedimento->termineDichiarazione = new SismaDate('2026-11-02');

        // 1) TassoInteresseLegaleModel::getSerie
        // 2) VersamentoModel::getGenerati (precedenti)
        // 3..5) VersamentoModel::getProssimoOrdine x3
        // 6) GenerazioneDeleghe::aggiornaDetrazioneImu -> VersamentoModel::getByDelega
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([]);

        $service = new RavvedimentoService($this->dataMapper);
        $service->registra($ravvedimento);

        $this->assertSame(20, $ravvedimento->giorniRitardo);
        $this->assertSame(12.50, $ravvedimento->sanzione);
        $this->assertGreaterThan(0, $ravvedimento->interessi);

        // Ora testiamo IMU (senza codiceSanzione e senza codiceInteressi -> tutto sommato nel rigo tributo con flag ravvedimento = true)
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->codiceCatastale = 'H501';

        $tributoImu = new CodiceTributo($this->dataMapper);
        $tributoImu->id = 10;
        $tributoImu->sezione = SezioneCodiceTributo::tributiLocali;
        $tributoImu->codice = '3918';
        $tributoImu->descrizione = 'IMU altri fabbricati';
        $tributoImu->tipoTributo = 'IMU';
        $tributoImu->tabelleEnte = 'T4';
        $tributoImu->formatoRateazione = '0000';
        $tributoImu->annoRichiesto = true;

        $ravvImu = new Ravvedimento($this->dataMapper);
        $ravvImu->delega = $delega;
        $ravvImu->sezione = SezioneF24::tributiLocali;
        $ravvImu->codiceTributo = $tributoImu;
        $ravvImu->codiceEnte = 'H501';
        $ravvImu->acconto = true;
        $ravvImu->numeroImmobili = 2;
        $ravvImu->annoRiferimento = 2024;
        $ravvImu->importoTributo = 500.00;
        $ravvImu->scadenzaOriginale = new SismaDate('2025-01-16');
        $ravvImu->termineDichiarazione = new SismaDate('2026-06-30');

        // 1) Tassi legali
        // 2) ControlloDelega::verificaTributiLocali -> ComuneModel::getByCodice
        // 3) VersamentoModel::getGenerati
        // 4) VersamentoModel::getProssimoOrdine
        // 5) GenerazioneDeleghe::aggiornaDetrazioneImu
        $this->testAdapter->accodaSelect($this->creaSerieTassiLegali());
        $this->testAdapter->accodaSelect([$comune]);
        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([]);
        $this->testAdapter->accodaSelect([]);

        $service->registra($ravvImu);
        $this->assertSame(6.25, $ravvImu->sanzione);

        // Test elimina
        $precedente = new Versamento($this->dataMapper);
        $precedente->id = 99;
        $precedente->scadenza = $delega->scadenza;
        $precedente->delega = $delega;
        $this->testAdapter->accodaSelect([$precedente]);
        $this->testAdapter->accodaSelect([]); // getByVersamentoOmesso
        $this->testAdapter->accodaSelect([]); // aggiornaDetrazioneImu
        $service->elimina($ravvImu);
        $this->assertNotEmpty($this->testAdapter->queryEseguite);
    }
}
