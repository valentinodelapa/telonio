<?php

namespace Deleghe\Tests;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Deleghe\Application\Entities\CausaleContributo;
use Deleghe\Application\Entities\CodiceTabellaF24;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Intermediario;
use Deleghe\Application\Entities\InvioTelematico;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\RegolaRavvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\TassoInteresseLegale;
use Deleghe\Application\Entities\TassoRiferimento;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\CircostanzaRavvedimento;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\ObbligoCampo;
use Deleghe\Application\Enumerations\RegimeSanzioneCivile;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Enumerations\TabellaF24;
use Deleghe\Application\Forms\CreditoForm;
use Deleghe\Application\Forms\DelegaForm;
use Deleghe\Application\Forms\IntermediarioForm;
use Deleghe\Application\Forms\RavvedimentoForm;
use Deleghe\Application\Forms\SanzioneCivileForm;
use Deleghe\Application\Forms\VersamentoForm;
use Deleghe\Application\Models\CausaleContributoModel;
use Deleghe\Application\Models\CodiceTabellaF24Model;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\DelegaModel;
use Deleghe\Application\Models\IntermediarioModel;
use Deleghe\Application\Models\InvioTelematicoModel;
use Deleghe\Application\Models\RavvedimentoModel;
use Deleghe\Application\Models\RegolaRavvedimentoModel;
use Deleghe\Application\Models\SanzioneCivileModel;
use Deleghe\Application\Models\TassoInteresseLegaleModel;
use Deleghe\Application\Models\TassoRiferimentoModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Permissions\DelegaPermission;
use Deleghe\Application\Voters\DelegaVoter;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Security\Enumerations\AccessControlEntry;

class VotersModelsAndFormsTest extends SuiteTestCase
{

    private function creaSoggettoTest(int $id = 1): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = $id;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->cognome = 'Rossi';
        $soggetto->nome = 'Mario';
        $soggetto->nominativo = 'Rossi Mario';
        return $soggetto;
    }

    private function creaCodiceTributoTest(): CodiceTributo
    {
        $ct = new CodiceTributo($this->dataMapper);
        $ct->id = 10;
        $ct->sezione = SezioneCodiceTributo::erario;
        $ct->codice = '4001';
        $ct->descrizione = 'IRPEF saldo';
        $ct->modalita = ModalitaUtilizzo::entrambi;
        $ct->formatoRateazione = 'NNRR';
        $ct->annoRichiesto = true;
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

    public function testDelegaVoterAndPermission(): void
    {
        $delega = new Delega($this->dataMapper);
        $delega->id = 1;
        $delega->studio = $this->studioPredefinito;

        $voter = new DelegaVoter();
        $this->assertTrue($voter->isAllowed($delega, AccessControlEntry::allow, $this->utenteCorrente));

        DelegaPermission::isAllowed($delega, AccessControlEntry::allow, $this->utenteCorrente);

        $altroStudio = $this->creaStudio(99, 'Altro');
        $delegaAltra = new Delega($this->dataMapper);
        $delegaAltra->id = 2;
        $delegaAltra->studio = $altroStudio;

        $this->expectException(AccessDeniedException::class);
        DelegaPermission::isAllowed($delegaAltra, AccessControlEntry::allow, $this->utenteCorrente);
    }

    public function testAllModels(): void
    {
        $soggetto = $this->creaSoggettoTest();
        $ct = $this->creaCodiceTributoTest();

        // CausaleContributoModel
        $causaleModel = new CausaleContributoModel($this->dataMapper);
        $this->assertNull($causaleModel->getCausale('INPS', null));
        $causale = new CausaleContributo($this->dataMapper);
        $causale->id = 1;
        $causale->ente = 'INPS';
        $causale->causale = 'DM10';
        $this->testAdapter->accodaSelect(
                new TestResultSet([$causale]),
                TestResultSet::conteggio(5),
                new TestResultSet([$causale]),
        );
        $this->assertSame($causale, $causaleModel->getCausale('INPS', 'dm10'));
        $this->assertTrue($causaleModel->hasCausali('INPS'));
        $this->assertCount(1, $causaleModel->cerca('INPS', 'dm', 10));

        // CodiceTabellaF24Model
        $tabModel = new CodiceTabellaF24Model($this->dataMapper);
        $voce = new CodiceTabellaF24($this->dataMapper);
        $voce->id = 1;
        $voce->tabella = TabellaF24::regioni;
        $voce->codice = '08';
        $voce->descrizione = 'Lazio';
        $this->testAdapter->accodaSelect(
                new TestResultSet([$voce]),
                new TestResultSet([$voce]),
        );
        $this->assertNull($tabModel->getVoce(TabellaF24::regioni, null));
        $this->assertSame($voce, $tabModel->getVoce(TabellaF24::regioni, '08'));
        $this->assertCount(1, $tabModel->cerca(TabellaF24::regioni, 'laz', 10));

        // CodiceTributoModel
        $ctModel = new CodiceTributoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(
                new TestResultSet([$ct]),
                new TestResultSet([$ct]),
                TestResultSet::conteggio(1),
                new TestResultSet([$ct]),
        );
        $this->assertCount(1, $ctModel->cerca(SezioneCodiceTributo::erario, '4001', 10));
        $this->assertCount(1, $ctModel->consulta(SezioneCodiceTributo::erario, false, '4001', 0, 20));
        $this->assertSame(1, $ctModel->contaRisultati(null, true, ''));
        $this->assertSame($ct, $ctModel->getByCodice(SezioneCodiceTributo::erario, '4001'));

        // CreditoModel
        $credModel = new CreditoModel($this->dataMapper);
        $credito = new Credito($this->dataMapper);
        $credito->id = 1;
        $credito->soggetto = $soggetto;
        $credito->importo = 500.0;
        $credito->utilizzabileDal = new SismaDate('2026-01-01');
        $utilizzo = new Versamento($this->dataMapper);
        $utilizzo->id = 10;
        $utilizzo->importoCredito = 150.0;

        $this->testAdapter->accodaSelect(
                new TestResultSet([$credito]),
                new TestResultSet([$utilizzo]),
                new TestResultSet([$credito]),
                new TestResultSet([$utilizzo]),
        );
        $this->assertCount(1, $credModel->getElenco($soggetto));
        $this->assertSame(35000, $credModel->getResiduo($credito));
        $disponibili = $credModel->getDisponibili($soggetto, new SismaDate('2026-06-16'));
        $this->assertCount(1, $disponibili);
        $this->assertSame(35000, $disponibili[0]['residuo']);

        // DelegaModel
        $delegaModel = new DelegaModel($this->dataMapper);
        $delega = new Delega($this->dataMapper);
        $delega->id = 1;
        $delega->studio = $this->studioPredefinito;
        $delega->soggetto = $soggetto;
        $this->testAdapter->accodaSelect(
                new TestResultSet([$delega]),
                TestResultSet::conteggio(2),
                new TestResultSet([$delega]),
                TestResultSet::conteggio(1),
                TestResultSet::conteggio(2),
        );
        $this->assertCount(1, $delegaModel->cerca($this->studioPredefinito, $soggetto, StatoDelega::bozza, new SismaDate('2026-01-01'), new SismaDate('2026-12-31'), 0, 20));
        $this->assertSame(2, $delegaModel->contaRisultati($this->studioPredefinito, null, null, null, null));
        $this->assertCount(1, $delegaModel->getPerScadenza($soggetto, new SismaDate('2026-06-16')));
        $this->assertSame(3, $delegaModel->contaDaVersareEntro($this->studioPredefinito, new SismaDate('2026-06-30')));

        // IntermediarioModel, InvioTelematicoModel, RavvedimentoModel, RegolaRavvedimentoModel, SanzioneCivileModel, TassoInteresseLegaleModel, TassoRiferimentoModel
        $intModel = new IntermediarioModel($this->dataMapper);
        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->id = 1;
        $this->testAdapter->accodaSelect(new TestResultSet([$intermediario]));
        $this->assertSame($intermediario, $intModel->getPredefinito($this->studioPredefinito));

        $invioModel = new InvioTelematicoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $this->assertCount(0, $invioModel->getElenco($this->studioPredefinito));

        $ravvModel = new RavvedimentoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $this->assertCount(0, $ravvModel->getElenco($delega));

        $regolaModel = new RegolaRavvedimentoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $this->assertNull($regolaModel->getRegola($ct));

        $sanzModel = new SanzioneCivileModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $this->assertCount(0, $sanzModel->getElenco($delega));

        $tassoLegModel = new TassoInteresseLegaleModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $this->assertSame([], $tassoLegModel->getSerie());

        $tassoRifModel = new TassoRiferimentoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $this->assertSame([], $tassoRifModel->getSerie());

        // VersamentoModel
        $versModel = new VersamentoModel($this->dataMapper);
        $rigo = new Versamento($this->dataMapper);
        $rigo->id = 1;
        $rigo->sezione = SezioneF24::erario;
        $rigo->importoDebito = 100.0;
        $rigo->importoCredito = 25.0;
        $rigo->ordine = 3;

        $this->testAdapter->accodaSelect(
                new TestResultSet([$rigo]),
                new TestResultSet([$rigo]),
                new TestResultSet([$rigo]),
                new TestResultSet([$rigo]),
                TestResultSet::conteggio(0),
                new TestResultSet([$rigo]),
        );
        $perSezione = $versModel->getPerSezione($delega);
        $this->assertCount(1, $perSezione[SezioneF24::erario->value]);
        $this->assertSame(['debito' => 10000, 'credito' => 2500], $versModel->getTotali($delega));
        $this->assertSame(4, $versModel->getProssimoOrdine($delega, SezioneF24::erario));
        $this->assertCount(1, $versModel->getDaVersare($soggetto));
        $this->assertCount(1, $versModel->getGenerati($soggetto, 'Imu', 'calc-1'));
    }

    public function testAllForms(): void
    {
        $soggetto = $this->creaSoggettoTest();
        $ct = $this->creaCodiceTributoTest();

        // DelegaForm
        $delega = new Delega($this->dataMapper);
        $delega->studio = $this->studioPredefinito;
        $delega->soggetto = $soggetto;
        $reqDelega = $this->creaRequest([
            'modello' => 'O',
            'scadenza' => '2026-06-16',
            'descrizione' => 'Delega giugno',
            'detrazioneImu' => '200,00',
        ]);
        $formDelega = new DelegaForm($delega, $this->dataMapper);
        $formDelega->handleRequest($reqDelega);
        $this->assertTrue($formDelega->isValid());
        $delegaRisolta = $formDelega->resolveEntity();
        $this->assertSame(200.0, $delegaRisolta->detrazioneImu);

        // CreditoForm
        $credito = new Credito($this->dataMapper);
        $credito->studio = $this->studioPredefinito;
        $credito->soggetto = $soggetto;
        $credito->sezione = SezioneF24::erario;
        $this->testAdapter->accodaSelect(new TestResultSet([$ct]));
        $reqCredito = $this->creaRequest([
            'codiceTributo' => '10',
            'rateazione' => '0101',
            'annoRiferimento' => '2025',
            'importo' => '1.500,00',
            'utilizzabileDal' => '2026-01-01',
            'descrizione' => 'Credito IRPEF',
        ]);
        $formCredito = new CreditoForm($credito, $this->dataMapper);
        $formCredito->handleRequest($reqCredito);
        $this->assertTrue($formCredito->isValid());

        // IntermediarioForm
        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->studio = $this->studioPredefinito;
        $this->testAdapter->accodaSelect(new TestResultSet([$soggetto]));
        $reqInt = $this->creaRequest([
            'soggetto' => '1',
            'telefono' => '06 123456',
            'email' => 'info@studio.it',
            'predefinito' => '1',
        ]);
        $formInt = new IntermediarioForm($intermediario, $this->dataMapper);
        $formInt->handleRequest($reqInt);
        $this->assertTrue($formInt->isValid());

        // VersamentoForm
        $versamento = new Versamento($this->dataMapper);
        $versamento->studio = $this->studioPredefinito;
        $versamento->soggetto = $soggetto;
        $versamento->sezione = SezioneF24::erario;
        $this->testAdapter->accodaSelect(new TestResultSet([$ct]));
        $reqVers = $this->creaRequest([
            'scadenza' => '2026-06-16',
            'codiceTributo' => '10',
            'rateazione' => '0101',
            'annoRiferimento' => '2025',
            'descrizione' => 'Saldo IRPEF',
            'importoDebito' => '450,00',
            'importoCredito' => '0',
        ]);
        $formVers = new VersamentoForm($versamento, $this->dataMapper);
        $formVers->handleRequest($reqVers);
        $this->assertTrue($formVers->isValid());

        // RavvedimentoForm
        $delegaRisolta->id = 1;
        $ravv = new Ravvedimento($this->dataMapper);
        $ravv->delega = $delegaRisolta;
        $ravv->sezione = SezioneF24::erario;
        $this->testAdapter->accodaSelect(new TestResultSet([$ct]));
        $reqRavv = $this->creaRequest([
            'codiceTributo' => '10',
            'importoTributo' => '500,00',
            'scadenzaOriginale' => '2026-05-18',
            'circostanza' => CircostanzaRavvedimento::cases()[0]->value,
            'rateazione' => '0101',
            'annoRiferimento' => '2025',
        ]);
        $formRavv = new RavvedimentoForm($ravv, $this->dataMapper);
        $formRavv->handleRequest($reqRavv);
        $this->assertTrue($formRavv->isValid());

        // SanzioneCivileForm
        $sanz = new SanzioneCivile($this->dataMapper);
        $sanz->delega = $delegaRisolta;
        $sanz->sezione = SezioneF24::inps;
        $reqSanz = $this->creaRequest([
            'riferimento' => 'Contributi maggio',
            'importoContributi' => '800,00',
            'scadenzaOriginale' => '2026-05-18',
            'regime' => RegimeSanzioneCivile::cases()[0]->value,
            'spontaneo' => '1',
        ]);
        $formSanz = new SanzioneCivileForm($sanz, $this->dataMapper);
        $formSanz->handleRequest($reqSanz);
        $this->assertTrue($formSanz->isValid());
    }
}
