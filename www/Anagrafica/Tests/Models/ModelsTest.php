<?php

namespace Anagrafica\Tests\Models;

use Anagrafica\Application\Entities\CodiceAteco;
use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\DocumentoIdentita;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Nazione;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Recapito;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\ContoCorrenteModel;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\DocumentoIdentitaModel;
use Anagrafica\Application\Models\ImmobileModel;
use Anagrafica\Application\Models\IndirizzoModel;
use Anagrafica\Application\Models\LegameModel;
use Anagrafica\Application\Models\NazioneModel;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Models\RecapitoModel;
use Anagrafica\Application\Models\SoggettoModel;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use SismaFramework\Orm\CustomTypes\SismaDate;

class ModelsTest extends SuiteTestCase
{

    public function testSoggettoModel(): void
    {
        $studio = $this->creaStudio(1);
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 10;
        $soggetto->studio = $studio;
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';

        $model = new SoggettoModel($this->dataMapper);

        $this->testAdapter->accodaSelect(new TestResultSet([$soggetto]));
        $risultati = $model->cerca($studio, 'ROSSI', TipoSoggetto::personaFisica, TipoAttivita::impresaIndividuale, true, 0, 25);
        $this->assertCount(1, $risultati);

        $this->testAdapter->accodaSelect(TestResultSet::conteggio(3));
        $this->assertSame(3, $model->contaRisultati($studio, '  ', null, null, false));

        $this->testAdapter->accodaSelect(new TestResultSet([$soggetto]));
        $deceduti = $model->cercaDeceduti($studio, 'ROSSI', 0, 10);
        $this->assertCount(1, $deceduti);

        $this->testAdapter->accodaSelect(TestResultSet::conteggio(2));
        $this->assertSame(2, $model->contaDeceduti($studio, null));

        $this->testAdapter->accodaSelect(new TestResultSet([$soggetto]));
        $this->assertTrue($model->esisteCodiceFiscale($studio, 'RSSMRA80A01H501U'));

        $this->testAdapter->accodaSelect(new TestResultSet([$soggetto]));
        $this->assertFalse($model->esisteCodiceFiscale($studio, 'RSSMRA80A01H501U', $soggetto));
    }

    public function testImmobileModel(): void
    {
        $studio = $this->creaStudio(1);
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->codiceCatastale = 'H501';

        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 5;
        $immobile->studio = $studio;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = $comune;
        $immobile->foglio = '10';
        $immobile->particella = '20';
        $immobile->subalterno = '1';

        $model = new ImmobileModel($this->dataMapper);

        $this->testAdapter->accodaSelect(new TestResultSet([$immobile]));
        $this->assertCount(1, $model->cerca($studio, '10', TipoCatasto::fabbricati, 0, 25));

        $this->testAdapter->accodaSelect(TestResultSet::conteggio(4));
        $this->assertSame(4, $model->contaRisultati($studio, '', null));

        $nuovo = new Immobile($this->dataMapper);
        $nuovo->studio = $studio;
        $nuovo->tipoCatasto = TipoCatasto::fabbricati;
        $nuovo->comune = $comune;
        $nuovo->foglio = '10';
        $nuovo->particella = '20';
        $nuovo->subalterno = '1';

        $this->testAdapter->accodaSelect(new TestResultSet([$immobile]));
        $this->assertSame($immobile, $model->trovaPerChiaveCatastale($studio, $nuovo));

        $this->testAdapter->accodaSelect(new TestResultSet([$immobile]));
        $this->assertNull($model->trovaPerChiaveCatastale($studio, $immobile));
    }

    public function testComuneNazioneECodiceAtecoModels(): void
    {
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'Roma';
        $comune->codiceCatastale = 'H501';
        $comune->validoDal = new SismaDate('1900-01-01');
        $comune->validoAl = null;

        $comuneModel = new ComuneModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$comune]));
        $this->assertCount(1, $comuneModel->getEntityCollection('Roma'));

        $this->testAdapter->accodaSelect(new TestResultSet([$comune]));
        $this->assertSame($comune, $comuneModel->getByCodice('h501'));

        $this->testAdapter->accodaSelect(new TestResultSet([$comune]));
        $this->assertSame($comune, $comuneModel->getByCodiceAl('H501', new \DateTimeImmutable('2020-01-01')));

        $comuneCessato = new Comune($this->dataMapper);
        $comuneCessato->id = 2;
        $comuneCessato->codiceCatastale = 'A001';
        $comuneCessato->validoDal = new SismaDate('1900-01-01');
        $comuneCessato->validoAl = new SismaDate('1950-12-31');
        $this->testAdapter->accodaSelect(new TestResultSet([$comuneCessato]));
        $this->assertNull($comuneModel->getByCodiceAl('A001', new \DateTimeImmutable('2020-01-01')));

        $italia = new Nazione($this->dataMapper);
        $italia->id = 1;
        $italia->denominazione = 'Italia';
        $italia->codiceIso = 'IT';
        $nazioneModel = new NazioneModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$italia]));
        $this->assertCount(1, $nazioneModel->getEntityCollection('Ita'));
        $this->testAdapter->accodaSelect(new TestResultSet([$italia]));
        $this->assertSame($italia, $nazioneModel->getItalia());
        $this->testAdapter->accodaSelect(new TestResultSet([$italia]));
        $this->assertSame($italia, $nazioneModel->getByCodice('z100'));

        $ateco = new CodiceAteco($this->dataMapper);
        $ateco->id = 10;
        $ateco->classificazione = ClassificazioneAteco::ateco2022;
        $ateco->codice = '69.20.11';
        $ateco->codicePadre = '69.20';
        $ateco->codiceRaccordo = '69.20.01';

        $atecoPadre = new CodiceAteco($this->dataMapper);
        $atecoPadre->id = 9;
        $atecoPadre->classificazione = ClassificazioneAteco::ateco2022;
        $atecoPadre->codice = '69.20';
        $atecoPadre->codicePadre = null;

        $ateco2025 = new CodiceAteco($this->dataMapper);
        $ateco2025->id = 20;
        $ateco2025->classificazione = ClassificazioneAteco::ateco2025;
        $ateco2025->codice = '69.20.01';

        $atecoModel = new CodiceAtecoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$ateco2025]));
        $this->assertCount(1, $atecoModel->cercaSottocategorie(ClassificazioneAteco::ateco2025, '69', 10));

        $this->testAdapter->accodaSelect(new TestResultSet([$ateco2025]));
        $this->assertSame($ateco2025, $atecoModel->getRaccordo($ateco));

        $atecoSenzaRaccordo = new CodiceAteco($this->dataMapper);
        $atecoSenzaRaccordo->codiceRaccordo = null;
        $this->assertNull($atecoModel->getRaccordo($atecoSenzaRaccordo));

        $this->testAdapter->accodaSelect(new TestResultSet([$atecoPadre]));
        $gerarchia = $atecoModel->getGerarchia($ateco);
        $this->assertCount(1, $gerarchia);
        $this->assertSame($atecoPadre, $gerarchia[0]);
    }

    public function testAltriModelsAnagrafica(): void
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 1;
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 2;

        // DatiFiscaliModel
        $df = new DatiFiscali($this->dataMapper);
        $df->id = 1;
        $dfModel = new DatiFiscaliModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$df]));
        $this->assertCount(1, $dfModel->getStorico($soggetto));
        $this->testAdapter->accodaSelect(new TestResultSet([$df]));
        $this->assertSame($df, $dfModel->getVigenteAl($soggetto, new SismaDate('2025-01-01')));

        // DatiCatastaliModel
        $dc = new DatiCatastali($this->dataMapper);
        $dc->id = 1;
        $dc->validoDal = new SismaDate('2020-01-01');
        $dcModel = new DatiCatastaliModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$dc]));
        $this->assertSame($dc, $dcModel->getVigenteAl($immobile, new \DateTimeImmutable('2025-01-01')));
        $this->testAdapter->accodaSelect(new TestResultSet([$dc]));
        $this->assertNull($dcModel->getVigenteAl($immobile, new \DateTimeImmutable('2019-01-01')));
        // prima del primo classamento: il primo successivo (presunto); alla data: quello vigente
        $dcPrecedente = new DatiCatastali($this->dataMapper);
        $dcPrecedente->id = 2;
        $dcPrecedente->validoDal = new SismaDate('2018-06-01');
        $dcPrecedente->validoAl = new SismaDate('2019-12-31');
        $this->testAdapter->accodaSelect(new TestResultSet([$dc, $dcPrecedente]));
        $this->assertSame($dcPrecedente, $dcModel->getVigenteOSuccessivo($immobile, new \DateTimeImmutable('2010-01-01')));
        $this->testAdapter->accodaSelect(new TestResultSet([$dc, $dcPrecedente]));
        $this->assertSame($dc, $dcModel->getVigenteOSuccessivo($immobile, new \DateTimeImmutable('2025-01-01')));
        $this->testAdapter->accodaSelect(new TestResultSet([]));
        $this->assertNull($dcModel->getVigenteOSuccessivo($immobile, new \DateTimeImmutable('2025-01-01')));

        // IndirizzoModel
        $ind = new Indirizzo($this->dataMapper);
        $ind->id = 1;
        $ind->tipoIndirizzo = TipoIndirizzo::residenza;
        $ind->validoDal = new SismaDate('2020-01-01');
        $indModel = new IndirizzoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$ind]));
        $vigenti = $indModel->getVigentiAl($soggetto, new \DateTimeImmutable('2025-01-01'));
        $this->assertArrayHasKey('RES', $vigenti);

        // LegameModel
        $legame = new Legame($this->dataMapper);
        $legame->id = 1;
        $legameModel = new LegameModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$legame]));
        $this->assertCount(1, $legameModel->getCollegati($soggetto));
        $this->testAdapter->accodaSelect(new TestResultSet([$legame]));
        $this->assertCount(1, $legameModel->getIncarichi($soggetto));

        // PossessoModel
        $possesso = new Possesso($this->dataMapper);
        $possesso->id = 1;
        $possesso->soggetto = $soggetto;
        $possesso->immobile = $immobile;
        $possesso->titolo = TitoloPossesso::proprieta;
        $possesso->validoDal = new SismaDate('2020-01-01');
        $possessoModel = new PossessoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$possesso]));
        $this->assertCount(1, $possessoModel->getStoricoSoggetto($soggetto));
        $this->testAdapter->accodaSelect(new TestResultSet([$possesso]));
        $this->assertCount(1, $possessoModel->getVigentiAl($immobile, new \DateTimeImmutable('2025-01-01')));
        $this->testAdapter->accodaSelect(new TestResultSet([$possesso]));
        $this->assertCount(1, $possessoModel->getAmbito($possesso));

        // ContoCorrenteModel
        $conto = new ContoCorrente($this->dataMapper);
        $conto->id = 1;
        $ccModel = new ContoCorrenteModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([$conto]));
        $this->assertCount(1, $ccModel->getElenco($soggetto));
        $this->testAdapter->accodaSelect(new TestResultSet([$conto]));
        $this->assertSame($conto, $ccModel->getPredefinito($soggetto));

        // DocumentoIdentitaModel & RecapitoModel
        $docModel = new DocumentoIdentitaModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([new DocumentoIdentita($this->dataMapper)]));
        $this->assertCount(1, $docModel->getElenco($soggetto));

        $recModel = new RecapitoModel($this->dataMapper);
        $this->testAdapter->accodaSelect(new TestResultSet([new Recapito($this->dataMapper)]));
        $this->assertCount(1, $recModel->getElenco($soggetto));
    }
}
