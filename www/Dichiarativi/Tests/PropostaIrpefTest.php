<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\InterventoEdilizio;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoDetrazioneEdilizia;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaBonusEdilizi;
use Dichiarativi\Application\Services\Redditi\CalcoloCreditoEstero;
use Dichiarativi\Application\Services\Redditi\CalcoloIrpef;
use Dichiarativi\Application\Services\Redditi\PropostaCrediti;
use Dichiarativi\Application\Services\Redditi\PropostaImpresa;
use Dichiarativi\Application\Services\Redditi\PropostaIrpef;
use Dichiarativi\Application\Services\Redditi\PropostaPartecipazioni;
use Dichiarativi\Application\Services\TabelleRiferimento\TabelleDichiarativi;
use Telonio\Tests\SuiteTestCase;
use Telonio\Tests\TestResultSet;

/**
 * Quadri RN e RX dai redditi di lavoro dipendente (quadro RC) e dal
 * prospetto dei familiari a carico, con il catalogo reale del modello
 * Redditi PF 2026.
 */
class PropostaIrpefTest extends SuiteTestCase
{

    /** @var array<string, CampoModello>|null */
    private static ?array $catalogo = null;

    private function getCatalogo(): array
    {
        if (self::$catalogo === null) {
            self::$catalogo = [];
            foreach ((new TabelleDichiarativi())->leggi()['campi'] as $riga) {
                if (($riga['modello'] !== 'RPF') || ($riga['anno'] !== 2026)) {
                    continue;
                }
                $campo = new CampoModello($this->dataMapper);
                $campo->modello = ModelloDichiarativo::redditiPf;
                $campo->anno = 2026;
                $campo->codice = $riga['codice'];
                $campo->quadro = $riga['quadro'];
                $campo->descrizione = $riga['descrizione'];
                $campo->formato = $riga['formato'] === null ? null : FormatoCampo::from($riga['formato']);
                self::$catalogo[$campo->codice] = $campo;
            }
        }
        return self::$catalogo;
    }

    private function creaQuadri(array $valori): QuadriDichiarazione
    {
        $soggetto = new Soggetto($this->dataMapper);
        $soggetto->id = 100;
        $soggetto->studio = $this->creaStudio(1);
        $soggetto->tipoSoggetto = TipoSoggetto::personaFisica;
        $soggetto->nominativo = 'ROSSI MARIO';
        $soggetto->codiceFiscale = 'RSSMRA80A01H501U';
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->studio = $soggetto->studio;
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::redditiPf;
        $dichiarazione->anno = 2025;
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper, $this->getCatalogo());
        foreach ($valori as $codice => $valore) {
            $quadri->imposta($codice, $valore);
        }
        return $quadri;
    }

    public function testCreditoImposteEstere(): void
    {
        $quadri = $this->creaQuadri(['RN001005' => 40000, 'RN005001' => 10000, 'RN026001' => 8000,
            'CE001001' => '071', 'CE001002' => 2025, 'CE001003' => 1000, 'CE001004' => 350]);
        $this->assertSame([], CalcoloCreditoEstero::proponi($quadri, 2025));
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(40000, $q('CE001005'));
        // quota di imposta lorda: 1.000 / 40.000 x 10.000 = 250, sotto l'imposta estera
        $this->assertSame(250, $q('CE001010'));
        $this->assertSame(250, $q('CE001011'));
        $this->assertSame(2025, $q('CE004001'));
        $this->assertSame(8000, $q('CE004003'));
        $this->assertSame(250, $q('CE004004'));
        $this->assertSame(250, $q('CE026001'));
        $this->assertSame(250, $q('RN029002'));
        // imposta estera oltre il 43 per cento del reddito estero
        $quadri->imposta('CE001004', 500);
        $this->assertCount(1, CalcoloCreditoEstero::proponi($quadri, 2025));
    }

    public function testCreditiDelQuadroCr(): void
    {
        // residui della dichiarazione dell'anno prima
        $precedenti = $this->creaQuadri(['RN047011' => 1200, 'RN047039' => 300]);
        $quadri = $this->creaQuadri([]);
        PropostaCrediti::proponiResidui($quadri, $precedenti);
        $this->assertSame(1200, $quadri->getImporto('CR007001', 1));
        $this->assertSame(3, $quadri->getImporto('CR031001', 1));
        $this->assertSame(300, $quadri->getImporto('CR031003', 1));
        // utilizzo fino all'imposta lorda meno le detrazioni: prima casa, poi mediazioni
        $quadri = $this->creaQuadri(['CR007001' => 3000, 'CR031001' => 16, 'CR031002' => 500, 'RN005001' => 2000]);
        PropostaCrediti::proponiUtilizzo($quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(3000, $q('RN024001'));
        $this->assertSame(1000, $q('RN047011'));
        $this->assertSame(500, $q('RN024004'));
        $this->assertSame(500, $q('RN047014'));
        $this->assertSame(3500, $q('RN025001'));
    }

    public function testScaglioniPerAnno(): void
    {
        $this->assertEqualsWithDelta(7720.0, CalcoloIrpef::impostaLorda(30000, 2021), 0.001);
        $this->assertEqualsWithDelta(7400.0, CalcoloIrpef::impostaLorda(30000, 2023), 0.001);
        $this->assertEqualsWithDelta(7140.0, CalcoloIrpef::impostaLorda(30000, 2025), 0.001);
    }

    public function testTotaliPartecipazioni(): void
    {
        // righi RH inseriti a mano: impresa in utile e in perdita, associazione tra professionisti con ritenute, società semplice
        $quadri = $this->creaQuadri(['RH001001' => '09990012008', 'RH001002' => 1, 'RH001003' => 50, 'RH001004' => 9000,
            'RH002001' => '01234560785', 'RH002002' => 1, 'RH002003' => 20, 'RH002004' => -2000,
            'RH003001' => '01234980587', 'RH003002' => 2, 'RH003003' => 30, 'RH003004' => 3000, 'RH003009' => 600,
            'RH004001' => '09990010994', 'RH004002' => 4, 'RH004003' => 10, 'RH004004' => 1000]);
        (new PropostaPartecipazioni($this->dataMapper))->proponi($quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(9000, $q('RH007002'));
        $this->assertSame(2000, $q('RH008001'));
        $this->assertSame(7000, $q('RH009002'));
        $this->assertSame(7000, $q('RH014001'));
        $this->assertSame(3000, $q('RH017001'));
        $this->assertSame(1000, $q('RH018001'));
        $this->assertSame(600, $q('RH019001'));
        // nel quadro RN: redditi di partecipazione e ritenute
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $this->assertSame(11000, $q('RN001005'));
        $this->assertSame(600, $q('RN033004'));
    }

    public function testLavoratoreDipendente(): void
    {
        // RC a mano (nessuna Certificazione Unica in archivio): 30.000 euro a tempo indeterminato per tutto l'anno
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 30000, 'RC005006' => 30000, 'RC006001' => 365,
            'RC010001' => 6000, 'RC014005' => 1, 'RC014006' => 30000, 'RC014008' => 365, 'RC014009' => 2]);
        // certificazioni uniche, indirizzi (domicilio) e dati fiscali (ISA): nessuno
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        $avvisi = (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(30000, $q('RN001005'));
        $this->assertSame(30000, $q('RN004001'));
        $this->assertSame(7140, $q('RN005001'));
        // 1.910 x 0,9090 + 65 e ulteriore detrazione di 1.000 euro (reddito tra 20.000 e 32.000)
        $this->assertSame(1801, $q('RN007001'));
        $this->assertSame(1000, $q('RN007005'));
        $this->assertSame(2801, $q('RN022001'));
        $this->assertSame(4339, $q('RN026001'));
        $this->assertSame(6000, $q('RN033004'));
        $this->assertSame(-1661, $q('RN034001'));
        $this->assertSame(1661, $q('RN046001'));
        $this->assertSame(1661, $q('RX001002'));
        $this->assertSame(1661, $q('RX001005'));
        $this->assertSame(0, $q('RN043001'));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'domicilio fiscale')));
    }

    public function testFamiliariETrattamentoIntegrativo(): void
    {
        // 12.000 euro, coniuge a carico per l'anno, trattamento integrativo non erogato
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 12000, 'RC005006' => 12000, 'RC006001' => 365,
            'RC010001' => 600, 'RC014001' => 2, 'RC014005' => 1, 'RC014006' => 12000, 'RC014008' => 365, 'RC014009' => 2,
            'FA001001' => true, 'FA001005' => 'BNCLRA82A41H501X', 'FA001006' => 12]);
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(2760, $q('RN005001'));
        // coniuge: 800 - 110 x 0,8 = 712
        $this->assertSame(712, $q('RN006001'));
        $this->assertSame(1955, $q('RN007001'));
        $this->assertSame(1200, $q('RN043001'));
        $this->assertSame(1200, $q('RN043002'));
        // somma esente del 5,3% su 12.000 euro (reddito fino a 20.000)
        $this->assertSame(636, $q('RN044001'));
        $this->assertSame(636, $q('RN044002'));
        // imposta netta 2.760 - 712 - 1.955 = 93; credito: ritenute oltre l'imposta, trattamento e somma esente
        $this->assertSame(93, $q('RN026001'));
        $this->assertSame(600 - 93 + 1200 + 636, $q('RN046001'));
    }

    public function testFigliConiugeMancanteEAltriFamiliari(): void
    {
        // 30.000 euro; primo figlio con la percentuale C, secondo figlio al 100%, un genitore convivente (G) e un fratello (P)
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 30000, 'RC005006' => 30000, 'RC006001' => 365, 'RC014005' => 2,
            'FA002001' => 'F', 'FA002005' => 'RSSMRC03C15H501Z', 'FA002006' => 12, 'FA002B07' => 'C', 'FA002010' => 12,
            'FA003001' => 'F', 'FA003005' => 'RSSGLI01P60H501Q', 'FA003006' => 12, 'FA003A07' => 100, 'FA003010' => 12,
            'FA004001' => 'G', 'FA004005' => 'RSSGNN50A01H501Q', 'FA004006' => 12, 'FA004A07' => 100,
            'FA005001' => 'P', 'FA005005' => 'RSSPLA75A01H501Q', 'FA005006' => 12, 'FA005A07' => 100]);
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertNull($quadri->get('RN006001', 1));
        // due figli: quoziente (110.000 - 30.000) / 110.000 = 0,7272; primo figlio: la più favorevole tra 950 x 0,7272 = 690,84
        // e la detrazione del coniuge (690 + 20 di maggiorazione = 710); secondo figlio 690,84
        $this->assertSame((int) round(710 + 950 * 0.7272), $q('RN006002'));
        // solo l'ascendente convivente: 750 x 0,625; il fratello (P) non dà detrazione
        $this->assertSame(469, $q('RN006004'));
    }

    public function testOneriDetraibili(): void
    {
        // 30.000 euro: spese sanitarie 1.129 (1.000 oltre la franchigia, col. 2 del rigo RP15), istruzione 600 (col. 3), ONLUS 200 (col. 5)
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 30000, 'RC005006' => 30000, 'RC006001' => 365, 'RC014005' => 2,
            'RP001001' => 300, 'RP001002' => 1129, 'RP008001' => 12, 'RP008002' => 600, 'RP009001' => 61, 'RP009002' => 200,
            'RP021001' => 2000, 'RP026001' => 7, 'RP026002' => 900]);
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(1000, $q('RP015002'));
        $this->assertSame(600, $q('RP015003'));
        $this->assertSame(200, $q('RP015005'));
        $this->assertSame(1600 * 19 / 100 + 52, $q('RN013001'));
        // patologie esenti dei familiari non a carico (RP1 col. 1): franchigia già assorbita dalla col. 2
        $this->assertSame(57, $q('RN023001'));
        // deducibili: contributi 2.000 + codice 7 entro il 2% del reddito (600)
        $this->assertSame(2600, $q('RP039001'));
        $this->assertSame(2600, $q('RN003001'));

        // 90.000 euro senza figli: limite del riordino 14.000 x 0,50 = 7.000, imputato prima al 26% (ONLUS 5.000) e poi al 19% (2.000 su 3.533);
        // gli interessi del mutuo per l'abitazione principale (codice 7) non sono soggetti al riordino
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 90000, 'RC005006' => 90000, 'RC006001' => 365, 'RC014005' => 2,
            'RP007001' => 7, 'RP007002' => 3000, 'RP008001' => 61, 'RP008002' => 5000, 'RP009001' => 12, 'RP009002' => 900, 'RP010001' => 18, 'RP010002' => 2633]);
        $avvisi = (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $this->assertSame(3000 + 3533, $quadri->getImporto('RP015002', 1) + $quadri->getImporto('RP015003', 1));
        $this->assertSame(570 + 1300 + 380, $quadri->getImporto('RN013001', 1));

        // 150.000 euro con il riordino non automatizzato: limite 8.000 x 0,50 superato (avviso); ONLUS al 26% per il coefficiente (240.000 - 150.000) / 120.000
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 150000, 'RC005006' => 150000, 'RC006001' => 365, 'RC014005' => 2,
            'RP000001' => true, 'RP008001' => 61, 'RP008002' => 5000]);
        $avvisi = (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $this->assertSame(975, $quadri->getImporto('RN013001', 1));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'non automatizzato')));
    }

    public function testImpresaFamiliare(): void
    {
        // tre collaboratori (due nel primo modulo, il terzo nel secondo) per il 45 per cento di 40.000 euro
        $quadri = $this->creaQuadri([]);
        $impresa = new PropostaImpresa($this->dataMapper);
        $collaboratori = [['codiceFiscale' => 'NRESRA70A41H501X', 'quota' => 20.0, 'nominativo' => 'NERI SARA'],
            ['codiceFiscale' => 'VRDLCU95A01H501X', 'quota' => 15.0, 'nominativo' => 'VERDI LUCA'],
            ['codiceFiscale' => 'VRDNNA98A41H501X', 'quota' => 10.0, 'nominativo' => 'VERDI ANNA']];
        $this->assertSame(8000 + 6000 + 4000, $impresa->imputaCollaboratori($quadri, $collaboratori, 40000));
        $this->assertSame('NRESRA70A41H501X', $quadri->get('RS006001', 1));
        $this->assertSame(6000, $quadri->getImporto('RS007003', 1));
        $this->assertSame('VRDNNA98A41H501X', $quadri->get('RS006001', 2));
        $this->assertSame(4000, $quadri->getImporto('RS006003', 2));
        $this->assertSame([], $impresa->getAvvisi());

        // perdita: nessuna quota ai collaboratori; quote oltre il 49 per cento segnalate
        $quadri = $this->creaQuadri([]);
        $impresa = new PropostaImpresa($this->dataMapper);
        $collaboratori[0]['quota'] = 30.0;
        $this->assertSame(0, $impresa->imputaCollaboratori($quadri, $collaboratori, -5000));
        $this->assertNull($quadri->get('RS006003', 1));
        $this->assertSame(30.0, $quadri->getDecimale('RS006002', 1));
        $this->assertCount(2, $impresa->getAvvisi());
    }

    public function testPerditeImpresa(): void
    {
        // reddito 10.000, residui 9.000 in misura limitata e 3.000 in misura piena: 8.000 (80%) limitate e 2.000 piene
        $utilizzo = PropostaImpresa::utilizzaPerdite(10000, ['limitata' => 9000, 'piena' => 3000], false);
        $this->assertSame(8000, $utilizzo['limitata']);
        $this->assertSame(2000, $utilizzo['piena']);
        $this->assertSame([0, 1000], $utilizzo['rs12']);
        $this->assertSame([0, 1000], $utilizzo['rs13']);
        // perdita dell'anno nei primi tre periodi: misura piena, sommata ai residui
        $utilizzo = PropostaImpresa::utilizzaPerdite(-4000, ['limitata' => 500, 'piena' => 0], true);
        $this->assertSame([0, 500], $utilizzo['rs12']);
        $this->assertSame([4000, 4000], $utilizzo['rs13']);
        // senza la dichiarazione dell'anno prima: solo la perdita dell'anno
        $utilizzo = PropostaImpresa::utilizzaPerdite(-1500, null, false);
        $this->assertSame([1500, 1500], $utilizzo['rs12']);
        $this->assertSame([0, null], $utilizzo['rs13']);
        $this->assertSame(0, PropostaImpresa::utilizzaPerdite(5000, null, false)['limitata']);
    }

    public function testBonusEdilizi(): void
    {
        // righi inseriti a mano: recupero 2022 al 50% (rata 2.000) e 2025 sull'abitazione principale (codice 3, rata 3.000),
        // bonus mobili 2025 (rata 500), risparmio energetico 2025 al 36% (rata 1.000), ONLUS al 26% (5.000)
        $righi = ['RP041001' => 2022, 'RP041008' => 4, 'RP041009' => 20000, 'RP041010' => 2000, 'RP041011' => 1,
            'RP042001' => 2025, 'RP042007' => 3, 'RP042008' => 1, 'RP042009' => 30000, 'RP042010' => 3000, 'RP042011' => 1,
            'RP057001' => 1, 'RP057002' => 5000, 'RP057003' => 500,
            'RP061001' => 1, 'RP061002' => 2025, 'RP061007' => 1, 'RP061008' => 10000, 'RP061010' => 1000,
            'RP008001' => 61, 'RP008002' => 5000];
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 30000, 'RC005006' => 30000, 'RC006001' => 365, 'RC014005' => 2] + $righi);
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(5000, $q('RP048002'));
        $this->assertSame(2500, $q('RP049002'));
        $this->assertSame(500, $q('RP060001'));
        $this->assertSame(1000, $q('RP065011'));
        $this->assertSame(360, $q('RP066011'));
        $this->assertSame(2500, $q('RN014001'));
        $this->assertSame(250, $q('RN015001'));
        $this->assertSame(360, $q('RN016001'));
        $this->assertSame(1300, $q('RN013001'));

        // 90.000 euro senza figli: limite 7.000 imputato prima alle rate dell'anno al 50% (recupero 3.000, mobili 500), poi al 36%
        // (risparmio energetico 1.000) e infine al 26% (2.500 su 5.000); la rata del 2022 non è soggetta al riordino
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 90000, 'RC005006' => 90000, 'RC006001' => 365, 'RC014005' => 2] + $righi);
        (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(2500, $q('RN014001'));
        $this->assertSame(250, $q('RN015001'));
        $this->assertSame(360, $q('RN016001'));
        $this->assertSame(650, $q('RN013001'));
        // 130.000 euro: limite 8.000 x 0,50 = 4.000 alle rate al 50% (3.000 di recupero e 500 di mobili), 500 al 36%, niente al 26%
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 130000, 'RC005006' => 130000, 'RC006001' => 365, 'RC014005' => 2] + $righi);
        (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $this->assertSame(2500, $q('RN014001'));
        $this->assertSame(250, $q('RN015001'));
        $this->assertSame(180, $q('RN016001'));
        $this->assertSame(0, $q('RN013001'));
        // i totali del quadro RP restano quelli ordinari
        $this->assertSame(1000, $q('RP065011'));
    }

    public function testRighiDallArchivioDegliInterventi(): void
    {
        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->codiceCatastale = 'H501';
        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 7;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = $comune;
        $immobile->foglio = '120';
        $immobile->particella = '45';
        $immobile->subalterno = '8';
        $crea = function (TipoDetrazioneEdilizia $tipo, int $anno, ?int $tipologia, ?int $codice, float $spesa, float $percentuale, int $rateazione, bool $condominio = false) use ($immobile): InterventoEdilizio {
            $intervento = new InterventoEdilizio($this->dataMapper);
            $intervento->immobile = $immobile;
            $intervento->tipo = $tipo;
            $intervento->anno = $anno;
            $intervento->tipologia = $tipologia;
            $intervento->codicePercentuale = $codice;
            $intervento->spesa = $spesa;
            $intervento->percentualeDetrazione = $percentuale;
            $intervento->rateazione = $rateazione;
            $intervento->condominio = $condominio;
            return $intervento;
        };
        $interventi = ['RE' => [$crea(TipoDetrazioneEdilizia::ristrutturazione, 2022, 5, 1, 50000, 110, 10), $crea(TipoDetrazioneEdilizia::ristrutturazione, 2025, null, 3, 30000, 50, 10, true)],
            'CR' => [], 'AR' => [$crea(TipoDetrazioneEdilizia::arredo, 2025, null, null, 5000, 50, 10)], 'EN' => [$crea(TipoDetrazioneEdilizia::risparmioEnergetico, 2024, 2, 2, 20000, 70, 10)]];
        // un rigo RP41 inserito a mano resta: gli interventi vanno nei righi successivi
        $quadri = $this->creaQuadri(['RP041001' => 2019, 'RP041008' => 7, 'RP041009' => 10000, 'RP041010' => 1000]);
        $bonus = new PropostaBonusEdilizi($this->dataMapper);
        (new \ReflectionProperty(PropostaBonusEdilizi::class, 'interventi'))->setValue($bonus, $interventi);
        (new \ReflectionProperty(PropostaBonusEdilizi::class, 'anno'))->setValue($bonus, 2025);
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        $this->assertSame(1, $bonus->getModuliNecessari());
        $bonus->proponiRighi($quadri, $quadri->getDichiarazione()->soggetto);
        $q = fn(string $codice) => $quadri->get($codice, 1);
        $this->assertSame('2019', $q('RP041001'));
        // superbonus 2022 in 10 rate dal 2023: terza rata di 5.000
        $this->assertSame('2022', $q('RP042001'));
        $this->assertSame('3', $q('RP042008'));
        $this->assertSame('5000', $q('RP042010'));
        $this->assertSame('1', $q('RP042011'));
        $this->assertSame('3', $q('RP043007'));
        $this->assertSame('3000', $q('RP043010'));
        $this->assertSame('2', $q('RP043011'));
        // dati catastali dell'immobile (n. 1) e casella "Condominio" per le parti comuni (n. 2)
        $this->assertSame('H501', $q('RP051003'));
        $this->assertSame('U', $q('RP051004'));
        $this->assertSame('120', $q('RP051006'));
        $this->assertSame('45', $q('RP051A07'));
        $this->assertSame('8', $q('RP051008'));
        $this->assertSame('2', $q('RP052001'));
        $this->assertSame('1', $q('RP052002'));
        $this->assertNull($q('RP052003'));
        $this->assertSame('1', $q('RP057001'));
        $this->assertSame('500', $q('RP057003'));
        $this->assertSame('2', $q('RP061007'));
        $this->assertSame('2000', $q('RP061010'));
        // non possiede l'immobile e non è il conduttore: avviso per il rigo RP53
        $this->assertNotEmpty(array_filter($bonus->getAvvisi(), fn(string $avviso) => str_contains($avviso, 'RP53')));

        // totali: 70% (risparmio energetico 2024) e 110% (superbonus), riordino solo per le spese del 2025
        $bonus->proponiTotali($quadri, 2025);
        $this->assertSame(1000 + 3000, $quadri->getImporto('RP048002', 1));
        $this->assertSame(5000, $quadri->getImporto('RP048010', 1));
        $this->assertSame(5500, $quadri->getImporto('RP049010', 1));
        $this->assertSame(2000, $quadri->getImporto('RP065004', 1));
        $this->assertSame(['50ri' => 3000.0, '50ar' => 500.0], $bonus->getEntitaRiordino());
        $this->assertSame(['RN014001' => 2000 + 5500, 'RN015001' => 250, 'RN016001' => 1400], $bonus->detrazioni(null));
    }

    public function testPerditeForfetarioELavoroAutonomo(): void
    {
        // forfetario con 10.000 euro di reddito netto, residui dell'anno prima: 9.000 in misura limitata (fino a 8.000) e 3.000 in misura piena (2.000)
        $quadri = $this->creaQuadri([]);
        $impresa = new PropostaImpresa($this->dataMapper);
        (new \ReflectionProperty(PropostaImpresa::class, 'perditePrecedenti'))->setValue($impresa, ['limitata' => 9000, 'piena' => 3000, 'lavoroAutonomo' => 0, 'vantaggio' => 0]);
        (new \ReflectionMethod(PropostaImpresa::class, 'proponiPerditeForfetario'))->invoke($impresa, $quadri, 10000, true);
        $this->assertSame(8000, $quadri->getImporto('LM037003', 1));
        $this->assertSame(2000, $quadri->getImporto('LM037004', 1));
        $this->assertSame(10000, $quadri->getImporto('LM037005', 1));
        $this->assertSame(10000, $quadri->getImporto('LM037002', 1));
        $this->assertNull($quadri->get('LM037001', 1));
        $this->assertCount(1, $impresa->getAvvisi());

        // perdita di lavoro autonomo oltre gli altri redditi: reddito complessivo pari a zero
        $quadri = $this->creaQuadri(['RC001001' => 2, 'RC001002' => 1, 'RC001003' => 20000, 'RC005006' => 20000, 'RC006001' => 365, 'RC014005' => 2,
            'RE025001' => -25000]);
        $this->testAdapter->impostaSelectCallback(fn() => new TestResultSet([]));
        $avvisi = (new PropostaIrpef($this->dataMapper))->proponi($quadri->getDichiarazione(), $quadri);
        $this->assertSame(0, $quadri->getImporto('RN001005', 1));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'perdita eccedente')));
    }
}
