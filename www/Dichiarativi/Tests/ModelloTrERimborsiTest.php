<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Intermediario;
use Dichiarativi\Application\Services\Telematico\TracciatoTr;
use Deleghe\Application\Services\Telematico\RecordF24;
use Contabilita\Application\Enumerations\CodiceCaricaIva;
use Anagrafica\Application\Entities\CodiceAteco;
use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Entities\CreditoTrimestraleIva;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneGruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\PartecipanteGruppoIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Services\CreditoTrimestrale;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Dichiarativi\Application\Entities\AttoAccertamento;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ValoreDichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Enumerations\StatoDichiarazione;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Iva\ControlliTr;
use Dichiarativi\Application\Services\Iva\CreditiTrimestrali;
use Dichiarativi\Application\Services\Iva\PropostaGruppoIva;
use Dichiarativi\Application\Services\Iva\PropostaIva;
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

    private function delega(StatoDelega $stato): Delega
    {
        $delega = new Delega($this->dataMapper);
        $delega->stato = $stato;
        return $delega;
    }

    private function datiRegime(RegimeFiscale $regime): DatiFiscali
    {
        $dati = $this->creaDatiFiscali();
        $dati->regimeFiscale = $regime;
        return $dati;
    }

    public function testAltreNonImponibiliFuoriDalRigoTa30(): void
    {
        $tabelle = $this->righeEsportatore();
        $altre = clone $this->codiciIva['N3.1'];
        $altre->id = 301;
        $altre->codice = 'N3.6';
        $altre->natura = NaturaIva::nonImponibiliAltre;
        $this->codiciIva['N3.6'] = $altre;
        $vendita = $tabelle['riga_iva'][0]->registrazioneContabile;
        $tabelle['riga_iva'][0] = $this->creaRigaIva('N3.6', 20000.0, 1, null, $vendita);
        $this->rispondi($tabelle);
        $quadri = $this->quadriTr();
        $dati = (new PropostaTr($this->dataMapper))->proponiModulo($quadri, $this->azienda, 2025, 3, '');
        $this->assertSame(0.0, $quadri->getDecimale('TA030001'));
        $this->assertSame(20000.0, $quadri->getDecimale('TA031001'));
        $this->assertNotContains('TD002001', $dati['presupposti']);
    }

    public function testPresuppostoTd5DallaQualifica(): void
    {
        $tabelle = $this->righeEsportatore();
        $nonSoggetta = clone $this->codiciIva['N3.1'];
        $nonSoggetta->id = 302;
        $nonSoggetta->codice = 'N2.1';
        $nonSoggetta->natura = NaturaIva::nonSoggetteTerritorialita;
        $this->codiciIva['N2.1'] = $nonSoggetta;
        $vendita = $tabelle['riga_iva'][0]->registrazioneContabile;
        $tabelle['riga_iva'][0] = $this->creaRigaIva('N2.1', 20000.0, 1, null, $vendita);
        $this->rispondi($tabelle);
        // operazioni non soggette qualsiasi: nessun presupposto
        $dati = (new PropostaTr($this->dataMapper))->proponiModulo($this->quadriTr(), $this->azienda, 2025, 3, '');
        $this->assertNotContains('TD005001', $dati['presupposti']);
        $tabelle['riga_iva'][0]->qualifica = QualificaOperazione::prestazioneNonStabiliti;
        $dati = (new PropostaTr($this->dataMapper))->proponiModulo($this->quadriTr(), $this->azienda, 2025, 3, '');
        $this->assertContains('TD005001', $dati['presupposti']);
    }

    public function testCausaliDaiRighiVe(): void
    {
        $quadri = $this->creaQuadri();
        $quadri->imposta('VE030001', 2000);
        $quadri->imposta('VE050001', 10000);
        $this->assertSame([], RimborsoIva::getCausaliDaiRighiVe($quadri));
        // la percentuale si arrotonda all'unità superiore: 25,01% diventa 26%
        $quadri->imposta('VE030001', 2501);
        $this->assertSame(['3'], RimborsoIva::getCausaliDaiRighiVe($quadri));
        // prevalenza con le non soggette più esportazioni e operazioni assimilate
        $quadri->imposta('VE030001', 2000);
        $quadri->imposta('VE034001', 3100);
        $this->assertSame(['5'], RimborsoIva::getCausaliDaiRighiVe($quadri));
        // senza non soggette le sole esportazioni non danno la causale 5
        $quadri->imposta('VE030001', 6000);
        $quadri->imposta('VE034001', 0);
        $this->assertSame(['3'], RimborsoIva::getCausaliDaiRighiVe($quadri));
    }

    public function testPassaggioAlRegimeForfetario(): void
    {
        $ordinario = $this->datiRegime(RegimeFiscale::ordinario);
        $forfetario = $this->datiRegime(RegimeFiscale::forfetario);
        $this->assertTrue(RimborsoIva::isPassaggioForfetario($ordinario, $forfetario));
        $this->assertFalse(RimborsoIva::isPassaggioForfetario($forfetario, $forfetario));
        $this->assertFalse(RimborsoIva::isPassaggioForfetario($ordinario, $ordinario));
        $this->assertFalse(RimborsoIva::isPassaggioForfetario(null, $forfetario));
    }

    public function testErogazionePrioritariaPerAttivita(): void
    {
        $tabelle = $this->righeEsportatore();
        foreach (['59.14.00' => '7', '30.30.09' => '5', '46.64.99' => null] as $codice => $atteso) {
            $ateco = new CodiceAteco($this->dataMapper);
            $ateco->id = 1;
            $ateco->classificazione = ClassificazioneAteco::ateco2025;
            $ateco->codice = $codice;
            $tabelle['dati_fiscali'][0]->codiceAteco = $ateco;
            $this->rispondi($tabelle);
            $quadri = $this->quadriTr();
            (new PropostaTr($this->dataMapper))->proponiModulo($quadri, $this->azienda, 2025, 3, '');
            $this->assertSame($atteso, $quadri->get('TD008001'));
        }
    }

    public function testPrioritaDalRaccordoAteco(): void
    {
        $tabelle = $this->righeEsportatore();
        foreach (['38.32.10' => '2', '38.21.20' => '2', '24.43.00' => '3', '24.42.00' => '4', '38.31.10' => null] as $codice => $atteso) {
            $ateco = new CodiceAteco($this->dataMapper);
            $ateco->id = 1;
            $ateco->classificazione = ClassificazioneAteco::ateco2025;
            $ateco->codice = $codice;
            $tabelle['dati_fiscali'][0]->codiceAteco = $ateco;
            $this->rispondi($tabelle);
            $quadri = $this->quadriTr();
            (new PropostaTr($this->dataMapper))->proponiModulo($quadri, $this->azienda, 2025, 3, '');
            $this->assertSame($atteso, $quadri->get('TD008001'));
        }
    }

    public function testNonResidenti(): void
    {
        $tabelle = $this->righeEsportatore();
        $tabelle['dati_fiscali'][0]->nonResidenteIva = true;
        $this->rispondi($tabelle);
        $quadri = $this->quadriTr();
        $dati = (new PropostaTr($this->dataMapper))->proponiModulo($quadri, $this->azienda, 2025, 3, '');
        $this->assertContains('TD004001', $dati['presupposti']);
        $servizio = new RimborsoIva($this->dataMapper);
        // causale 6, con la soglia dell'art. 30, c. 2
        $annuale = $this->creaQuadri();
        $this->assertSame('6', $servizio->getPresupposti($this->azienda, 2025, 5000, $annuale)['causale']);
        $this->assertNull($servizio->getPresupposti($this->azienda, 2025, 2000, $annuale)['causale']);
    }

    public function testIngressoInUnGruppoIva(): void
    {
        $attuale = $this->creaDatiFiscali();
        $successivo = $this->creaDatiFiscali();
        $successivo->gruppoIvaArt70bis = true;
        $this->assertTrue(RimborsoIva::isIngressoGruppoIva($attuale, $successivo));
        $this->assertFalse(RimborsoIva::isIngressoGruppoIva($successivo, $successivo));
        $this->assertFalse(RimborsoIva::isIngressoGruppoIva($attuale, $attuale));
        // tra VX4, VX5 e VX6 si ripartisce la differenza tra i campi 1 e 2 del VX2, senza il VX3
        $quadri = $this->creaQuadri();
        $quadri->imposta('VX002001', 5000);
        $quadri->imposta('VX003001', 300);
        $this->assertSame(5300, PropostaIva::getCreditoRipartibile($quadri));
        $quadri->imposta('VX002002', 4000);
        $this->assertSame(1000, PropostaIva::getCreditoRipartibile($quadri));
    }

    public function testRischiDeiRimborsiOltreLimite(): void
    {
        $dati = $this->creaDatiFiscali();
        $this->rispondi(['dati_fiscali' => [$dati]]);
        $servizio = new RimborsoIva($this->dataMapper);
        $dichiarazione = $this->creaDichiarazione();
        // senza data non si può escludere il rischio
        $this->assertStringContainsString('mancante', implode(' ', $servizio->getRischi($dichiarazione)));
        // alla scadenza del 30 aprile 2026 l'attività iniziata il 1° settembre 2024 non ha due anni
        $dati->dataInizioAttivita = new SismaDate('2024-09-01');
        $this->assertStringContainsString('meno di due anni', implode(' ', $servizio->getRischi($dichiarazione)));
        $dichiarazione->dataPresentazione = new SismaDate('2026-09-30');
        $this->assertSame([], $servizio->getRischi($dichiarazione));
        $dichiarazione->dataPresentazione = null;
        // start-up innovative e lavoratori autonomi esclusi
        $dati->startupInnovativa = true;
        $this->assertSame([], $servizio->getRischi($dichiarazione));
        $dati->startupInnovativa = false;
        $dati->tipoAttivita = TipoAttivita::lavoroAutonomo;
        $this->assertSame([], $servizio->getRischi($dichiarazione));
        // cessazione nell'anno
        $this->azienda->dataCessazione = new SismaDate('2025-10-31');
        $this->assertContains('cessazione dell' . chr(39) . 'attività', $servizio->getRischi($dichiarazione));
    }

    public function testVistoNonEsoneraNelleSituazioniDiRischio(): void
    {
        $tabelle = $this->righeEsportatore();
        $tabelle['dati_fiscali'][0]->dataInizioAttivita = new SismaDate('2025-01-15');
        $this->rispondi($tabelle);
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->attestazionePatrimoniale = true;
        $dichiarazione->vistoProfessionista = $this->azienda;
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VX002001', 40000);
        $quadri->imposta('VX004001', 35000);
        $servizio = new RimborsoIva($this->dataMapper);
        $servizio->proponiVx4($quadri, $dichiarazione);
        $this->assertNull($quadri->get('VX004007'));
        $this->assertStringContainsString('garanzia dovuta', implode(' ', $servizio->getAvvisi()));
    }

    public function testLimiteDelleCompensazioni(): void
    {
        $credito = $this->creaCredito('6099', 2025, 1900000.0);
        $utilizzo = new Versamento($this->dataMapper);
        $utilizzo->id = 1;
        $utilizzo->studio = $this->studioPredefinito;
        $utilizzo->soggetto = $this->azienda;
        $utilizzo->scadenza = new SismaDate('2026-03-16');
        $utilizzo->sezione = SezioneF24::erario;
        $utilizzo->importoCredito = 1900000.0;
        $utilizzo->credito = $credito;
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()], 'credito' => [$credito], 'versamento' => [$utilizzo]]);
        $this->assertSame(1900000, (new CreditiTrimestrali($this->dataMapper))->getCompensatiNellAnno($this->azienda, 2026));
        $this->assertSame(0, (new CreditiTrimestrali($this->dataMapper))->getCompensatiNellAnno($this->azienda, 2025));
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VX004002', 150000);
        $this->assertStringContainsString('2.000.000', implode(' ', (new ControlliIva($this->dataMapper))->getAvvertenze($quadri, $dichiarazione)));
        $quadri->imposta('VX004002', 50000);
        $this->assertStringNotContainsString('2.000.000', implode(' ', (new ControlliIva($this->dataMapper))->getAvvertenze($quadri, $dichiarazione)));
        // semplificata oltre il rimborso e con la cessazione
        $quadri->imposta('VX004001', 40000);
        $errori = implode(' ', (new ControlliIva($this->dataMapper))->getErrori($dichiarazione, $quadri));
        $this->assertStringContainsString('procedura semplificata', $errori);
        $quadri->imposta('VX004002', 10000);
        $quadri->imposta('VX004003', '1');
        $errori = implode(' ', (new ControlliIva($this->dataMapper))->getErrori($dichiarazione, $quadri));
        $this->assertStringContainsString('campo 2 non va compilato', $errori);
    }

    public function testCausale10PerChiPartecipaAlGruppo(): void
    {
        $precedente = $this->creaDichiarazione(2024);
        $precedente->id = 50;
        $partecipante = new PartecipanteGruppoIva($this->dataMapper);
        $partecipante->id = 1;
        $partecipante->gruppoIva = new GruppoIva($this->dataMapper);
        $partecipante->gruppoIva->annoInizio = 2024;
        $partecipante->soggetto = $this->azienda;
        $partecipante->annoIngresso = 2024;
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()], 'dichiarazione' => [$precedente], 'partecipante_gruppo_iva' => [$partecipante],
            'valore_dichiarazione' => [$this->valore($precedente, 'VX005001', '4000'), $this->valore($precedente, 'VY005001', '6000')]]);
        $servizio = new RimborsoIva($this->dataMapper);
        $quadri = $this->creaQuadri();
        // anche sotto soglia, per la minore delle tre eccedenze
        $presupposti = $servizio->getPresupposti($this->azienda, 2025, 2000, $quadri);
        $this->assertSame('10', $presupposti['causale']);
        $this->assertSame(2000, $presupposti['limite']);
        // l'ex controllante parte dal VY5 del prospetto
        $this->assertSame(4000, $servizio->getMinoreEccedenza($this->azienda, 2025, 9000, $quadri, true));
        $this->assertSame(4000, $servizio->getMinoreEccedenza($this->azienda, 2025, 9000, $quadri));
        $altro = $this->valore($precedente, 'VX005001', '7000');
        $this->rispondi(['dichiarazione' => [$precedente], 'valore_dichiarazione' => [$altro, $this->valore($precedente, 'VY005001', '6000')]]);
        $this->assertSame(6000, $servizio->getMinoreEccedenza($this->azienda, 2025, 9000, $quadri, true));
        // nel quadro VS del prospetto le causali sono solo da 1 a 7
        $this->assertNull($servizio->getPresuppostiSocieta($this->azienda, 2025, 2000, $quadri)['causale']);
    }

    public function testCreditoPrecedenteNonTrasferibileAlGruppo(): void
    {
        $quadri = $this->creaQuadri();
        $quadri->imposta('VK001001', '09222330301');
        $quadri->imposta('VK001002', 12);
        $quadri->imposta('VL010001', 4000);
        // credito del VL10 nel VX2, mai nel VX8 trasferito al gruppo
        $this->assertSame([-4000, 0], PropostaIva::calcolaImposteVx($quadri, [1]));
    }

    private function creaAtto(string $notifica, float $dichiarato, float $accertato): AttoAccertamento
    {
        $atto = new AttoAccertamento($this->dataMapper);
        $atto->id = 1;
        $atto->studio = $this->studioPredefinito;
        $atto->soggetto = $this->azienda;
        $atto->annoImposta = 2022;
        $atto->dataNotifica = new SismaDate($notifica);
        $atto->importoDichiarato = $dichiarato;
        $atto->importoAccertato = $accertato;
        return $atto;
    }

    public function testSoglieDegliAttiDiAccertamento(): void
    {
        $this->assertFalse($this->creaAtto('2025-06-01', 100000.0, 110000.0)->isOltreSoglia());
        $this->assertTrue($this->creaAtto('2025-06-01', 100000.0, 110000.01)->isOltreSoglia());
        $this->assertSame(15000.0, $this->creaAtto('2025-06-01', 150000.0, 0.0)->getSoglia());
        $this->assertSame(10000.0, $this->creaAtto('2025-06-01', 200000.0, 0.0)->getSoglia());
        $this->assertSame(75000.0, $this->creaAtto('2025-06-01', 1500000.0, 0.0)->getSoglia());
        $this->assertSame(20000.0, $this->creaAtto('2025-06-01', 2000000.0, 0.0)->getSoglia());
        $this->assertSame(150000.0, $this->creaAtto('2025-06-01', 20000000.0, 0.0)->getSoglia());
        // conta anche un minor credito: la differenza è in valore assoluto
        $this->assertTrue($this->creaAtto('2025-06-01', 100000.0, 80000.0)->isOltreSoglia());
        $richiesta = new SismaDate('2026-04-30');
        $this->assertTrue($this->creaAtto('2024-04-30', 1.0, 1.0)->isNelBiennio($richiesta));
        $this->assertFalse($this->creaAtto('2024-04-29', 1.0, 1.0)->isNelBiennio($richiesta));
        $this->assertFalse($this->creaAtto('2026-05-01', 1.0, 1.0)->isNelBiennio($richiesta));
    }

    public function testVerificaDegliAccertamenti(): void
    {
        $dati = $this->creaDatiFiscali();
        $dati->dataInizioAttivita = new SismaDate('2018-03-01');
        $servizio = new RimborsoIva($this->dataMapper);
        $dichiarazione = $this->creaDichiarazione();
        // archivio vuoto e casella non barrata: la verifica resta da fare
        $this->rispondi(['dati_fiscali' => [$dati]]);
        $this->assertSame([], $servizio->getRischi($dichiarazione));
        $this->assertTrue($servizio->isVerificaDaFare($dichiarazione));
        $dichiarazione->verificaAccertamenti = true;
        $this->assertFalse($servizio->isVerificaDaFare($dichiarazione));
        // atto oltre soglia: garanzia dovuta, la casella non conta
        $dichiarazione->verificaAccertamenti = false;
        $this->rispondi(['dati_fiscali' => [$dati], 'atto_accertamento' => [$this->creaAtto('2025-06-01', 100000.0, 125000.0)]]);
        $this->assertStringContainsString('accertamento notificato il 01/06/2025', implode(' ', $servizio->getRischi($dichiarazione)));
        $this->assertFalse($servizio->isVerificaDaFare($dichiarazione));
        // atto sotto soglia o fuori dal biennio: come l'archivio vuoto
        $this->rispondi(['dati_fiscali' => [$dati], 'atto_accertamento' => [$this->creaAtto('2025-06-01', 100000.0, 105000.0), $this->creaAtto('2023-06-01', 100000.0, 125000.0)]]);
        $this->assertSame([], $servizio->getRischi($dichiarazione));
        $this->assertTrue($servizio->isVerificaDaFare($dichiarazione));
    }

    public function testRielaborazioneFermataDallaComunicazioneInviata(): void
    {
        $crea = function (int $id, int $periodo): LiquidazioneIva {
            $liquidazione = new LiquidazioneIva($this->dataMapper);
            $liquidazione->id = $id;
            $liquidazione->studio = $this->studioPredefinito;
            $liquidazione->soggetto = $this->azienda;
            $liquidazione->periodicita = PeriodicitaIva::mensile;
            $liquidazione->anno = 2025;
            $liquidazione->periodo = $periodo;
            return $liquidazione;
        };
        $settembre = $crea(9, 9);
        $settembre->creditoFinale = 9900.0;
        $ottobre = $crea(10, 10);
        $inviata = new ComunicazioneLipe($this->dataMapper);
        $inviata->stato = StatoComunicazioneLipe::inviata;
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()], 'liquidazione_iva' => [$settembre, $ottobre], 'comunicazione_lipe' => [$inviata]]);
        $messaggi = (new CreditoTrimestrale($this->dataMapper))->registra($this->azienda, null, 2025, 3, 3300.0);
        $testo = implode(' ', $messaggi);
        $this->assertStringContainsString('comunicazione del trimestre già inviata', $testo);
        $this->assertStringContainsString('corretto a mano', $testo);
        $this->assertStringNotContainsString('Riporto aggiornato', $testo);
    }

    public function testCreditoRegistratoSenzaLiquidazione(): void
    {
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()]]);
        $messaggi = (new CreditoTrimestrale($this->dataMapper))->registra($this->azienda, null, 2025, 3, 3300.0);
        $this->assertStringContainsString('alla sua elaborazione', $messaggi[0]);
        // la liquidazione elaborata dopo lo riprende dal registro
        $liquidazione = new LiquidazioneIva($this->dataMapper);
        $liquidazione->id = 9;
        $liquidazione->studio = $this->studioPredefinito;
        $liquidazione->soggetto = $this->azienda;
        $liquidazione->periodicita = PeriodicitaIva::mensile;
        $liquidazione->anno = 2025;
        $liquidazione->periodo = 9;
        $registrato = new CreditoTrimestraleIva($this->dataMapper);
        $registrato->id = 1;
        $registrato->studio = $this->studioPredefinito;
        $registrato->soggetto = $this->azienda;
        $registrato->anno = 2025;
        $registrato->trimestre = 3;
        $registrato->importo = 3300.0;
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()], 'liquidazione_iva' => [$liquidazione], 'credito_trimestrale_iva' => [$registrato]]);
        (new CreditoTrimestrale($this->dataMapper))->sincronizza($this->azienda, 2025, 3, $liquidazione);
        $this->assertSame(3300.0, $liquidazione->creditoTrimestrale);
        $this->assertSame(3, CreditoTrimestrale::getTrimestreChiuso($liquidazione));
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
        // non residenti: identificazione diretta (cifre 8-10 della partita IVA uguali a 999) o rappresentante fiscale
        $quadri = $this->quadriTr($dichiarazione);
        $quadri->imposta('TD004001', true);
        $this->assertTrue($contiene($controlli->getErrori($dichiarazione, $quadri), 'TD4'));
        $dichiarazione->codiceCarica = CodiceCaricaIva::rappresentanteFiscale;
        $this->assertFalse($contiene($controlli->getErrori($dichiarazione, $quadri), 'TD4'));
    }

    public function testRecordCDelModelloTr(): void
    {
        $dichiarazione = $this->creaTr();
        $this->assertSame('IVT25_01234560785_2025_T3.txt', GeneratoreTr::getNomeFile($dichiarazione));
        // codice fiscale della società dichiarante: formato CN, zeri se assente (posizione 1078)
        $recordB = (string) (new RecordF24(TracciatoTr::RECORD_B))->imposta('codiceFiscaleSocietaDichiarante', 0);
        $this->assertSame('00000000000', substr($recordB, 1077, 11));
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
        $record = RecordQuadri::genera('C', '01234560785', $valori, $quadri->getCatalogo());
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
        $quadri->imposta('VE030001', 20000);
        $quadri->imposta('VE050001', 20000);
        $quadri->imposta('VX002001', 5000);
        $quadri->imposta('VX004001', 3000);
        $servizio->proponiVx4($quadri, $dichiarazione);
        $this->assertSame('3', $quadri->get('VX004003'));
        $this->assertNull($quadri->get('VX004007'));
        // senza importo: solo l'avviso
        $senza = $this->creaQuadri($dichiarazione);
        $senza->imposta('VE030001', 20000);
        $senza->imposta('VE050001', 20000);
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
        $quadri->imposta('VE030001', 20000);
        $quadri->imposta('VE050001', 20000);
        $quadri->imposta('VX002001', 40000);
        $quadri->imposta('VX004001', 35000);
        $servizio->proponiVx4($quadri, $dichiarazione);
        $this->assertSame('4', $quadri->get('VX004007'));
        $this->assertNull($quadri->get('VX00410A'));
        $dichiarazione->adempimentoCollaborativo = false;
        $dichiarazione->attestazionePatrimoniale = true;
        $dichiarazione->vistoProfessionista = $this->azienda;
        $this->azienda->dataCostituzione = new SismaDate('2018-03-01');
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VE030001', 20000);
        $quadri->imposta('VE050001', 20000);
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

    public function testVistoDiChiNonTrasmette(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $this->assertNull(ControlliIva::getAvvisoVisto($dichiarazione));
        $professionista = new Soggetto($this->dataMapper);
        $professionista->codiceFiscale = 'MRNPLA70A01F205L';
        $professionista->nominativo = 'MARINI PAOLO';
        $studio = new Soggetto($this->dataMapper);
        $studio->codiceFiscale = '80012345676';
        $studio->nominativo = 'STUDIO DEMO';
        $intermediario = new Intermediario($this->dataMapper);
        $intermediario->soggetto = $studio;
        $dichiarazione->vistoProfessionista = $professionista;
        $dichiarazione->intermediario = $intermediario;
        $this->assertStringContainsString('MARINI PAOLO', (string) ControlliIva::getAvvisoVisto($dichiarazione));
        $intermediario->soggetto = $professionista;
        $this->assertNull(ControlliIva::getAvvisoVisto($dichiarazione));
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
        // firma dell'attestazione senza il quadro VK: esonero dalle garanzie con il codice 1 (specifiche, VX00410D)
        $this->assertStringContainsString('codice 1', $errori(['VX002001' => 40000, 'VX004001' => 35000, 'VX004003' => '3', 'VX00410A' => true, 'VX00410D' => true]));
        $this->assertStringNotContainsString('codice 1', $errori(['VX002001' => 40000, 'VX004001' => 35000, 'VX004003' => '3', 'VX004007' => '1', 'VX00410A' => true, 'VX00410D' => true]));
        // causale 13 e credito trasferito al gruppo solo con la casella del rigo VA11
        $this->assertStringContainsString('VA11', $errori(['VX002001' => 6000, 'VX002002' => 4000, 'VX004001' => 2000, 'VX004003' => '13']));
        $this->assertStringNotContainsString('VA11', $errori(['VA011001' => true, 'VX002001' => 6000, 'VX002002' => 4000, 'VX004001' => 2000, 'VX004003' => '13']));
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
        $utilizzo->delega = $this->delega(StatoDelega::trasmessa);
        $this->rispondi(['credito' => [$credito], 'versamento' => [$utilizzo]]);
        $quadri = $this->creaQuadri();
        $quadri->imposta('VL009001', 500);
        $quadri->imposta('VW025001', 6000);
        $servizio = new PropostaGruppoIva($this->dataMapper);
        $entro = new SismaDate('2026-04-30');
        $servizio->proponiGaranzie($quadri, $this->azienda, 2025, 2000, $entro);
        // 1.500 utilizzati in F24, di cui 500 nel VL9 della controllante
        $this->assertSame(1000, $quadri->getImporto('VW024001'));
        // credito residuo di 5.000, compensato nei limiti delle eccedenze di debito delle società (VK22)
        $this->assertSame(2000, $quadri->getImporto('VS030001'));
        $servizio->proponiGaranzie($quadri, $this->azienda, 2025, 9000, $entro);
        $this->assertSame(5000, $quadri->getImporto('VS030001'));
        // compensazione successiva alla presentazione: non conta
        $servizio->proponiGaranzie($quadri, $this->azienda, 2025, 9000, new SismaDate('2025-05-15'));
        $this->assertSame(0, $quadri->getImporto('VW024001'));
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

    public function testCreditoDiGruppoFuoriDalVl8DellaControllante(): void
    {
        $gruppo = new GruppoIva($this->dataMapper);
        $gruppo->id = 1;
        $gruppo->studio = $this->studioPredefinito;
        $gruppo->controllante = $this->azienda;
        $gruppo->annoInizio = 2023;
        $precedente = $this->creaDichiarazione(2024);
        $precedente->id = 50;
        $credito = $this->creaCredito('6099', 2024, 8000.0);
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()], 'gruppo_iva' => [$gruppo], 'dichiarazione' => [$precedente], 'credito' => [$credito],
            'valore_dichiarazione' => [$this->valore($precedente, 'VY005001', '5000')]]);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        // 8.000 in Deleghe, di cui 5.000 del gruppo (VY5 del prospetto precedente)
        $this->assertSame(5000, $quadri->getImporto('VW025001'));
        $this->assertSame(3000, $quadri->getImporto('VL008001'));
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
        $utilizzo->delega = $this->delega(StatoDelega::trasmessa);
        $inBozza = clone $utilizzo;
        $inBozza->id = 2;
        $inBozza->importoCredito = 200.0;
        $inBozza->delega = $this->delega(StatoDelega::bozza);
        $tardivo = clone $utilizzo;
        $tardivo->id = 3;
        $tardivo->scadenza = new SismaDate('2026-05-18');
        $tardivo->importoCredito = 300.0;
        $this->rispondi(['dichiarazione' => [$presentata, $bozza], 'valore_dichiarazione' => [$this->valore($presentata, 'TD006001', '1300.00')], 'credito' => [$credito, $altro], 'versamento' => [$utilizzo, $inBozza, $tardivo]]);
        $servizio = new CreditiTrimestrali($this->dataMapper);
        // solo i modelli presentati e i soli crediti trimestrali
        $this->assertSame(1300, $servizio->getRimborsiRichiesti($this->azienda, 2025));
        // fino alla presentazione e nelle sole deleghe trasmesse (VL22)
        $entro = new SismaDate('2026-04-30');
        $this->assertSame(1500, $servizio->getCompensati($this->azienda, 2025, $entro));
        $this->assertSame(500, $servizio->getEsclusi($this->azienda, 2025, $entro));
        $dichiarazione = $this->creaDichiarazione();
        $this->assertSame('2026-04-30', CreditiTrimestrali::getDataLimite($dichiarazione)->format('Y-m-d'));
        $dichiarazione->dataPresentazione = new SismaDate('2026-03-10');
        $this->assertSame('2026-03-10', CreditiTrimestrali::getDataLimite($dichiarazione)->format('Y-m-d'));
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
        $this->assertStringContainsString('annotato', $messaggi[0]);
        $this->assertSame(6600.0, $liquidazione->getCreditoRiportato());
        $registrato = new CreditoTrimestraleIva($this->dataMapper);
        $registrato->id = 1;
        $registrato->studio = $this->studioPredefinito;
        $registrato->soggetto = $this->azienda;
        $registrato->anno = 2025;
        $registrato->trimestre = 3;
        $registrato->importo = 3300.0;
        $this->rispondi(['dati_fiscali' => [$this->creaDatiFiscali()], 'liquidazione_iva' => [$liquidazione], 'credito_trimestrale_iva' => [$registrato]]);
        $servizio->annullaCredito($dichiarazione);
        $this->assertSame(0.0, $liquidazione->creditoTrimestrale);
    }
}
