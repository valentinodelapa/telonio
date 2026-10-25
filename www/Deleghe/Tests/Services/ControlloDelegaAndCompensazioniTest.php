<?php

namespace Deleghe\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\Sesso;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Entities\CodiceTabellaF24;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\TassoInteresseLegale;
use Deleghe\Application\Entities\TassoRiferimento;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\CircostanzaRavvedimento;
use Deleghe\Application\Enumerations\CodiceIdentificativo;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\ObbligoCampo;
use Deleghe\Application\Enumerations\Periodicita;
use Deleghe\Application\Enumerations\RegimeSanzioneCivile;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use Deleghe\Application\Services\ArchivioVersamenti;
use Deleghe\Application\Services\Compensazioni;
use Deleghe\Application\Services\ControlloDelega;
use Deleghe\Application\Services\CopiaVersamenti;
use Deleghe\Application\Services\DatiContribuente;
use Deleghe\Application\Services\EsitoControllo;
use Deleghe\Application\Services\EsitoRegistrazione;
use Deleghe\Application\Services\GenerazioneDeleghe;
use Deleghe\Application\Services\ModelloCompilato;
use Deleghe\Application\Services\RavvedimentoService;
use Deleghe\Application\Services\RiepilogoScrivania;
use Deleghe\Application\Services\RipartizioneModelli;
use Deleghe\Application\Services\SanzioniCiviliService;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ControlloDelegaAndCompensazioniTest extends SuiteTestCase
{

    private function creaSoggettoTest(int $id = 1, string $cf = 'RSSMRA80A01H501U'): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = $id;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = $cf;
        $soggetto->cognome = 'Rossi';
        $soggetto->nome = 'Mario';
        $soggetto->nominativo = 'Rossi Mario';
        $soggetto->sesso = Sesso::from('M');
        $soggetto->dataNascita = new SismaDate('1980-01-01');
        $comune = new Comune($this->dataMapper);
        $comune->id = 10;
        $comune->codiceCatastale = 'H501';
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $soggetto->comuneNascita = $comune;
        return $soggetto;
    }

    private function creaCodiceTributo(
            string $codice = '4001',
            SezioneCodiceTributo $sezione = SezioneCodiceTributo::erario,
            ModalitaUtilizzo $modalita = ModalitaUtilizzo::entrambi,
            string $formatoRateazione = 'NNRR',
            bool $annoRichiesto = true,
    ): CodiceTributo {
        $ct = new CodiceTributo($this->dataMapper);
        $ct->id = 100;
        $ct->sezione = $sezione;
        $ct->codice = $codice;
        $ct->descrizione = 'Tributo ' . $codice;
        $ct->modalita = $modalita;
        $ct->formatoRateazione = $formatoRateazione;
        $ct->annoRichiesto = $annoRichiesto;
        $ct->annoMassimo = 1;
        $ct->codiceUfficio = ObbligoCampo::nonPrevisto;
        $ct->codiceAtto = ObbligoCampo::nonPrevisto;
        $ct->identificativoOperazione = ObbligoCampo::nonPrevisto;
        $ct->provinciaRichiesta = false;
        $ct->meseRichiesto = false;
        $ct->compensabileCreditiPa = true;
        $ct->inUso = true;
        return $ct;
    }

    private function creaDelegaTest(?Soggetto $soggetto = null, StatoDelega $stato = StatoDelega::bozza): Delega
    {
        $delega = new Delega($this->dataMapper);
        $delega->id = 1;
        $delega->studio = $this->studioPredefinito;
        $delega->soggetto = $soggetto ?? $this->creaSoggettoTest();
        $delega->modello = ModelloF24::ordinario;
        $delega->scadenza = new SismaDate('2026-06-16');
        $delega->stato = $stato;
        return $delega;
    }

    public function testEsitoControlloAndModelloCompilato(): void
    {
        $esito = new EsitoControllo();
        $this->assertTrue($esito->isConfermabile());
        $this->assertSame(0, $esito->getSaldo());
        $this->assertSame('1.234,56', EsitoControllo::euro(123456));
        $this->assertSame(12345, EsitoControllo::centesimi(123.45));
        $esito->debito = 50000;
        $esito->credito = 20000;
        $this->assertSame(30000, $esito->getSaldo());
        $esito->errori[] = 'Errore';
        $this->assertFalse($esito->isConfermabile());

        $esitoReg = new EsitoRegistrazione();
        $this->assertSame([], $esitoReg->differenze);
        $esitoReg->differenze[] = 'Diff';
        $this->assertCount(1, $esitoReg->differenze);

        $modello = new ModelloCompilato();
        $this->assertSame(0, $modello->contaRighe());
        $this->assertNull($modello->getCodiceAltroEnte());

        $rigoErario = new Versamento($this->dataMapper);
        $rigoErario->sezione = SezioneF24::erario;
        $modello->aggiungi($rigoErario, 15000, 0);

        $rigoPre = new Versamento($this->dataMapper);
        $rigoPre->sezione = SezioneF24::altriEnti;
        $rigoPre->codiceEnte = '0001';
        $modello->aggiungi($rigoPre, 0, 5000);

        $this->assertSame(2, $modello->contaRighe());
        $this->assertSame(1, $modello->contaRighe(SezioneF24::erario));
        $this->assertSame(['debito' => 15000, 'credito' => 0], $modello->getTotaliSezione(SezioneF24::erario));
        $this->assertSame(15000, $modello->getDebito());
        $this->assertSame(5000, $modello->getCredito());
        $this->assertSame(10000, $modello->getSaldo());
        $this->assertSame('0001', $modello->getCodiceAltroEnte());
    }

    public function testRipartizioneModelli(): void
    {
        $ctErario = $this->creaCodiceTributo('4001', SezioneCodiceTributo::erario);
        $ctImu = $this->creaCodiceTributo('3918', SezioneCodiceTributo::tributiLocali);
        $ctImu->tipoTributo = 'IMU';
        $ctPpaa = $this->creaCodiceTributo('PPAA', SezioneCodiceTributo::erario, ModalitaUtilizzo::credito);

        $righe = [];
        for ($i = 1; $i <= 7; $i++) {
            $r = new Versamento($this->dataMapper);
            $r->sezione = SezioneF24::erario;
            $r->codiceTributo = $ctErario;
            $r->importoDebito = 100.0;
            $r->importoCredito = 0.0;
            $righe[] = $r;
        }
        $rImuDeb = new Versamento($this->dataMapper);
        $rImuDeb->sezione = SezioneF24::tributiLocali;
        $rImuDeb->codiceTributo = $ctImu;
        $rImuDeb->codiceEnte = 'H501';
        $rImuDeb->importoDebito = 300.0;
        $rImuDeb->importoCredito = 0.0;
        $righe[] = $rImuDeb;

        $rImuCred = new Versamento($this->dataMapper);
        $rImuCred->sezione = SezioneF24::tributiLocali;
        $rImuCred->codiceTributo = $ctImu;
        $rImuCred->codiceEnte = 'H501';
        $rImuCred->importoDebito = 0.0;
        $rImuCred->importoCredito = 100.0;
        $righe[] = $rImuCred;

        $rPpaa = new Versamento($this->dataMapper);
        $rPpaa->sezione = SezioneF24::erario;
        $rPpaa->codiceTributo = $ctPpaa;
        $rPpaa->importoDebito = 0.0;
        $rPpaa->importoCredito = 200.0;
        $righe[] = $rPpaa;

        $ripartizione = new RipartizioneModelli(fn(Versamento $v) => $v->codiceTributo?->codice === '4001');
        $modelli = $ripartizione->ripartisci(ModelloF24::ordinario, $righe);
        $this->assertCount(2, $modelli);
        $this->assertSame([], $ripartizione->getErrori());
        $this->assertTrue($modelli[0]->conDetrazioneImu);

        // Semplificato & Altri enti con ente diverso
        $rPre1 = new Versamento($this->dataMapper);
        $rPre1->sezione = SezioneF24::altriEnti;
        $rPre1->codiceEnte = '0001';
        $rPre1->importoDebito = 50.0;
        $rPre2 = new Versamento($this->dataMapper);
        $rPre2->sezione = SezioneF24::altriEnti;
        $rPre2->codiceEnte = '0002';
        $rPre2->importoDebito = 60.0;
        $modelliPre = $ripartizione->ripartisci(ModelloF24::ordinario, [$rPre1, $rPre2]);
        $this->assertCount(2, $modelliPre);

        // Credito non collocabile per mancanza di capienza IMU comune
        $rImuCredEccessivo = new Versamento($this->dataMapper);
        $rImuCredEccessivo->sezione = SezioneF24::tributiLocali;
        $rImuCredEccessivo->codiceTributo = $ctImu;
        $rImuCredEccessivo->codiceEnte = 'F205';
        $rImuCredEccessivo->importoDebito = 0.0;
        $rImuCredEccessivo->importoCredito = 50.0;
        $ripartizione->ripartisci(ModelloF24::semplificato, [$rImuDeb, $rImuCredEccessivo]);
        $this->assertNotEmpty($ripartizione->getErrori());
    }

    public function testControlloDelegaRigheSezioniETestata(): void
    {
        $controllo = new ControlloDelega($this->dataMapper);
        $data = new SismaDate('2026-06-16');

        // Erario valido e con errori (importi, anno, rateazione, PPAA)
        $ctErario = $this->creaCodiceTributo('4001', SezioneCodiceTributo::erario, ModalitaUtilizzo::entrambi, 'NNRR', true);
        $rigoErario = new Versamento($this->dataMapper);
        $rigoErario->sezione = SezioneF24::erario;
        $rigoErario->scadenza = $data;
        $rigoErario->codiceTributo = $ctErario;
        $rigoErario->rateazione = '0101';
        $rigoErario->annoRiferimento = 2025;
        $rigoErario->importoDebito = 100.0;
        $rigoErario->importoCredito = 0.0;
        $this->assertSame([], $controllo->verificaRiga($rigoErario));

        // Errore entrambi importi o negativi
        $rigoErr = clone $rigoErario;
        $rigoErr->importoCredito = 50.0;
        $this->assertArrayHasKey('importoCredito', $controllo->verificaRiga($rigoErr));
        $rigoZero = clone $rigoErario;
        $rigoZero->importoDebito = 0.0;
        $this->assertArrayHasKey('importoDebito', $controllo->verificaRiga($rigoZero));

        // PPAA richiede numero certificazione
        $ctPpaa = $this->creaCodiceTributo('PPAA', SezioneCodiceTributo::erario, ModalitaUtilizzo::credito, '0000', true);
        $rigoPpaa = new Versamento($this->dataMapper);
        $rigoPpaa->sezione = SezioneF24::erario;
        $rigoPpaa->scadenza = $data;
        $rigoPpaa->codiceTributo = $ctPpaa;
        $rigoPpaa->annoRiferimento = 2025;
        $rigoPpaa->importoDebito = 0.0;
        $rigoPpaa->importoCredito = 100.0;
        $this->assertArrayHasKey('numeroCertificazione', $controllo->verificaRiga($rigoPpaa));
        $rigoPpaa->numeroCertificazione = 'CERT123456';
        $this->assertSame([], $controllo->verificaRiga($rigoPpaa));

        // Regioni
        $voceRegione = new CodiceTabellaF24($this->dataMapper);
        $voceRegione->id = 1;
        $voceRegione->tabella = TabellaF24::regioni;
        $voceRegione->codice = '08';
        $voceRegione->descrizione = 'Lazio';
        $voceRegione->inUso = true;
        $this->testAdapter->accodaSelect(new TestResultSet([$voceRegione]));

        $ctReg = $this->creaCodiceTributo('3801', SezioneCodiceTributo::regioni, ModalitaUtilizzo::entrambi, '00MM', true);
        $rigoReg = new Versamento($this->dataMapper);
        $rigoReg->sezione = SezioneF24::regioni;
        $rigoReg->scadenza = $data;
        $rigoReg->codiceTributo = $ctReg;
        $rigoReg->codiceEnte = '08';
        $rigoReg->rateazione = '0006';
        $rigoReg->annoRiferimento = 2025;
        $rigoReg->importoDebito = 120.0;
        $this->assertSame([], $controllo->verificaRiga($rigoReg));

        // Tributi locali (IMU)
        $comune = new Comune($this->dataMapper);
        $comune->id = 10;
        $comune->codiceCatastale = 'H501';
        $this->testAdapter->accodaSelect(new TestResultSet([$comune]));
        $ctImu = $this->creaCodiceTributo('3918', SezioneCodiceTributo::tributiLocali, ModalitaUtilizzo::entrambi, '0000', true);
        $ctImu->tipoTributo = 'IMU';
        $ctImu->tabelleEnte = 'T4';
        $rigoImu = new Versamento($this->dataMapper);
        $rigoImu->sezione = SezioneF24::tributiLocali;
        $rigoImu->scadenza = $data;
        $rigoImu->codiceTributo = $ctImu;
        $rigoImu->codiceEnte = 'H501';
        $rigoImu->annoRiferimento = 2026;
        $rigoImu->numeroImmobili = 2;
        $rigoImu->acconto = true;
        $rigoImu->importoDebito = 400.0;
        $this->assertSame([], $controllo->verificaRiga($rigoImu));

        // INPS
        $sedeInps = new CodiceTabellaF24($this->dataMapper);
        $sedeInps->id = 2;
        $sedeInps->tabella = TabellaF24::sediInps;
        $sedeInps->codice = '7000';
        $sedeInps->descrizione = 'Roma';
        $sedeInps->inUso = true;

        $causaleInps = new CausaleContributo($this->dataMapper);
        $causaleInps->id = 1;
        $causaleInps->ente = CausaleContributo::ENTE_INPS;
        $causaleInps->causale = 'DM10';
        $causaleInps->modalita = ModalitaUtilizzo::entrambi;
        $causaleInps->periodoDa = ObbligoCampo::obbligatorio;
        $causaleInps->periodoA = ObbligoCampo::facoltativo;
        $causaleInps->inUso = true;
        $this->testAdapter->accodaSelect(new TestResultSet([$sedeInps]), new TestResultSet([$causaleInps]));

        $rigoInps = new Versamento($this->dataMapper);
        $rigoInps->sezione = SezioneF24::inps;
        $rigoInps->scadenza = $data;
        $rigoInps->codiceSede = '7000';
        $rigoInps->causale = 'DM10';
        $rigoInps->posizione = '1234567890';
        $rigoInps->periodoDa = '05/2026';
        $rigoInps->importoDebito = 250.0;
        $this->assertSame([], $controllo->verificaRiga($rigoInps));

        // INAIL
        $sedeInail = new CodiceTabellaF24($this->dataMapper);
        $sedeInail->id = 3;
        $sedeInail->tabella = TabellaF24::sediInail;
        $sedeInail->codice = '12345';
        $sedeInail->descrizione = 'Roma';
        $sedeInail->inUso = true;
        $this->testAdapter->accodaSelect(new TestResultSet([$sedeInail]));

        $rigoInail = new Versamento($this->dataMapper);
        $rigoInail->sezione = SezioneF24::inail;
        $rigoInail->scadenza = $data;
        $rigoInail->codiceSede = '12345';
        $rigoInail->posizione = '12345678';
        $rigoInail->cifraControllo = '90';
        $rigoInail->numeroRiferimento = '902026';
        $rigoInail->causale = 'P';
        $rigoInail->importoDebito = 180.0;
        $this->assertSame([], $controllo->verificaRiga($rigoInail));

        // ELIDE
        $tipoElide = new CodiceTabellaF24($this->dataMapper);
        $tipoElide->id = 4;
        $tipoElide->tabella = TabellaF24::tipiElide;
        $tipoElide->codice = 'F';
        $tipoElide->descrizione = 'Locazioni';
        $tipoElide->inUso = true;
        $this->testAdapter->accodaSelect(new TestResultSet([$tipoElide]));

        $ctElide = $this->creaCodiceTributo('1500', SezioneCodiceTributo::elementiIdentificativi, ModalitaUtilizzo::debito, '0000', true);
        $ctElide->elementiIdentificativi = '4';
        $rigoElide = new Versamento($this->dataMapper);
        $rigoElide->sezione = SezioneF24::elide;
        $rigoElide->scadenza = $data;
        $rigoElide->tipoElide = 'F';
        $rigoElide->elementiIdentificativi = 'ABC1234567890';
        $rigoElide->codiceTributo = $ctElide;
        $rigoElide->annoRiferimento = 2026;
        $rigoElide->importoDebito = 67.0;
        $this->assertSame([], $controllo->verificaRiga($rigoElide));

        // Accise
        $prov = new CodiceTabellaF24($this->dataMapper);
        $prov->id = 5;
        $prov->tabella = TabellaF24::province;
        $prov->codice = 'RM';
        $prov->descrizione = 'Roma';
        $prov->inUso = true;
        $this->testAdapter->accodaSelect(new TestResultSet([$prov]));

        $ctAccise = $this->creaCodiceTributo('2806', SezioneCodiceTributo::accise, ModalitaUtilizzo::debito, '0000', true);
        $ctAccise->enteAccise = 'D';
        $ctAccise->provinciaRichiesta = true;
        $ctAccise->meseRichiesto = true;
        $ctAccise->codiceIdentificativoEnte = 'Codice ditta';
        $rigoAccise = new Versamento($this->dataMapper);
        $rigoAccise->sezione = SezioneF24::accise;
        $rigoAccise->scadenza = $data;
        $rigoAccise->codiceTributo = $ctAccise;
        $rigoAccise->provincia = 'RM';
        $rigoAccise->codiceIdentificativoEnte = 'DITTA01';
        $rigoAccise->meseRiferimento = 5;
        $rigoAccise->annoRiferimento = 2026;
        $rigoAccise->importoDebito = 300.0;
        $this->assertSame([], $controllo->verificaRiga($rigoAccise));

        // Testata e verifica delega completa
        $delega = $this->creaDelegaTest();
        $delega->codiceFiscaleCoobbligato = 'RSSMRA80A01H501U';
        $delega->codiceIdentificativo = CodiceIdentificativo::erede;
        $errTestata = $controllo->verificaTestata($delega, 'RSSMRA80A01H501U');
        $this->assertArrayHasKey('codiceFiscaleCoobbligato', $errTestata);

        $delega->codiceFiscaleCoobbligato = null;
        $delega->codiceIdentificativo = null;
        $this->testAdapter->accodaSelect(new TestResultSet([$rigoErario]));
        $esito = $controllo->verifica($delega);
        $this->assertTrue($esito->isConfermabile());
        $this->assertSame(10000, $esito->getSaldo());
        $this->assertCount(1, $esito->modelli);
    }

    public function testCompensazioniAndGenerazioneDelegheAndArchivioVersamenti(): void
    {
        $soggetto = $this->creaSoggettoTest();
        $delega = $this->creaDelegaTest($soggetto);
        $ctErario = $this->creaCodiceTributo('4001', SezioneCodiceTributo::erario, ModalitaUtilizzo::entrambi, '0101', true);

        $rigoDebito = new Versamento($this->dataMapper);
        $rigoDebito->id = 10;
        $rigoDebito->studio = $this->studioPredefinito;
        $rigoDebito->soggetto = $soggetto;
        $rigoDebito->delega = $delega;
        $rigoDebito->scadenza = $delega->scadenza;
        $rigoDebito->sezione = SezioneF24::erario;
        $rigoDebito->codiceTributo = $ctErario;
        $rigoDebito->rateazione = '0101';
        $rigoDebito->annoRiferimento = 2025;
        $rigoDebito->importoDebito = 500.0;
        $rigoDebito->importoCredito = 0.0;
        $rigoDebito->ordine = 1;

        $credito = new Credito($this->dataMapper);
        $credito->id = 20;
        $credito->studio = $this->studioPredefinito;
        $credito->soggetto = $soggetto;
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = $ctErario;
        $credito->rateazione = '0101';
        $credito->annoRiferimento = 2025;
        $credito->importo = 300.0;
        $credito->utilizzabileDal = new SismaDate('2026-01-01');

        $compensazioni = new Compensazioni($this->dataMapper);

        // getCapienza: getElenco($delega)
        $this->testAdapter->accodaSelect(new TestResultSet([$rigoDebito]));
        $this->assertSame(50000, $compensazioni->getCapienza($delega, $credito));

        // compensa: getUtilizzi($credito), getProssimoOrdine($delega, sezione)
        $this->testAdapter->accodaSelect(
                new TestResultSet([]),
                new TestResultSet([$rigoDebito]),
        );
        $utilizzo = $compensazioni->compensa($delega, $credito, 200.0);
        $this->assertSame(200.0, $utilizzo->importoCredito);
        $this->assertSame($credito, $utilizzo->credito);
        $this->assertSame(2, $utilizzo->ordine);

        // Delega non modificabile lancia eccezione
        $delegaConfermata = $this->creaDelegaTest($soggetto, StatoDelega::confermata);
        try {
            $compensazioni->compensa($delegaConfermata, $credito, 50.0);
            $this->fail('Attesa DelegaNonModificabileException');
        } catch (DelegaNonModificabileException $e) {
            $this->assertStringContainsString('confermata', $e->getMessage());
        }

        // ArchivioVersamenti::registra
        $archivio = new ArchivioVersamenti($this->dataMapper);
        $nuovoVers = new Versamento($this->dataMapper);
        $nuovoVers->scadenza = new SismaDate('2026-06-16');
        $nuovoVers->sezione = SezioneF24::erario;
        $nuovoVers->codiceTributo = $ctErario;
        $nuovoVers->rateazione = '0101';
        $nuovoVers->annoRiferimento = 2025;
        $nuovoVers->importoDebito = 150.0;
        $nuovoVers->importoCredito = 0.0;

        // rimuoviPrecedenti -> getGenerati([])
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $esitoReg = $archivio->registra($soggetto, 'Imu', 'calc-1', [$nuovoVers]);
        $this->assertCount(1, $esitoReg->versamenti);
        $this->assertSame([], $esitoReg->differenze);

        // GenerazioneDeleghe::genera
        $gen = new GenerazioneDeleghe($this->dataMapper);
        $daVersare = clone $nuovoVers;
        $daVersare->id = 55;
        $daVersare->studio = $this->studioPredefinito;
        $daVersare->soggetto = $soggetto;
        $daVersare->delega = null;
        $daVersare->detrazioneImu = 200.0;

        // getDaVersare -> getBySoggettoAndScadenzaAndDelega, countByVersamentoOmesso(0)
        // trovaBozza -> getPerScadenza([$delega]), isCompatibile -> getByDelega([])
        // aggiungi -> isCompatibile -> getByDelega([]), getProssimoOrdine -> getByDelegaAndSezione([]), aggiornaDetrazioneImu -> getByDelega([$daVersare])
        $this->testAdapter->accodaSelect(
                new TestResultSet([$daVersare]),
                TestResultSet::conteggio(0),
                new TestResultSet([$delega]),
                new TestResultSet([]),
                new TestResultSet([]),
                new TestResultSet([]),
                new TestResultSet([$daVersare]),
        );
        $delegheGen = $gen->genera($soggetto, new SismaDate('2026-06-16'), false);
        $this->assertCount(1, $delegheGen);
        $this->assertSame(200.0, $delegheGen[0]->detrazioneImu);
    }

    public function testCopiaVersamentiDatiContribuenteRiepilogoAndRavvedimentoSanzioniServices(): void
    {
        $soggetto = $this->creaSoggettoTest();
        $ctMensile = $this->creaCodiceTributo('1040', SezioneCodiceTributo::erario, ModalitaUtilizzo::debito, '00MM', true);
        $orig = new Versamento($this->dataMapper);
        $orig->id = 1;
        $orig->studio = $this->studioPredefinito;
        $orig->soggetto = $soggetto;
        $orig->scadenza = new SismaDate('2026-06-16');
        $orig->sezione = SezioneF24::erario;
        $orig->codiceTributo = $ctMensile;
        $orig->rateazione = '0005';
        $orig->annoRiferimento = 2026;
        $orig->importoDebito = 100.0;
        $orig->importoCredito = 0.0;

        $copiaService = new CopiaVersamenti($this->dataMapper);
        $copia = $copiaService->copia($orig, 1);
        $this->assertNotNull($copia);
        $this->assertSame('0006', $copia->rateazione);
        $this->assertSame('2026-07-16', $copia->scadenza->format('Y-m-d'));

        $ripetuti = $copiaService->ripeti($orig, Periodicita::mensile, 3);
        $this->assertCount(3, $ripetuti);
        $this->assertSame('0008', $ripetuti[2]->rateazione);
        $this->assertSame([], $copiaService->verifica($ripetuti));
        $copiaService->salva($ripetuti);

        // Rateazione NNRR che supera il numero massimo di rate
        $ctRate = $this->creaCodiceTributo('4001', SezioneCodiceTributo::erario, ModalitaUtilizzo::debito, 'NNRR', true);
        $origRata = clone $orig;
        $origRata->codiceTributo = $ctRate;
        $origRata->rateazione = '0203';
        $ripetutiRata = $copiaService->ripeti($origRata, Periodicita::mensile, 5);
        $this->assertCount(1, $ripetutiRata);
        $this->assertSame('0303', $ripetutiRata[0]->rateazione);

        // DatiContribuente
        $this->assertSame(['Roma', 'RM'], DatiContribuente::getLuogoNascita($soggetto));
        $this->assertSame([], DatiContribuente::getDatiMancanti($soggetto));

        $soggEstero = new Soggetto($this->dataMapper);
        $soggEstero->tipoSoggetto = TipoSoggetto::personaFisica;
        $nazione = new Nazione($this->dataMapper);
        $nazione->denominazione = 'Francia';
        $soggEstero->nazioneNascita = $nazione;
        $this->assertSame(['Francia', 'EE'], DatiContribuente::getLuogoNascita($soggEstero));
        $this->assertNotEmpty(DatiContribuente::getDatiMancanti($soggEstero));

        $ind = new Indirizzo($this->dataMapper);
        $ind->id = 1;
        $ind->tipoIndirizzo = TipoIndirizzo::domicilioFiscale;
        $ind->comune = $soggetto->comuneNascita;
        $ind->indirizzo = 'Via Roma';
        $ind->numeroCivico = '10';
        $ind->cap = '00100';
        $ind->validoDal = new SismaDate('2020-01-01');
        $this->testAdapter->accodaSelect(new TestResultSet([$ind]));
        $domicilio = (new DatiContribuente($this->dataMapper))->getDomicilio($soggetto, new SismaDate('2026-06-16'));
        $this->assertSame($ind, $domicilio);

        // RiepilogoScrivania
        $this->testAdapter->accodaSelect(
                TestResultSet::conteggio(2),
                TestResultSet::conteggio(1),
                TestResultSet::conteggio(2),
                TestResultSet::conteggio(10),
        );
        $indScrivania = (new RiepilogoScrivania($this->dataMapper))->getIndicatori($this->studioPredefinito);
        $this->assertSame(3, $indScrivania['Da versare entro 30 giorni']);
        $this->assertSame(2, $indScrivania['In bozza']);
        $this->assertSame(10, $indScrivania['Totale deleghe']);

        // RavvedimentoService
        $delega = $this->creaDelegaTest($soggetto);
        $tassoLeg = new TassoInteresseLegale($this->dataMapper);
        $tassoLeg->id = 1;
        $tassoLeg->validoDal = new SismaDate('2025-01-01');
        $tassoLeg->tasso = 2.0;

        $ravv = new Ravvedimento($this->dataMapper);
        $ravv->id = 5;
        $ravv->delega = $delega;
        $ravv->sezione = SezioneF24::erario;
        $ravv->codiceTributo = $ctRate;
        $ravv->codiceSanzione = $this->creaCodiceTributo('8901', SezioneCodiceTributo::erario, ModalitaUtilizzo::debito, '0000', true);
        $ravv->codiceInteressi = $this->creaCodiceTributo('1989', SezioneCodiceTributo::erario, ModalitaUtilizzo::debito, '0000', true);
        $ravv->rateazione = '0101';
        $ravv->annoRiferimento = 2025;
        $ravv->importoTributo = 1000.0;
        $ravv->scadenzaOriginale = new SismaDate('2026-06-01');
        $ravv->termineDichiarazione = new SismaDate('2026-10-31');
        $ravv->circostanza = CircostanzaRavvedimento::cases()[0];

        // TassoInteresseLegaleModel::getSerie(), ArchivioVersamenti::rimuoviPrecedenti (getGenerati), getProssimoOrdine x3, aggiornaDetrazioneImu (getByDelega)
        $this->testAdapter->accodaSelect(
                new TestResultSet([$tassoLeg]),
                new TestResultSet([]),
                new TestResultSet([]),
                new TestResultSet([]),
                new TestResultSet([]),
                new TestResultSet([]),
        );
        $ravvService = new RavvedimentoService($this->dataMapper);
        $ravvService->registra($ravv);
        $this->assertSame(15, $ravv->giorniRitardo);
        $this->assertGreaterThan(0, $ravv->sanzione);

        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $ravvService->elimina($ravv);

        // SanzioniCiviliService
        $tassoRif = new TassoRiferimento($this->dataMapper);
        $tassoRif->id = 1;
        $tassoRif->validoDal = new SismaDate('2025-01-01');
        $tassoRif->tasso = 3.0;

        $sedeInps = new CodiceTabellaF24($this->dataMapper);
        $sedeInps->id = 2;
        $sedeInps->tabella = TabellaF24::sediInps;
        $sedeInps->codice = '7000';
        $sedeInps->descrizione = 'Roma';
        $sedeInps->inUso = true;

        $causaleInps = new CausaleContributo($this->dataMapper);
        $causaleInps->id = 1;
        $causaleInps->ente = CausaleContributo::ENTE_INPS;
        $causaleInps->causale = 'DM10';
        $causaleInps->modalita = ModalitaUtilizzo::debito;
        $causaleInps->periodoDa = ObbligoCampo::facoltativo;
        $causaleInps->periodoA = ObbligoCampo::facoltativo;
        $causaleInps->inUso = true;

        $sanz = new SanzioneCivile($this->dataMapper);
        $sanz->id = 7;
        $sanz->delega = $delega;
        $sanz->sezione = SezioneF24::inps;
        $sanz->riferimento = 'DM10 05/2026';
        $sanz->importoContributi = 1000.0;
        $sanz->scadenzaOriginale = new SismaDate('2026-06-01');
        $sanz->regime = RegimeSanzioneCivile::cases()[0];
        $sanz->spontaneo = true;
        $sanz->codiceSede = '7000';
        $sanz->causale = 'DM10';
        $sanz->posizione = '1234567890';
        $sanz->periodoDa = '05/2026';

        // TassoRiferimentoModel::getSerie(), verificaInps(sedeInps, causaleInps), rimuoviPrecedenti(getGenerati), getProssimoOrdine, aggiornaDetrazioneImu
        $this->testAdapter->accodaSelect(
                new TestResultSet([$tassoRif]),
                new TestResultSet([$sedeInps]),
                new TestResultSet([$causaleInps]),
                new TestResultSet([]),
                new TestResultSet([]),
                new TestResultSet([]),
        );
        $sanzService = new SanzioniCiviliService($this->dataMapper);
        $sanzService->registra($sanz);
        $this->assertSame(15, $sanz->giorniRitardo);
        $this->assertGreaterThan(0, $sanz->sanzione);

        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $sanzService->elimina($sanz);
    }
}
