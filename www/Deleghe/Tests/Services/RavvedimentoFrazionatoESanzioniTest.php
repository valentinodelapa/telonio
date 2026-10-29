<?php

namespace Deleghe\Tests\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\PianoRateazione;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\RavvedimentoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use Deleghe\Application\Services\CalcoloRateazione;
use SismaFramework\Orm\CustomTypes\SismaDate;

class RavvedimentoFrazionatoESanzioniTest extends SuiteTestCase
{

    private function creaSoggetto(): Soggetto
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 1;
        $soggetto->studio = $this->studioPredefinito;
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $soggetto->nominativo = 'Rossi Mario';
        return $soggetto;
    }

    private function creaVersamento(?int $id, float $importo, string $scadenza = '2026-06-30'): Versamento
    {
        $codice = new CodiceTributo($this->dataMapper);
        $codice->id = 4001;
        $codice->sezione = SezioneCodiceTributo::erario;
        $codice->codice = '4001';
        $codice->descrizione = 'IRPEF saldo';
        $codice->modalita = ModalitaUtilizzo::debito;
        $codice->formatoRateazione = 'NNRR';
        $codice->annoRichiesto = true;
        $versamento = new Versamento($this->dataMapper);
        if ($id !== null) {
            $versamento->id = $id;
        }
        $versamento->studio = $this->studioPredefinito;
        $versamento->soggetto = $this->creaSoggetto();
        $versamento->scadenza = new SismaDate($scadenza);
        $versamento->sezione = SezioneF24::erario;
        $versamento->codiceTributo = $codice;
        $versamento->rateazione = '0101';
        $versamento->annoRiferimento = 2025;
        $versamento->importoDebito = $importo;
        return $versamento;
    }

    private function creaRavvedimento(int $id, Versamento $omesso, float $importo): Ravvedimento
    {
        $ravvedimento = new Ravvedimento($this->dataMapper);
        $ravvedimento->id = $id;
        $ravvedimento->versamentoOmesso = $omesso;
        $ravvedimento->importoTributo = $importo;
        return $ravvedimento;
    }

    public function testResiduoDelRavvedimentoFrazionato(): void
    {
        $omesso = $this->creaVersamento(10, 1000.00);
        $model = new RavvedimentoModel($this->dataMapper);

        // due frazioni regolarizzate: 400 + 350
        $this->testAdapter->accodaSelect([$this->creaRavvedimento(1, $omesso, 400.00), $this->creaRavvedimento(2, $omesso, 350.00)]);
        $this->assertSame(75000, $model->getImportoRavveduto($omesso));
        $this->testAdapter->accodaSelect([$this->creaRavvedimento(1, $omesso, 400.00), $this->creaRavvedimento(2, $omesso, 350.00)]);
        $this->assertSame(25000, $model->getResiduo($omesso));
        // modificando la seconda frazione, il residuo disponibile la esclude
        $this->testAdapter->accodaSelect([$this->creaRavvedimento(1, $omesso, 400.00), $this->creaRavvedimento(2, $omesso, 350.00)]);
        $this->assertSame(60000, $model->getResiduo($omesso, 2));
        // nessun ravvedimento: residuo pari all'importo
        $this->testAdapter->accodaSelect([]);
        $this->assertSame(100000, $model->getResiduo($omesso));
    }

    public function testChiaveInteressiInpsUgualePerQuoteEInteressi(): void
    {
        $crea = function (string $causale, ?string $posizione = '49123456789000000'): Versamento {
            $versamento = new Versamento($this->dataMapper);
            $versamento->sezione = SezioneF24::inps;
            $versamento->causale = $causale;
            $versamento->codiceSede = '4900';
            $versamento->posizione = $posizione;
            $versamento->periodoDa = '01/2025';
            $versamento->periodoA = '12/2025';
            return $versamento;
        };
        // contributo originale, quota della rata e interessi appartengono allo stesso rigo di interessi
        $chiave = CalcoloRateazione::getChiaveInteressi($crea('AP'));
        $this->assertSame($chiave, CalcoloRateazione::getChiaveInteressi($crea('APR')));
        $this->assertSame($chiave, CalcoloRateazione::getChiaveInteressi($crea('API')));
        // commercianti e altre posizioni hanno righi distinti
        $this->assertNotSame($chiave, CalcoloRateazione::getChiaveInteressi($crea('CPR')));
        $this->assertNotSame($chiave, CalcoloRateazione::getChiaveInteressi($crea('APR', '49999999999999999')));
    }

    public function testRicalcoloNonSostituisceIlVersamentoPagatoInRitardoConLeSanzioniCivili(): void
    {
        $soggetto = $this->creaSoggetto();
        $delega = new Delega($this->dataMapper);
        $delega->id = 5;
        $delega->studio = $this->studioPredefinito;
        $delega->soggetto = $soggetto;
        $delega->scadenza = new SismaDate('2026-11-16');
        $delega->stato = StatoDelega::bozza;

        // rata del 16/07 spostata nella delega del 16/11 con le sanzioni civili
        $rata = $this->creaVersamento(20, 300.00, '2026-11-16');
        $rata->delega = $delega;
        $rata->origine = PianoRateazione::ORIGINE;
        $rata->riferimentoOrigine = 'rateazione-1';
        $sanzione = new SanzioneCivile($this->dataMapper);
        $sanzione->id = 7;
        $sanzione->delega = $delega;
        $sanzione->versamentoTardivo = $rata;
        $sanzione->scadenzaOriginale = new SismaDate('2026-07-16');

        // interessi della stessa rata, spostati con la quota nella delega
        $interessi = $this->creaVersamento(21, 0.54, '2026-11-16');
        $interessi->delega = $delega;
        $interessi->origine = PianoRateazione::ORIGINE;
        $interessi->riferimentoOrigine = 'rateazione-1';
        $interessi->descrizione = 'Interessi rata 2/3';

        $ricalcolata = $this->creaVersamento(null, 350.00, '2026-07-16');

        // rimuoviPrecedenti: getGenerati, poi getByVersamentoOmesso e getByVersamentoTardivo per ciascuno
        $this->testAdapter->accodaSelect(new TestResultSet([$rata, $interessi]), new TestResultSet([]), new TestResultSet([$sanzione]), new TestResultSet([]), new TestResultSet([]));
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, PianoRateazione::ORIGINE, 'rateazione-1', [$ricalcolata]);

        // la scadenza originale resta bloccata con la quota e i suoi interessi: nessun nuovo versamento, differenza segnalata
        $this->assertSame([], $esito->versamenti);
        $this->assertSame([
            'Versamento del 16/07/2026 già versato in ritardo con le sanzioni civili nella delega del 16/11/2026: registrato € 300,54, ricalcolato € 350,00.',
        ], $esito->differenze);
    }
}
