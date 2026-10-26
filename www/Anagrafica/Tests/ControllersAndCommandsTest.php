<?php

namespace Anagrafica\Tests;

use Anagrafica\Application\Controllers\ContoCorrenteController;
use Anagrafica\Application\Controllers\DatiCatastaliController;
use Anagrafica\Application\Controllers\DatiFiscaliController;
use Anagrafica\Application\Controllers\DocumentoIdentitaController;
use Anagrafica\Application\Controllers\ImmobileController;
use Anagrafica\Application\Controllers\IndirizzoController;
use Anagrafica\Application\Controllers\LegameController;
use Anagrafica\Application\Controllers\PossessoController;
use Anagrafica\Application\Controllers\RecapitoController;
use Anagrafica\Application\Controllers\SoggettoController;
use Anagrafica\Application\Controllers\TabelleController;
use Anagrafica\Application\Entities\CodiceAteco;
use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Services\TabelleRiferimento\ArchivioComuniAnpr;
use Anagrafica\Application\Services\TabelleRiferimento\ClassificazioniAtecoIstat;
use Anagrafica\Application\Services\TabelleRiferimento\CodiciIsoUe;
use Anagrafica\Application\Services\TabelleRiferimento\GeneratoreSql;
use Anagrafica\Application\Services\TabelleRiferimento\StatiEsteriAnpr;
use Anagrafica\Console\Commands\TabelleRiferimentoCommand;
use Telonio\Application\Entities\Utente;
use Telonio\Application\Enumerations\RuoloUtente;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;
use SismaFramework\Core\Exceptions\BadRequestException;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ControllersAndCommandsTest extends SuiteTestCase
{

    private function preparaSessione(): Soggetto
    {
        $studio = $this->creaStudio(1);
        $this->utenteCorrente = $this->creaUtente($studio, RuoloUtente::amministratore, 10);
        $this->autenticaUtente($this->utenteCorrente);

        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 100;
        $soggetto->studio = $studio;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        return $soggetto;
    }

    public function testSoggettoController(): void
    {
        $soggetto = $this->preparaSessione();
        $cattura = $this->catturaRenderERouter();

        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $controller = new SoggettoController($this->dataMapper);

        // index
        $this->testAdapter->accodaSelect(
            TestResultSet::conteggio(1),
            new TestResultSet([$soggetto]),
        );
        $controller->index('ROSSI');
        $this->assertSame('soggetto/index', $cattura->ultimaVista);
        $this->assertSame(1, $cattura->ultimeVars['totale']);

        // show
        $this->testAdapter->accodaSelect(
            new TestResultSet([]), // datiFiscali getStorico
            new TestResultSet([]), // datiFiscali getVigenteAl
            new TestResultSet([]), // indirizzo getStorico
            new TestResultSet([]), // indirizzo getVigentiAl -> getStorico
            new TestResultSet([]), // recapito getElenco
            new TestResultSet([]), // conto getElenco
            new TestResultSet([]), // legame getCollegati
            new TestResultSet([]), // legame getIncarichi
            new TestResultSet([]), // documento getElenco
            new TestResultSet([]), // possesso getStoricoSoggetto
        );
        $controller->show($soggetto, 'generale', '2025-01-01');
        $this->assertSame('soggetto/show', $cattura->ultimaVista);

        // create GET
        $controller->create($this->creaRequest([], [], [], 'GET'), TipoSoggetto::personaFisica);
        $this->assertSame('soggetto/form', $cattura->ultimaVista);

        // cerca JSON
        $this->testAdapter->accodaSelect(new TestResultSet([$soggetto]));
        $controller->cerca('RO');
        $this->assertCount(1, $cattura->ultimoJson);

        // delete bloccato da incarichi
        $this->testAdapter->accodaSelect(TestResultSet::conteggio(2));
        $reqDelete = $this->creaRequest();
        $controller->delete($reqDelete, $soggetto);
        $this->assertSame('soggetto/show/soggetto/100/scheda/legami', $cattura->ultimoRedirect);

        // delete consentito
        $this->testAdapter->impostaSelectCallback(fn() => TestResultSet::conteggio(0));
        $controller->delete($reqDelete, $soggetto);
        $this->testAdapter->impostaSelectCallback(null);
        $this->assertSame('soggetto/index', $cattura->ultimoRedirect);
    }

    public function testImmobileController(): void
    {
        $soggetto = $this->preparaSessione();
        $cattura = $this->catturaRenderERouter();

        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';

        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 50;
        $immobile->studio = $soggetto->studio;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = $comune;
        $immobile->foglio = '10';
        $immobile->particella = '20';

        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $controller = new ImmobileController($this->dataMapper);

        // index
        $this->testAdapter->accodaSelect(
            TestResultSet::conteggio(1),
            new TestResultSet([$immobile]),
            new TestResultSet([]),
            new TestResultSet([]),
        );
        $controller->index();
        $this->assertSame('immobile/index', $cattura->ultimaVista);

        // show
        $this->testAdapter->accodaSelect(
            new TestResultSet([]),
            new TestResultSet([]),
            new TestResultSet([]),
        );
        $controller->show($immobile);
        $this->assertSame('immobile/show', $cattura->ultimaVista);

        // create GET
        $controller->create($this->creaRequest([], [], [], 'GET'), TipoCatasto::fabbricati, $soggetto);
        $this->assertSame('immobile/form', $cattura->ultimaVista);

        // cerca JSON
        $this->testAdapter->accodaSelect(new TestResultSet([$immobile]));
        $controller->cerca('10');
        $this->assertCount(1, $cattura->ultimoJson);

        // delete
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $this->testAdapter->impostaSelectCallback(fn() => TestResultSet::conteggio(0));
        $controller->delete($this->creaRequest(), $immobile);
        $this->testAdapter->impostaSelectCallback(null);
        $this->assertSame('immobile/index', $cattura->ultimoRedirect);
    }

    public function testTabelleController(): void
    {
        $this->preparaSessione();
        $cattura = $this->catturaRenderERouter();

        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $controller = new TabelleController($this->dataMapper);

        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';
        $comune->validoAl = null;

        $this->testAdapter->accodaSelect(new TestResultSet([$comune]));
        $controller->comuni('Ro');
        $this->assertCount(1, $cattura->ultimoJson);

        $nazione = new Nazione($this->dataMapper);
        $nazione->id = 2;
        $nazione->denominazione = 'Francia';
        $nazione->validoAl = new SismaDate('1990-12-31');
        $this->testAdapter->accodaSelect(new TestResultSet([$nazione]));
        $controller->nazioni('Fr');
        $this->assertStringContainsString('fino al 31/12/1990', $cattura->ultimoJson[0]['etichetta']);

        $ateco = new CodiceAteco($this->dataMapper);
        $ateco->id = 3;
        $ateco->codice = '69.20.01';
        $ateco->titolo = 'Commercialisti';
        $this->testAdapter->accodaSelect(new TestResultSet([$ateco]));
        $controller->ateco('69', '2025-05-01');
        $this->assertCount(1, $cattura->ultimoJson);
    }

    public function testControllersSecondariAnagrafica(): void
    {
        $soggetto = $this->preparaSessione();
        $cattura = $this->catturaRenderERouter();

        // DatiFiscaliController
        $this->testAdapter->accodaSelect(
            new TestResultSet([$this->utenteCorrente]),
            new TestResultSet([]),
        );
        $dfController = new DatiFiscaliController($this->dataMapper);
        $dfController->create($this->creaRequest([], [], [], 'GET'), $soggetto);
        $this->assertSame('datiFiscali/form', $cattura->ultimaVista);

        // DatiCatastaliController
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'Roma';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 50;
        $immobile->studio = $soggetto->studio;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = $comune;
        $immobile->foglio = '1';
        $immobile->particella = '2';

        $this->testAdapter->accodaSelect(
            new TestResultSet([$this->utenteCorrente]),
            new TestResultSet([]),
        );
        $dcController = new DatiCatastaliController($this->dataMapper);
        $dcController->create($this->creaRequest([], [], [], 'GET'), $immobile);
        $this->assertSame('datiCatastali/form', $cattura->ultimaVista);

        // IndirizzoController
        $italia = new Nazione($this->dataMapper);
        $italia->id = 1;
        $italia->codiceIso = 'IT';
        $italia->denominazione = 'Italia';
        $this->testAdapter->accodaSelect(
            new TestResultSet([$this->utenteCorrente]),
            new TestResultSet([$italia]),
            new TestResultSet([$italia]),
        );
        $indController = new IndirizzoController($this->dataMapper);
        $indController->create($this->creaRequest([], [], [], 'GET'), $soggetto, TipoIndirizzo::residenza->value);
        $this->assertSame('indirizzo/form', $cattura->ultimaVista);

        // LegameController
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $legController = new LegameController($this->dataMapper);
        $legController->create($this->creaRequest([], [], [], 'GET'), $soggetto);
        $this->assertSame('legame/form', $cattura->ultimaVista);

        // PossessoController
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $posController = new PossessoController($this->dataMapper);
        $posController->create($this->creaRequest([], [], [], 'GET'), $immobile, null);
        $this->assertSame('possesso/form', $cattura->ultimaVista);

        // ContoCorrenteController
        $this->testAdapter->accodaSelect(
            new TestResultSet([$this->utenteCorrente]),
            new TestResultSet([]),
        );
        $ccController = new ContoCorrenteController($this->dataMapper);
        $ccController->create($this->creaRequest([], [], [], 'GET'), $soggetto);
        $this->assertSame('contoCorrente/form', $cattura->ultimaVista);

        // DocumentoIdentitaController
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $docController = new DocumentoIdentitaController($this->dataMapper);
        $docController->create($this->creaRequest([], [], [], 'GET'), $soggetto);
        $this->assertSame('documentoIdentita/form', $cattura->ultimaVista);

        // RecapitoController
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $recController = new RecapitoController($this->dataMapper);
        $recController->create($this->creaRequest([], [], [], 'GET'), $soggetto);
        $this->assertSame('recapito/form', $cattura->ultimaVista);
    }

    public function testPossessoControllerVariazione(): void
    {
        $soggetto = $this->preparaSessione();
        $cattura = $this->catturaRenderERouter();
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'Roma';
        $comune->codiceCatastale = 'H501';
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 50;
        $immobile->studio = $soggetto->studio;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = $comune;
        $immobile->foglio = '1';
        $immobile->particella = '2';
        // quota ereditata dal primo genitore, poi aumentata alla morte del secondo
        $crea = function (int $id, string $dal, float $quota, ?string $al) use ($soggetto, $immobile): Possesso {
            $possesso = new Possesso($this->dataMapper);
            $possesso->id = $id;
            $possesso->soggetto = $soggetto;
            $possesso->immobile = $immobile;
            $possesso->titolo = TitoloPossesso::proprieta;
            $possesso->quota = $quota;
            $possesso->utilizzo = UtilizzoImmobile::aDisposizione;
            $possesso->validoDal = new SismaDate($dal);
            $possesso->validoAl = $al === null ? null : new SismaDate($al);
            return $possesso;
        };
        $prima = $crea(1, '2015-03-01', 16.67, '2024-05-09');
        $ultima = $crea(2, '2024-05-10', 50.0, null);
        $this->testAdapter->impostaSelectCallback(fn(string $query): TestResultSet => str_contains($query, 'possesso') ? new TestResultSet([$prima, $ultima]) : new TestResultSet([$this->utenteCorrente]));

        // dalla versione più vecchia la variazione parte comunque dalla più recente, con il titolo fisso
        (new PossessoController($this->dataMapper))->variazione($this->creaRequest([], [], [], 'GET'), $prima);
        $this->assertSame('possesso/form', $cattura->ultimaVista);
        $this->assertTrue($cattura->ultimeVars['variazione']);
        $this->assertSame([TitoloPossesso::proprieta], $cattura->ultimeVars['titoli']);
        $this->assertSame(50.0, $cattura->ultimeVars['formEntity']->quota);
        $this->assertSame(UtilizzoImmobile::aDisposizione, $cattura->ultimeVars['formEntity']->utilizzo);
        $this->assertFalse(isset($cattura->ultimeVars['formEntity']->id));

        // la rettifica resta una rettifica
        (new PossessoController($this->dataMapper))->update($this->creaRequest([], [], [], 'GET'), $ultima);
        $this->assertFalse($cattura->ultimeVars['variazione']);
        $this->assertSame(TitoloPossesso::cases(), $cattura->ultimeVars['titoli']);
    }

    public function testPossessoControllerEccezioneParametri(): void
    {
        $this->preparaSessione();
        $this->testAdapter->accodaSelect(new TestResultSet([$this->utenteCorrente]));
        $posController = new PossessoController($this->dataMapper);
        $this->expectException(BadRequestException::class);
        $posController->create($this->creaRequest([], [], [], 'GET'), null, null);
    }

    public function testTabelleRiferimentoCommand(): void
    {
        $archivioComuni = $this->createStub(ArchivioComuniAnpr::class);
        $archivioComuni->method('leggi')->willReturn([
            [
                'id_anpr' => 1,
                'codice_istat' => '058091',
                'codice_catastale' => 'H501',
                'denominazione' => 'Roma',
                'sigla_provincia' => 'RM',
                'valido_dal' => '1884-01-01',
                'valido_al' => null,
            ],
        ]);
        $archivioComuni->method('getEscluse')->willReturn(0);

        $codiciIso = $this->createStub(CodiciIsoUe::class);
        $codiciIso->method('leggi')->willReturn(['ITA' => 'IT']);

        $statiEsteri = $this->createMock(StatiEsteriAnpr::class);
        $statiEsteri->expects($this->once())->method('leggi')->with('finto_stati.xlsx', ['ITA' => 'IT'])->willReturn([
            [
                'id_anpr' => 100,
                'codice_iso' => 'IT',
                'codice_iso3' => 'ITA',
                'codice_catastale' => null,
                'denominazione' => 'Italia',
                'valido_dal' => '1861-03-17',
                'valido_al' => null,
            ],
        ]);

        $ateco = $this->createStub(ClassificazioniAtecoIstat::class);
        $ateco->method('leggi')->willReturn([
            [
                'classificazione' => '2025',
                'codice' => '69.20.01',
                'livello' => 6,
                'codice_padre' => '69.20.0',
                'titolo' => 'Commercialisti',
                'codice_raccordo' => null,
            ],
        ]);

        $command = new TabelleRiferimentoCommand($archivioComuni, $statiEsteri, $codiciIso, $ateco, new GeneratoreSql());
        $this->assertTrue($command->checkCompatibility('tabelle-riferimento'));
        $this->assertFalse($command->checkCompatibility('altro'));

        $fileSql = tempnam(sys_get_temp_dir(), 'test_sql_');
        try {
            $command->setOptions([
                'sql' => $fileSql,
                'comuni' => 'finto_comuni.csv',
                'stati' => 'finto_stati.xlsx',
                'iso' => 'finto_iso.csv',
                'ateco2025' => 'finto_2025.xlsx',
                'ateco2022' => 'finto_2022.xlsx',
            ]);
            ob_start();
            $esito = $command->run();
            $output = ob_get_clean();

            $this->assertTrue($esito);
            $this->assertStringContainsString('Script scritto in', $output);
            $contenutoSql = file_get_contents($fileSql);
            $this->assertStringContainsString('INSERT INTO `comune`', $contenutoSql);
            $this->assertStringContainsString('INSERT INTO `nazione`', $contenutoSql);
            $this->assertStringContainsString(CodiciIsoUe::ENDPOINT, $contenutoSql);
            $this->assertStringContainsString('INSERT INTO `codice_ateco`', $contenutoSql);
        } finally {
            if (file_exists($fileSql)) {
                unlink($fileSql);
            }
        }
    }
}
