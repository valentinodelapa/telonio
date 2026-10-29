<?php

namespace Deleghe\Tests\Services;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Telonio\Tests\SuiteTestCase;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\PianoRateazione;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Services\CalcoloRateazione;
use Deleghe\Application\Services\RateazioneService;
use SismaFramework\Orm\CustomTypes\SismaDate;

class CalcoloRateazioneTest extends SuiteTestCase
{

    private int $prossimoId = 100;

    private function creaCodice(string $codice, SezioneCodiceTributo $sezione, string $formatoRateazione = 'NNRR', ?string $tipoTributo = null): CodiceTributo
    {
        $codiceTributo = new CodiceTributo($this->dataMapper);
        $codiceTributo->id = $this->prossimoId++;
        $codiceTributo->sezione = $sezione;
        $codiceTributo->codice = $codice;
        $codiceTributo->descrizione = 'Codice ' . $codice;
        $codiceTributo->tipoTributo = $tipoTributo;
        $codiceTributo->modalita = ModalitaUtilizzo::debito;
        $codiceTributo->formatoRateazione = $formatoRateazione;
        $codiceTributo->annoRichiesto = true;
        return $codiceTributo;
    }

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

    private function creaVersamento(SezioneF24 $sezione, ?CodiceTributo $codice, float $importo, string $scadenza = '2026-06-30'): Versamento
    {
        $versamento = new Versamento($this->dataMapper);
        $versamento->id = $this->prossimoId++;
        $versamento->studio = $this->studioPredefinito;
        $versamento->soggetto = $this->creaSoggetto();
        $versamento->scadenza = new SismaDate($scadenza);
        $versamento->sezione = $sezione;
        $versamento->codiceTributo = $codice;
        $versamento->annoRiferimento = $codice === null ? null : 2025;
        $versamento->importoDebito = $importo;
        return $versamento;
    }

    public function testGiorniCommercialiEPercentualiDelleTabelleUfficiali(): void
    {
        // metodo commerciale (30/360) dal giorno dopo la prima rata alla scadenza nominale della rata
        $this->assertSame(16, CalcoloRateazione::giorniCommerciali(new SismaDate('2021-06-30'), new SismaDate('2021-07-16')));
        $this->assertSame(15, CalcoloRateazione::giorniCommerciali(new SismaDate('2024-07-01'), new SismaDate('2024-07-16')));
        $this->assertSame(46, CalcoloRateazione::giorniCommerciali(new SismaDate('2021-06-30'), new SismaDate('2021-08-16')));
        $this->assertSame(106, CalcoloRateazione::giorniCommerciali(new SismaDate('2021-06-30'), new SismaDate('2021-10-16')));
        $this->assertSame(16, CalcoloRateazione::giorniCommerciali(new SismaDate('2021-07-31'), new SismaDate('2021-08-16')));

        // tabelle delle istruzioni: frazione fino alla seconda rata, poi 0,33% al mese
        $prima = new SismaDate('2021-06-30');
        $seconda = new SismaDate('2021-07-16');
        $attese = ['2021-07-16' => 0.18, '2021-08-16' => 0.51, '2021-09-16' => 0.84, '2021-10-16' => 1.17, '2021-11-16' => 1.50];
        foreach ($attese as $data => $percentuale) {
            $this->assertSame($percentuale, CalcoloRateazione::getPercentualeInteressi($prima, $seconda, new SismaDate($data)), $data);
        }
        // 2024, prima rata 01/07 (30/06 domenica): 0,17 - 0,50 - 0,83 - 1,16
        $prima = new SismaDate('2024-07-01');
        $seconda = new SismaDate('2024-07-16');
        $attese = ['2024-07-16' => 0.17, '2024-08-16' => 0.50, '2024-09-16' => 0.83, '2024-10-16' => 1.16];
        foreach ($attese as $data => $percentuale) {
            $this->assertSame($percentuale, CalcoloRateazione::getPercentualeInteressi($prima, $seconda, new SismaDate($data)), $data);
        }
        // versamento: primo giorno lavorativo, in agosto il 20
        $this->assertSame('2026-08-20', CalcoloRateazione::getDataVersamento(new SismaDate('2026-08-16'))->format('Y-m-d'));
        $this->assertSame('2026-11-16', CalcoloRateazione::getDataVersamento(new SismaDate('2026-11-16'))->format('Y-m-d'));
        $this->assertSame('2027-01-18', CalcoloRateazione::getDataVersamento(new SismaDate('2027-01-16'))->format('Y-m-d'));
    }

    public function testNumeroMassimoDateProposteRipartizioneEVerificaDate(): void
    {
        $this->assertSame(7, CalcoloRateazione::getMassimoRate(new SismaDate('2026-06-30')));
        $this->assertSame(6, CalcoloRateazione::getMassimoRate(new SismaDate('2026-07-30')));
        $this->assertSame(1, CalcoloRateazione::getMassimoRate(new SismaDate('2026-12-16')));

        $date = CalcoloRateazione::getDateProposte(new SismaDate('2026-06-30'), 9);
        $this->assertSame(['2026-06-30', '2026-07-16', '2026-08-16', '2026-09-16', '2026-10-16', '2026-11-16', '2026-12-16'],
                array_map(fn($data) => $data->format('Y-m-d'), $date));

        $this->assertSame([33335, 33333, 33333], CalcoloRateazione::ripartisci(100001, 3));
        $this->assertSame([50000, 50000], CalcoloRateazione::ripartisci(100000, 2));

        $scadenza = new SismaDate('2026-06-30');
        $this->assertSame([], CalcoloRateazione::verificaDate($scadenza, [new SismaDate('2026-06-30'), new SismaDate('2026-07-16'), new SismaDate('2026-12-16')]));
        $this->assertSame(['Indicare almeno due rate.'], CalcoloRateazione::verificaDate($scadenza, [new SismaDate('2026-06-30')]));
        $errori = CalcoloRateazione::verificaDate($scadenza, [new SismaDate('2026-07-01'), new SismaDate('2026-07-16'), new SismaDate('2026-07-20'), new SismaDate('2026-07-10'), new SismaDate('2026-12-17')]);
        $this->assertContains('La prima rata si versa alla scadenza dei versamenti (30/06/2026).', $errori);
        $this->assertContains('La rata 4 deve seguire la precedente.', $errori);
        $this->assertContains('La rata 5 supera il 16/12/2026, termine del pagamento rateale.', $errori);
        $this->assertContains('Le rate sono mensili: al più una rata per mese.', $errori);
    }

    public function testVersamentiRateizzabiliEInteressi(): void
    {
        $this->assertTrue(CalcoloRateazione::isRateizzabile($this->creaVersamento(SezioneF24::erario, $this->creaCodice('4001', SezioneCodiceTributo::erario), 100)));
        // secondo acconto (rateazione 0000) e IMU non rientrano nel pagamento rateale dell'art. 20
        $this->assertFalse(CalcoloRateazione::isRateizzabile($this->creaVersamento(SezioneF24::erario, $this->creaCodice('4034', SezioneCodiceTributo::erario, '0000'), 100)));
        $this->assertFalse(CalcoloRateazione::isRateizzabile($this->creaVersamento(SezioneF24::tributiLocali, $this->creaCodice('3918', SezioneCodiceTributo::tributiLocali, 'NNRR', 'IMU'), 100)));
        $inps = $this->creaVersamento(SezioneF24::inps, null, 100);
        $inps->causale = 'AP';
        $this->assertTrue(CalcoloRateazione::isRateizzabile($inps));
        $inps->causale = 'DM10';
        $this->assertFalse(CalcoloRateazione::isRateizzabile($inps));
        $credito = $this->creaVersamento(SezioneF24::erario, $this->creaCodice('4001', SezioneCodiceTributo::erario), 0);
        $credito->importoCredito = 100;
        $this->assertFalse(CalcoloRateazione::isRateizzabile($credito));

        $interessi = $this->creaVersamento(SezioneF24::erario, $this->creaCodice('1668', SezioneCodiceTributo::erario, '0000'), 1);
        $interessi->descrizione = 'Interessi rata 3/7';
        $this->assertTrue(CalcoloRateazione::isInteresse($interessi));
        $this->assertSame(3, CalcoloRateazione::getNumeroRata($interessi));
        $inps->causale = 'API';
        $this->assertTrue(CalcoloRateazione::isInteresse($inps));
    }

    public function testCalcolaQuoteEInteressiPerSezione(): void
    {
        $codiciInteressi = [
            'E1668' => $this->creaCodice('1668', SezioneCodiceTributo::erario, '0000'),
            'R3805' => $this->creaCodice('3805', SezioneCodiceTributo::regioni, '0000'),
        ];
        $calcolo = new CalcoloRateazione($this->dataMapper, fn(SezioneF24 $sezione, string $codice) => $codiciInteressi[$sezione->getSezioneCodici()->value . $codice] ?? null);

        $irpef = $this->creaVersamento(SezioneF24::erario, $this->creaCodice('4001', SezioneCodiceTributo::erario), 900.00);
        $irpef->descrizione = 'Saldo IRPEF 2025';
        $addizionale = $this->creaVersamento(SezioneF24::regioni, $this->creaCodice('3801', SezioneCodiceTributo::regioni), 300.00);
        $addizionale->codiceEnte = '08';
        $inps = $this->creaVersamento(SezioneF24::inps, null, 600.00);
        $inps->causale = 'AP';
        $inps->codiceSede = '7000';
        $inps->posizione = '12345678901234567';
        $inps->periodoDa = '01/2025';
        $inps->periodoA = '12/2025';

        $date = CalcoloRateazione::getDateProposte(new SismaDate('2026-06-30'), 3);
        $rate = $calcolo->calcola([$irpef, $addizionale, $inps], $date);

        $sintesi = array_map(fn(Versamento $rata) => [$rata->scadenza->format('Y-m-d'), $rata->getCodice(), $rata->rateazione, $rata->codiceEnte, $rata->importoDebito], $rate);
        $this->assertSame([
            // prima rata, senza interessi
            ['2026-06-30', '4001', '0103', null, 300.0],
            ['2026-06-30', '3801', '0103', '08', 100.0],
            // INPS: tutte le rate con la causale del pagamento rateale
            ['2026-06-30', 'APR', null, null, 200.0],
            // seconda rata: interessi 0,18%
            ['2026-07-16', '4001', '0203', null, 300.0],
            ['2026-07-16', '3801', '0203', '08', 100.0],
            ['2026-07-16', 'APR', null, null, 200.0],
            ['2026-07-16', '1668', null, null, 0.54],
            ['2026-07-16', '3805', null, '08', 0.18],
            ['2026-07-16', 'API', null, null, 0.36],
            // terza rata: scadenza nominale 16/08, versamento il 20/08 (proroga di agosto); interessi 0,51%
            ['2026-08-20', '4001', '0303', null, 300.0],
            ['2026-08-20', '3801', '0303', '08', 100.0],
            ['2026-08-20', 'APR', null, null, 200.0],
            ['2026-08-20', '1668', null, null, 1.53],
            ['2026-08-20', '3805', null, '08', 0.51],
            ['2026-08-20', 'API', null, null, 1.02],
        ], $sintesi);
        $this->assertSame('Rata 2/3 Saldo IRPEF 2025', $rate[3]->descrizione);
        $this->assertSame('12345678901234567', $rate[8]->posizione);
        $this->assertSame(2025, $rate[6]->annoRiferimento);

        $senzaCodici = new CalcoloRateazione($this->dataMapper, fn() => null);
        $this->expectException(\InvalidArgumentException::class);
        $senzaCodici->calcola([$irpef], $date);
    }

    public function testInteressiDellaRataPerIlRavvedimento(): void
    {
        $codiceInteressi = $this->creaCodice('1668', SezioneCodiceTributo::erario, '0000');
        $crea = function (string $descrizione, ?Delega $delega) use ($codiceInteressi): Versamento {
            $versamento = $this->creaVersamento(SezioneF24::erario, $codiceInteressi, 1.0);
            $versamento->descrizione = $descrizione;
            $versamento->origine = PianoRateazione::ORIGINE;
            $versamento->riferimentoOrigine = 'rateazione-1';
            $versamento->delega = $delega;
            return $versamento;
        };
        $delega = new Delega($this->dataMapper);
        $delega->id = 5;
        $quota = $this->creaVersamento(SezioneF24::erario, $this->creaCodice('4001', SezioneCodiceTributo::erario), 225.0);
        $quota->descrizione = 'Rata 2/4 Saldo IRPEF';
        $quota->origine = PianoRateazione::ORIGINE;
        $quota->riferimentoOrigine = 'rateazione-1';
        $rata2 = $crea('Interessi rata 2/4', null);
        $rata3 = $crea('Interessi rata 3/4', null);
        // stessa rata, altro rigo di interessi (anno di riferimento diverso): resta dov'è
        $altroAnno = $crea('Interessi rata 2/4', null);
        $altroAnno->annoRiferimento = 2026;
        $rata2InDelega = $crea('Interessi rata 2/4', $delega);

        $service = new RateazioneService($this->dataMapper);
        // da versare: solo gli interessi della stessa rata non ancora in una delega
        $this->testAdapter->accodaSelect([$quota, $rata2, $rata3, $altroAnno, $rata2InDelega]);
        $this->assertSame([$rata2], $service->getInteressiRata($quota, null));
        // già nella delega del ravvedimento: quelli da riportare alla data della rata
        $this->testAdapter->accodaSelect([$quota, $rata2, $rata3, $altroAnno, $rata2InDelega]);
        $this->assertSame([$rata2InDelega], $service->getInteressiRata($quota, $delega));
        // gli interessi non hanno a loro volta interessi, e i versamenti non rateizzati nemmeno
        $this->assertSame([], $service->getInteressiRata($rata2, null));
        $quota->origine = null;
        $this->assertSame([], $service->getInteressiRata($quota, null));
    }

    public function testCreazioneRifiutaVersamentiNonValidi(): void
    {
        $service = new RateazioneService($this->dataMapper);
        $soggetto = $this->creaSoggetto();
        $date = CalcoloRateazione::getDateProposte(new SismaDate('2026-06-30'), 3);
        $irpef = $this->creaVersamento(SezioneF24::erario, $this->creaCodice('4001', SezioneCodiceTributo::erario), 900.00);
        $secondoAcconto = $this->creaVersamento(SezioneF24::erario, $this->creaCodice('4034', SezioneCodiceTributo::erario, '0000'), 500.00);
        $altraData = $this->creaVersamento(SezioneF24::erario, $this->creaCodice('4033', SezioneCodiceTributo::erario), 500.00, '2026-07-30');

        $casi = [
            [[], 'Scegliere almeno un versamento da rateizzare.'],
            [[$secondoAcconto], 'Il versamento 4034 non è rateizzabile (art. 20 D.Lgs. 241/1997: saldo e primo acconto; secondo acconto solo con una norma straordinaria).'],
            [[$irpef, $altraData], 'I versamenti rateizzati insieme devono avere la stessa scadenza.'],
        ];
        foreach ($casi as [$originali, $messaggio]) {
            try {
                $service->crea($soggetto, $originali, $date);
                $this->fail('Attesa eccezione: ' . $messaggio);
            } catch (\InvalidArgumentException $errore) {
                $this->assertSame($messaggio, $errore->getMessage());
            }
        }
    }
}
