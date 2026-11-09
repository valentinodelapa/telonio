<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Dichiarativi\Application\Entities\CampoModello;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\FormatoCampo;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\PropostaImpresa;
use Dichiarativi\Application\Services\Redditi\PropostaIrpef;
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
}
