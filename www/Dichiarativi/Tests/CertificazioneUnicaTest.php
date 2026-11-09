<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Dichiarativi\Application\Controllers\CertificazioneUnicaController;
use Dichiarativi\Application\Entities\CertificazioneUnica;
use Dichiarativi\Application\Entities\ValoreCertificazioneUnica;
use Dichiarativi\Application\Forms\CertificazioneUnicaForm;
use Dichiarativi\Application\Services\Redditi\PuntiCertificazioneUnica;
use Telonio\Application\Enumerations\RuoloUtente;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;

/**
 * Archivio delle Certificazioni Uniche: lettura dei punti, controlli della
 * testata, salvataggio dei punti dal controller.
 */
class CertificazioneUnicaTest extends SuiteTestCase
{

    public function testLetturaDeiPunti(): void
    {
        $this->assertNull(PuntiCertificazioneUnica::leggi(1, ' '));
        $this->assertSame(25000.5, PuntiCertificazioneUnica::leggi(1, '25.000,50'));
        $this->assertSame(1234.56, PuntiCertificazioneUnica::leggi(21, '1234.56'));
        $this->assertSame(365.0, PuntiCertificazioneUnica::leggi(6, '365'));
        $this->assertFalse(PuntiCertificazioneUnica::leggi(6, '366'));
        $this->assertFalse(PuntiCertificazioneUnica::leggi(390, '1,5'));
        $this->assertFalse(PuntiCertificazioneUnica::leggi(1, '-10'));
        $this->assertFalse(PuntiCertificazioneUnica::leggi(1, 'abc'));
        $this->assertContains(PuntiCertificazioneUnica::ACCONTO_COMUNALE_SUCCESSIVO, PuntiCertificazioneUnica::getPunti());
    }

    private function creaContribuente(): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 100;
        $soggetto->studio = $this->creaStudio(1);
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        return $soggetto;
    }

    public function testCodiceFiscaleDelSostituto(): void
    {
        $certificazione = new CertificazioneUnica($this->dataMapper);
        $certificazione->soggetto = $this->creaContribuente();
        $form = new CertificazioneUnicaForm($certificazione, $this->dataMapper);
        $form->handleRequest($this->creaRequest(['annoImposta' => '2025', 'codiceFiscaleSostituto' => '1234', 'denominazioneSostituto' => 'Alfa']));
        $this->assertFalse($form->isValid());
        $form = new CertificazioneUnicaForm($certificazione, $this->dataMapper);
        $form->handleRequest($this->creaRequest(['annoImposta' => '2025', 'codiceFiscaleSostituto' => '01234560785', 'denominazioneSostituto' => 'alfa  spa', 'conguagliata' => '1']));
        $this->assertTrue($form->isValid());
        $salvata = $form->resolveEntity();
        $this->assertSame('ALFA SPA', $salvata->denominazioneSostituto);
        $this->assertTrue($salvata->conguagliata);
    }

    public function testSalvataggioDeiPunti(): void
    {
        $studio = $this->creaStudio(1);
        $this->utenteCorrente = $this->creaUtente($studio, RuoloUtente::amministratore, 10);
        $this->autenticaUtente($this->utenteCorrente);
        $cattura = $this->catturaRenderERouter();
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $soggetto = $this->creaContribuente();
        $controller = new CertificazioneUnicaController($this->dataMapper);

        $controller->create($this->creaRequest([], [], [], 'GET'), $soggetto, 2025);
        $this->assertSame('certificazioneUnica/form', $cattura->ultimaVista);
        $this->assertArrayHasKey('Redditi', $cattura->ultimeVars['sezioniPunti']);

        // punto non valido: il form torna con l'errore
        $controller->create($this->creaRequest(['annoImposta' => '2025', 'codiceFiscaleSostituto' => '01234560785', 'denominazioneSostituto' => 'ALFA SPA', 'punto' => [6 => '400']]), $soggetto, 2025);
        $this->assertArrayHasKey(6, $cattura->ultimeVars['erroriPunti']);
        $this->assertSame('400', $cattura->ultimeVars['valoriPunti'][6]);

        $this->testAdapter->impostaSelectCallback(fn() => TestResultSet::conteggio(0));
        $this->testAdapter->queryEseguite = [];
        $controller->create($this->creaRequest(['annoImposta' => '2025', 'codiceFiscaleSostituto' => '01234560785', 'denominazioneSostituto' => 'ALFA SPA', 'punto' => [1 => '28.500,00', 6 => '365', 21 => '5.120,40', 22 => '']]), $soggetto, 2025);
        $this->testAdapter->impostaSelectCallback(null);
        $salvati = array_filter($this->testAdapter->queryEseguite, fn(array $query) => str_starts_with(ltrim($query['cmd']), 'INSERT') && str_contains($query['cmd'], 'valore_certificazione_unica'));
        $this->assertSame('contribuente-dichiarativi/show/soggetto/100/anno/2025', $cattura->ultimoRedirect);
        $this->assertCount(3, $salvati);
    }

    public function testValoriSalvatiNelForm(): void
    {
        $studio = $this->creaStudio(1);
        $this->utenteCorrente = $this->creaUtente($studio, RuoloUtente::amministratore, 10);
        $this->autenticaUtente($this->utenteCorrente);
        $cattura = $this->catturaRenderERouter();
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $certificazione = new CertificazioneUnica($this->dataMapper);
        $certificazione->id = 7;
        $certificazione->studio = $studio;
        $certificazione->soggetto = $this->creaContribuente();
        $certificazione->annoImposta = 2025;
        $certificazione->codiceFiscaleSostituto = '01234560785';
        $certificazione->denominazioneSostituto = 'ALFA SPA';
        $reddito = new ValoreCertificazioneUnica($this->dataMapper);
        $reddito->certificazioneUnica = $certificazione;
        $reddito->punto = 1;
        $reddito->valore = 28500.0;
        $giorni = new ValoreCertificazioneUnica($this->dataMapper);
        $giorni->certificazioneUnica = $certificazione;
        $giorni->punto = 6;
        $giorni->valore = 365.0;
        $this->testAdapter->accodaSelect(new TestResultSet([$reddito, $giorni]));
        (new CertificazioneUnicaController($this->dataMapper))->update($this->creaRequest([], [], [], 'GET'), $certificazione);
        $this->assertSame(['1' => '28.500,00', '6' => '365'], array_map('strval', $cattura->ultimeVars['valoriPunti']));
    }
}
