<?php

namespace Dichiarativi\Tests;

use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneGruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaIva;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ValoreDichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Iva\ControlliTr;
use Dichiarativi\Application\Services\Iva\CreditiTrimestrali;
use Dichiarativi\Application\Services\Iva\PropostaGruppoIva;
use Dichiarativi\Application\Services\Iva\PropostaTr;
use Dichiarativi\Application\Services\Iva\RimborsoIva;
use Dichiarativi\Application\Services\Iva\VersamentiTr;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Telematico\GeneratoreTr;
use Dichiarativi\Application\Services\Telematico\RecordQuadri;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Modello IVA TR (formati in centesimi, presupposti, controlli, file
 * telematico), credito chiesto escluso dal riporto delle liquidazioni,
 * rimborso annuale (causale, esonero, attestazione) e righi dei crediti
 * trimestrali nella dichiarazione annuale.
 */
class ModelloTrERimborsiTest extends DichiarativiTestCase
{

    private function creaTr(int $trimestre = 3): Dichiarazione
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = ModelloDichiarativo::ivaTr;
        $dichiarazione->trimestre = $trimestre;
        return $dichiarazione;
    }

    private function quadriTr(?Dichiarazione $dichiarazione = null): QuadriDichiarazione
    {
        return new QuadriDichiarazione($dichiarazione ?? $this->creaTr(), $this->dataMapper, $this->getCatalogoTr());
    }

    private function rispondi(array $tabelle): void
    {
        $tabelle['campo_modello'] ??= array_values($this->getCatalogoTr());
        $this->testAdapter->impostaSelectCallback(function (string $cmd) use ($tabelle): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            return new TestResultSet(preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1 ? ($tabelle[$tabella[1]] ?? []) : []);
        });
    }

    /**
     * Operazioni non imponibili e acquisti del terzo trimestre.
     *
     * @return array<string, array>
     */
    private function righeEsportatore(): array
    {
        $esente = $this->codiciIva['N4'];
        $esportazione = new CodiceIva($this->dataMapper);
        $esportazione->id = 300;
        $esportazione->studio = $esente->studio;
        $esportazione->codice = 'N3.1';
        $esportazione->descrizione = 'Esportazioni';
        $esportazione->aliquota = 0.0;
        $esportazione->natura = NaturaIva::esportazioni;
        $esportazione->detraibilita = 100.0;
        $this->codiciIva['N3.1'] = $esportazione;
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-08-10');
        $vendita->id = 1;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-08-12');
        $acquisto->id = 2;
        return ['dati_fiscali' => [$this->creaDatiFiscali()], 'riga_iva' => [$this->creaRigaIva('N3.1', 20000.0, 1, null, $vendita), $this->creaRigaIva('22', 15000.0, 1, null, $acquisto)]];
    }

    private function valore(Dichiarazione $dichiarazione, string $codice, string $valore, int $modulo = 1): ValoreDichiarazione
    {
        $riga = new ValoreDichiarazione($this->dataMapper);
        $riga->dichiarazione = $dichiarazione;
        $riga->modulo = $modulo;
        $riga->codice = $codice;
        $riga->valore = $valore;
        return $riga;
    }

    private function creaCredito(string $codice, int $anno, float $importo): Credito
    {
        $tributo = new CodiceTributo($this->dataMapper);
        $tributo->id = (int) $codice;
        $tributo->sezione = SezioneCodiceTributo::erario;
        $tributo->codice = $codice;
        $tributo->descrizione = 'Tributo ' . $codice;
        $credito = new Credito($this->dataMapper);
        $credito->id = (int) $codice;
        $credito->studio = $this->studioPredefinito;
        $credito->soggetto = $this->azienda;
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = $tributo;
        $credito->annoRiferimento = $anno;
        $credito->importo = $importo;
        return $credito;
    }

    public function testCreditoChiestoNonRiportato(): void
    {
        $liquidazione = new LiquidazioneIva($this->dataMapper);
        $liquidazione->creditoFinale = 9900.0;
        $liquidazione->creditoTrimestrale = 3300.0;
        $this->assertSame(6600.0, $liquidazione->getCreditoRiportato());
        $liquidazione->creditoTrimestrale = 12000.0;
        $this->assertSame(0.0, $liquidazione->getCreditoRiportato());
        $gruppo = new LiquidazioneGruppoIva($this->dataMapper);
        $gruppo->creditoFinale = 9240.0;
        $gruppo->creditoTrimestrale = 6600.0;
        $this->assertSame(2640.0, $gruppo->getCreditoRiportato());
    }

    public function testImportiInCentesimiDelModelloTr(): void
    {
        $quadri = $this->quadriTr();
        $quadri->imposta('TD006001', 1300);
        $quadri->imposta('TD007001', 2000.5);
        $this->assertSame(1300.0, $quadri->getDecimale('TD006001'));
        $this->assertSame(2000.5, $quadri->getDecimale('TD007001'));
    }

    public function testPresuppostiDalleRigheDelTrimestre(): void
    {
        $this->rispondi($this->righeEsportatore());
        $quadri = $this->quadriTr();
        $dati = (new PropostaTr($this->dataMapper))->proponiModulo($quadri, $this->azienda, 2025, 3, '');
        // 3.300 di imposta sugli acquisti, operazioni tutte non imponibili
        $this->assertSame(3300.0, $dati['credito']);
        $this->assertContains('TD002001', $dati['presupposti']);
        $this->assertSame(100, (int) $quadri->get('TD002002'));
        $this->assertSame(20000.0, $quadri->getDecimale('TA030001'));
    }

    public function testControlliDelModelloTr(): void
    {
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()]]);
        $controlli = new ControlliTr($this->dataMapper);
        $contiene = fn(array $errori, string $testo) => count(array_filter($errori, fn(string $errore) => str_contains($errore, $testo))) > 0;
        // quarto trimestre, credito sotto soglia e presupposto mancante
        $dichiarazione = $this->creaTr(4);
        $quadri = $this->quadriTr($dichiarazione);
        $quadri->imposta('TC007002', 2000);
        $errori = $controlli->getErrori($dichiarazione, $quadri);
        $this->assertTrue($contiene($errori, 'trimestre (da 1 a 3)'));
        $this->assertTrue($contiene($errori, '2.582,28'));
        $this->assertTrue($contiene($errori, 'presupposto'));
        // importi oltre il credito
        $dichiarazione = $this->creaTr();
        $quadri = $this->quadriTr($dichiarazione);
        $quadri->imposta('TC007002', 3300);
        $quadri->imposta('TD002001', true);
        $quadri->imposta('TD007001', 4000);
        $this->assertTrue($contiene($controlli->getErrori($dichiarazione, $quadri), 'non oltre il credito'));
        // con il solo presupposto dei beni ammortizzabili il limite è l'imposta del TB33
        $quadri = $this->quadriTr($dichiarazione);
        $quadri->imposta('TC007002', 3300);
        $quadri->imposta('TB033003', 1000);
        $quadri->imposta('TD003001', true);
        $quadri->imposta('TD007001', 2000);
        $this->assertTrue($contiene($controlli->getErrori($dichiarazione, $quadri), 'TB33'));
        // compensazione oltre 5.000 euro senza visto
        $quadri = $this->quadriTr($dichiarazione);
        $quadri->imposta('TC007002', 6000);
        $quadri->imposta('TD002001', true);
        $quadri->imposta('TD007001', 6000);
        $this->assertTrue($contiene($controlli->getErrori($dichiarazione, $quadri), 'visto di conformità'));
        $dichiarazione->esoneroVisto = true;
        $this->assertFalse($contiene($controlli->getErrori($dichiarazione, $quadri), 'visto di conformità'));
        // rimborso oltre 30.000 euro senza esonero
        $quadri = $this->quadriTr($dichiarazione);
        $quadri->imposta('TD006001', 31000);
        $this->assertNotEmpty($controlli->getAvvertenze($quadri));
    }

    public function testRecordCDelModelloTr(): void
    {
        $dichiarazione = $this->creaTr();
        $this->assertSame('IVT25_01234567897_2025_T3.txt', GeneratoreTr::getNomeFile($dichiarazione));
        $quadri = $this->quadriTr($dichiarazione);
        $quadri->imposta('TC007002', 3300);
        $quadri->imposta('TD002001', true);
        $quadri->imposta('TD002002', 100);
        $quadri->imposta('TD007001', 3300);
        $valori = [];
        foreach ($quadri->getValori() as $modulo => $campi) {
            foreach ($campi as $codice => $dati) {
                $valori[$modulo][$codice] = $dati['valore'];
            }
        }
        $record = RecordQuadri::genera('C', '01234567897', $valori, $quadri->getCatalogo());
        $this->assertCount(1, $record);
        $this->assertSame(1900, strlen($record[0]));
        $this->assertSame('C', $record[0][0]);
        // unità e centesimi di euro separati dalla virgola (specifiche, paragrafo sui formati)
        $this->assertStringContainsString('TD007001', $record[0]);
        $this->assertStringContainsString('3300,00', $record[0]);
    }

    public function testCausaleEdEsoneroDelRimborsoAnnuale(): void
    {
        $this->rispondi($this->righeEsportatore());
        $servizio = new RimborsoIva($this->dataMapper);
        // operazioni non imponibili oltre il 25%: causale 3 proposta con l'importo indicato
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VX002001', 5000);
        $quadri->imposta('VX004001', 3000);
        $servizio->proponiVx4($quadri, $dichiarazione);
        $this->assertSame('3', $quadri->get('VX004003'));
        $this->assertNull($quadri->get('VX004007'));
        // senza importo: solo l'avviso
        $senza = $this->creaQuadri($dichiarazione);
        $senza->imposta('VX002001', 5000);
        $avvisi = new RimborsoIva($this->dataMapper);
        $avvisi->proponiVx4($senza, $dichiarazione);
        $this->assertNull($senza->get('VX004003'));
        $this->assertStringContainsString('causale 3', $avvisi->getAvvisi()[0]);
        // sotto soglia nessuna causale dell'art. 30, c. 2
        $this->assertNull($servizio->getPresupposti($this->azienda, 2025, 2000, null)['causale']);
        // oltre 30.000 euro: adempimento collaborativo (codice 4) e attestazione con il visto
        $dichiarazione->adempimentoCollaborativo = true;
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VX002001', 40000);
        $quadri->imposta('VX004001', 35000);
        $servizio->proponiVx4($quadri, $dichiarazione);
        $this->assertSame('4', $quadri->get('VX004007'));
        $this->assertNull($quadri->get('VX00410A'));
        $dichiarazione->adempimentoCollaborativo = false;
        $dichiarazione->attestazionePatrimoniale = true;
        $dichiarazione->vistoProfessionista = $this->azienda;
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VX002001', 40000);
        $quadri->imposta('VX004001', 35000);
        $servizio->proponiVx4($quadri, $dichiarazione);
        $this->assertSame('1', $quadri->get('VX004007'));
        foreach (['VX00410A', 'VX00410B', 'VX00410C', 'VX00410D'] as $codice) {
            $this->assertNotNull($quadri->get($codice));
        }
    }

    public function testCodiciDiEsoneroDalleGaranzie(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $vs = ['visto' => '1', 'collaborativo' => '2', 'isa' => '3', 'concordato' => '4'];
        $this->assertNull($dichiarazione->getCodiceEsoneroGaranzie($vs));
        $dichiarazione->concordatoBiennale = true;
        $this->assertSame('4', $dichiarazione->getCodiceEsoneroGaranzie($vs));
        $dichiarazione->esoneroVisto = true;
        $this->assertSame('3', $dichiarazione->getCodiceEsoneroGaranzie($vs));
        $dichiarazione->adempimentoCollaborativo = true;
        $this->assertSame('2', $dichiarazione->getCodiceEsoneroGaranzie($vs));
        // la sottoscrizione dell'organo di controllo vale come il visto, con l'attestazione
        $dichiarazione->attestazionePatrimoniale = true;
        $this->assertSame('1', $dichiarazione->getCodiceEsoneroGaranzie($vs, true));
        // il VX4 non ha il codice ISA né quello del concordato
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->concordatoBiennale = true;
        $this->assertNull($dichiarazione->getCodiceEsoneroGaranzie(['visto' => '1', 'collaborativo' => '4']));
    }

    public function testControlliDelRimborso(): void
    {
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()]]);
        $controlli = new ControlliIva($this->dataMapper);
        $dichiarazione = $this->creaDichiarazione();
        $errori = function (array $valori) use ($controlli, $dichiarazione): string {
            $quadri = $this->creaQuadri($dichiarazione);
            foreach ($valori as $codice => $valore) {
                $quadri->imposta($codice, $valore);
            }
            return implode(' ', $controlli->getErrori($dichiarazione, $quadri));
        };
        $this->assertStringContainsString('supera 2.582,28', $errori(['VX002001' => 2000, 'VX004001' => 1000, 'VX004003' => '3']));
        $this->assertStringNotContainsString('supera 2.582,28', $errori(['VX002001' => 2000, 'VX004001' => 1000, 'VX004003' => '8']));
        $this->assertStringContainsString('non può superare il credito', $errori(['VX002001' => 3000, 'VX004001' => 4000, 'VX004003' => '3']));
        $this->assertStringContainsString('codice 6', $errori(['VX002001' => 3000, 'VX004001' => 1000, 'VX004003' => '3', 'VX004005' => 200]));
        $this->assertStringNotContainsString('codice 6', $errori(['VX002001' => 3000, 'VX004001' => 1000, 'VX004003' => '3', 'VX004004' => '6', 'VX004005' => 200]));
        // VY4 pari a VS20, oltre solo con il quadro VZ
        $this->assertStringContainsString('deve coincidere', $errori(['VY002001' => 9000, 'VY004001' => 5000, 'VS020001' => 3000]));
        $this->assertStringNotContainsString('deve coincidere', $errori(['VY002001' => 9000, 'VY004001' => 5000, 'VS020001' => 3000, 'VZ001001' => 2000]));
        $this->assertStringContainsString('deve coincidere', $errori(['VY002001' => 9000, 'VY004001' => 2000, 'VS020001' => 3000, 'VZ001001' => 2000]));
        $this->assertStringContainsString('Rigo VK25', $errori(['VK023001' => 5000, 'VK024001' => 4000, 'VK025001' => 2000]));
    }

    public function testMinoreEccedenzaDelTriennio(): void
    {
        $precedente = $this->creaDichiarazione(2024);
        $precedente->id = 50;
        $this->rispondi(['dichiarazione' => [$precedente], 'valore_dichiarazione' => [$this->valore($precedente, 'VX005001', '4000'), $this->valore($precedente, 'VL009001', '1000')]]);
        $servizio = new RimborsoIva($this->dataMapper);
        $quadri = $this->creaQuadri();
        $quadri->imposta('VL009001', 500);
        // 4.000 - 1.000 per il primo anno, 4.000 - 500 per il secondo, 5.000 dell'anno
        $this->assertSame(3000, $servizio->getMinoreEccedenza($this->azienda, 2025, 5000, $quadri));
        $this->assertSame(2000, $servizio->getMinoreEccedenza($this->azienda, 2025, 2000, $quadri));
        $this->rispondi([]);
        $this->assertNull($servizio->getMinoreEccedenza($this->azienda, 2025, 5000, $quadri));
    }

    public function testGaranzieDelGruppo(): void
    {
        $credito = $this->creaCredito('6099', 2024, 8000.0);
        $utilizzo = new Versamento($this->dataMapper);
        $utilizzo->id = 1;
        $utilizzo->studio = $this->studioPredefinito;
        $utilizzo->soggetto = $this->azienda;
        $utilizzo->scadenza = new SismaDate('2025-05-16');
        $utilizzo->sezione = SezioneF24::erario;
        $utilizzo->importoCredito = 1500.0;
        $utilizzo->credito = $credito;
        $this->rispondi(['credito' => [$credito], 'versamento' => [$utilizzo]]);
        $liquidazione = new LiquidazioneGruppoIva($this->dataMapper);
        $liquidazione->debitiTrasferiti = 2000.0;
        $quadri = $this->creaQuadri();
        $quadri->imposta('VL009001', 500);
        $quadri->imposta('VW025001', 6000);
        $servizio = new PropostaGruppoIva($this->dataMapper);
        $servizio->proponiGaranzie($quadri, $this->azienda, 2025, [$liquidazione]);
        // 1.500 utilizzati in F24, di cui 500 nel VL9 della controllante
        $this->assertSame(1000, $quadri->getImporto('VW024001'));
        // credito residuo di 5.000, compensato nei limiti dei debiti trasferiti
        $this->assertSame(2000, $quadri->getImporto('VS030001'));
        $liquidazione->debitiTrasferiti = 9000.0;
        $servizio->proponiGaranzie($quadri, $this->azienda, 2025, [$liquidazione]);
        $this->assertSame(5000, $quadri->getImporto('VS030001'));
    }

    public function testQuadroVzPerLaMinoreEccedenzaDelGruppo(): void
    {
        $precedente = $this->creaDichiarazione(2024);
        $precedente->id = 50;
        $this->rispondi(['dichiarazione' => [$precedente], 'valore_dichiarazione' => [$this->valore($precedente, 'VY005001', '4000'), $this->valore($precedente, 'VW024001', '1000')]]);
        $gruppo = new GruppoIva($this->dataMapper);
        $gruppo->controllante = $this->azienda;
        $gruppo->annoInizio = 2023;
        $dichiarazione = $this->creaDichiarazione();
        // nessun rimborso oltre le quote del quadro VS: niente quadro VZ, solo l'avviso
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VY002001', 5000);
        $quadri->imposta('VW024001', 500);
        $quadri->imposta('VS020001', 1000);
        $servizio = new PropostaGruppoIva($this->dataMapper);
        $servizio->proponiRimborsoGruppo($quadri, $dichiarazione, $gruppo);
        $this->assertSame(1000, $quadri->getImporto('VY004001'));
        $this->assertNull($quadri->get('VZ001001'));
        $this->assertStringContainsString('€ 3.000', implode(' ', $servizio->getAvvisi()));
        // parte del rimborso per la minore eccedenza: VZ1 e VZ2 dai prospetti dei due anni precedenti
        $quadri->imposta('VY004001', 3500);
        $servizio->proponiRimborsoGruppo($quadri, $dichiarazione, $gruppo);
        $this->assertSame(3000, $quadri->getImporto('VZ001001'));
        $this->assertSame(3500, $quadri->getImporto('VZ002001'));
        // gruppo iniziato da meno di due anni: niente eccedenze del triennio
        $gruppo->annoInizio = 2024;
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VY002001', 5000);
        $quadri->imposta('VS020001', 1000);
        $quadri->imposta('VY004001', 3500);
        $servizio->proponiRimborsoGruppo($quadri, $dichiarazione, $gruppo);
        $this->assertNull($quadri->get('VZ001001'));
    }

    public function testCreditiTrimestraliNellaDichiarazioneAnnuale(): void
    {
        $presentata = $this->creaTr();
        $presentata->id = 60;
        $presentata->stato = StatoDichiarazione::presentata;
        $bozza = $this->creaTr(2);
        $bozza->id = 61;
        $credito = $this->creaCredito('6038', 2025, 2000.0);
        $altro = $this->creaCredito('6099', 2025, 500.0);
        $utilizzo = new Versamento($this->dataMapper);
        $utilizzo->id = 1;
        $utilizzo->studio = $this->studioPredefinito;
        $utilizzo->soggetto = $this->azienda;
        $utilizzo->scadenza = new SismaDate('2025-11-17');
        $utilizzo->sezione = SezioneF24::erario;
        $utilizzo->importoCredito = 1500.0;
        $utilizzo->credito = $credito;
        $this->rispondi(['dichiarazione' => [$presentata, $bozza], 'valore_dichiarazione' => [$this->valore($presentata, 'TD006001', '1300.00')], 'credito' => [$credito, $altro], 'versamento' => [$utilizzo]]);
        $servizio = new CreditiTrimestrali($this->dataMapper);
        // solo i modelli presentati e i soli crediti trimestrali
        $this->assertSame(1300, $servizio->getRimborsiRichiesti($this->azienda, 2025));
        $this->assertSame(1500, $servizio->getCompensati($this->azienda, 2025));
    }

    public function testPresentazioneAnnotaLaLiquidazione(): void
    {
        $liquidazione = new LiquidazioneIva($this->dataMapper);
        $liquidazione->id = 9;
        $liquidazione->soggetto = $this->azienda;
        $liquidazione->studio = $this->studioPredefinito;
        $liquidazione->periodicita = \Anagrafica\Application\Enumerations\PeriodicitaIva::mensile;
        $liquidazione->anno = 2025;
        $liquidazione->periodo = 9;
        $liquidazione->creditoFinale = 9900.0;
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()], 'liquidazione_iva' => [$liquidazione]]);
        $dichiarazione = $this->creaTr();
        $quadri = $this->quadriTr($dichiarazione);
        $quadri->imposta('TD006001', 3300);
        $servizio = new VersamentiTr($this->dataMapper);
        // in bozza il credito resta nel riporto
        $this->assertSame([], $servizio->registraCredito($dichiarazione, $quadri));
        $this->assertSame(0.0, $liquidazione->creditoTrimestrale);
        $dichiarazione->dataPresentazione = new SismaDate('2025-10-28');
        $messaggi = $servizio->registraCredito($dichiarazione, $quadri);
        $this->assertSame(3300.0, $liquidazione->creditoTrimestrale);
        $this->assertStringContainsString('rielaborare', $messaggi[0]);
        $this->assertSame(6600.0, $liquidazione->getCreditoRiportato());
        $servizio->annullaCredito($dichiarazione);
        $this->assertSame(0.0, $liquidazione->creditoTrimestrale);
    }
}
