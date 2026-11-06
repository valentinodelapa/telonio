<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Services\CalcoloLiquidazioneIva;
use Contabilita\Application\Services\EsoneroAgricolo;
use Contabilita\Application\Services\RegimeIva;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Iva\OpzioniAgricole;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\PropostaTr;
use Dichiarativi\Application\Services\Iva\RimborsoIva;
use Dichiarativi\Application\Services\ModelliContribuente;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Regimi speciali agricoli (art. 34, 34-bis, agriturismo, enoturismo,
 * oleoturismo, associazioni, esonerati): liquidazione e dichiarazione.
 */
class RegimeAgricoloTest extends DichiarativiTestCase
{

    private function datiRegime(?RegimeDetrazioneIva $regime)
    {
        $dati = $this->creaDatiFiscali(\Anagrafica\Application\Enumerations\PeriodicitaIva::trimestrale);
        $dati->regimeDetrazioneIva = $regime;
        return $dati;
    }

    private function rispondi(array $tabelle): void
    {
        $this->testAdapter->impostaSelectCallback(function (string $cmd) use ($tabelle): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            return new TestResultSet(preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1 ? ($tabelle[$tabella[1]] ?? []) : []);
        });
    }

    private function codiceNatura(string $codice, NaturaIva $natura, int $id): void
    {
        $base = $this->codiciIva['N4'];
        $nuovo = new CodiceIva($this->dataMapper);
        $nuovo->id = $id;
        $nuovo->studio = $base->studio;
        $nuovo->codice = $codice;
        $nuovo->descrizione = $codice;
        $nuovo->aliquota = 0.0;
        $nuovo->natura = $natura;
        $nuovo->detraibilita = 100.0;
        $this->codiciIva[$codice] = $nuovo;
    }

    /**
     * Vendita al 22% con compensazione 12,3%, esportazione con compensazione,
     * operazione diversa, connessa occasionale, conferimento, due acquisti.
     *
     * @return array<int, \Contabilita\Application\Entities\RigaIva>
     */
    private function righe(): array
    {
        $this->codiceNatura('N3.1', NaturaIva::esportazioni, 310);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $vendita->id = 1;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-03-12');
        $acquisto->id = 2;
        $agricola = $this->creaRigaIva('22', 10000.0, 1, null, $vendita);
        $agricola->percentualeCompensazione = 12.3;
        $esportazione = $this->creaRigaIva('N3.1', 5000.0, 1, null, $vendita);
        $esportazione->percentualeCompensazione = 12.3;
        $diversa = $this->creaRigaIva('22', 2000.0, 1, null, $vendita);
        $connessa = $this->creaRigaIva('22', 1000.0, 1, null, $vendita);
        $connessa->qualifica = QualificaOperazione::connessaOccasionale;
        $conferimento = $this->creaRigaIva('10', 1000.0, 1, null, $vendita);
        $conferimento->qualifica = QualificaOperazione::conferimentoAgricolo;
        $generico = $this->creaRigaIva('22', 3000.0, 1, null, $acquisto);
        $destinato = $this->creaRigaIva('22', 500.0, 1, null, $acquisto);
        $destinato->operazioneDiversa = true;
        return [$agricola, $esportazione, $diversa, $connessa, $conferimento, $generico, $destinato];
    }

    public function testLiquidazioneRegimeArticolo34(): void
    {
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => $this->righe()]);
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($this->azienda, new SismaDate('2025-01-01'), new SismaDate('2025-03-31'));
        // 1.230 di compensazione + 615 di IVA teorica + 110 dell'acquisto destinato + 110 (50% della connessa) + 100 del conferimento al 10%
        $this->assertSame(2165.0, $risultato->ivaDetraibile);
        $this->assertSame(615.0, $risultato->ivaTeorica);
        // l'acquisto generico non si detrae, l'imposta delle vendite resta a debito
        $this->assertSame(2200.0 + 440.0 + 220.0 + 100.0, $risultato->ivaEsigibile);
    }

    public function testLiquidazioneForfetaria(): void
    {
        foreach ([[RegimeDetrazioneIva::agriturismo, 1650.0], [RegimeDetrazioneIva::attivitaConnesse, 1650.0], [RegimeDetrazioneIva::enoturismo, 1650.0], [RegimeDetrazioneIva::oleoturismo, 1650.0], [RegimeDetrazioneIva::associazioniAgricole, 1100.0]] as [$regime, $atteso]) {
            $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
            $vendita->id = 1;
            $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-03-12');
            $acquisto->id = 2;
            $this->rispondi(['dati_fiscali' => [$this->datiRegime($regime)], 'riga_iva' => [$this->creaRigaIva('22', 15000.0, 1, null, $vendita), $this->creaRigaIva('22', 9000.0, 1, null, $acquisto)]]);
            $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($this->azienda, new SismaDate('2025-01-01'), new SismaDate('2025-03-31'));
            $this->assertSame($atteso, $risultato->ivaDetraibile);
        }
    }

    public function testDichiarazioneRegimeArticolo34(): void
    {
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => $this->righe()]);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        // casella del regime, conferimento nella sezione 1 del VE, le altre nella sezione 2
        $this->assertNotNull($quadri->get('VF030008'));
        $this->assertSame(1000, $quadri->getImporto('VE010001'));
        $this->assertSame(13000, $quadri->getImporto('VE023001'));
        // sezione 3-B: operazioni diverse senza la connessa, compensazione, IVA teorica
        $this->assertSame(2000, $quadri->getImporto('VF038001'));
        $this->assertSame(440, $quadri->getImporto('VF038002'));
        $this->assertSame(10000, $quadri->getImporto('VF049001'));
        $this->assertSame(1230, $quadri->getImporto('VF049002'));
        $this->assertSame(1000, $quadri->getImporto('VF048001'));
        $this->assertSame(100, $quadri->getImporto('VF048002'));
        $this->assertSame(1330, $quadri->getImporto('VF052002'));
        $this->assertSame(110, $quadri->getImporto('VF053002'));
        $this->assertSame(615, $quadri->getImporto('VF054002'));
        $this->assertSame(2055, $quadri->getImporto('VF055002'));
        $this->assertSame(1000, $quadri->getImporto('VF062001'));
        $this->assertSame(220, $quadri->getImporto('VF062002'));
        // VF71 = VF55 + 50% del VF62, come la liquidazione
        $this->assertSame(2165, $quadri->getImporto('VF071002'));
        $this->assertSame(2165, $quadri->getImporto('VL002001'));
        $segnalazioni = (new ControlliIva($this->dataMapper))->getSegnalazioni($quadri);
        $this->assertEmpty(array_filter($segnalazioni, fn(string $segnalazione) => str_starts_with($segnalazione, 'VF')));
        $quadri->imposta('VF071002', 3000);
        $this->assertNotEmpty(array_filter((new ControlliIva($this->dataMapper))->getSegnalazioni($quadri), fn(string $segnalazione) => str_starts_with($segnalazione, 'VF071002')));
    }

    public function testDichiarazioneForfetaria(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $vendita->id = 1;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-03-12');
        $acquisto->id = 2;
        foreach ([[RegimeDetrazioneIva::agriturismo, 'VF030004', 1650], [RegimeDetrazioneIva::attivitaConnesse, 'VF030007', 1650], [RegimeDetrazioneIva::enoturismo, 'VF030009', 1650], [RegimeDetrazioneIva::oleoturismo, 'VF030010', 1650], [RegimeDetrazioneIva::associazioniAgricole, 'VF030005', 1100]] as [$regime, $casella, $atteso]) {
            $this->rispondi(['dati_fiscali' => [$this->datiRegime($regime)], 'riga_iva' => [$this->creaRigaIva('22', 15000.0, 1, null, $vendita), $this->creaRigaIva('22', 9000.0, 1, null, $acquisto)]]);
            $dichiarazione = $this->creaDichiarazione();
            $quadri = $this->creaQuadri($dichiarazione);
            (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
            $this->assertNotNull($quadri->get($casella));
            $this->assertSame($atteso, $quadri->getImporto('VF071002'));
            $segnalazioni = (new ControlliIva($this->dataMapper))->getSegnalazioni($quadri);
            $this->assertEmpty(array_filter($segnalazioni, fn(string $segnalazione) => str_starts_with($segnalazione, 'VF071002')));
        }
    }

    public function testEsoneratoSenzaLiquidazioniEDichiarazioneOltreLimite(): void
    {
        $dati = $this->datiRegime(RegimeDetrazioneIva::agricoloEsonerato);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-05-10');
        $vendita->id = 1;
        $agricola = $this->creaRigaIva('10', 4000.0, 1, null, $vendita);
        $agricola->percentualeCompensazione = 4.0;
        $this->rispondi(['dati_fiscali' => [$dati], 'riga_iva' => [$agricola]]);
        // niente liquidazioni, dichiarazione non dovuta nei limiti
        try {
            (new RegimeIva($this->dataMapper))->getPeriodicita($this->azienda, 2025);
            $this->fail('L\'esonerato non liquida l\'IVA');
        } catch (\DomainException $errore) {
            $this->assertStringContainsString('esonerato', $errore->getMessage());
        }
        $modelli = (new ModelliContribuente($this->dataMapper))->getModelli($this->azienda, 2025);
        $this->assertNotNull($modelli['IVA']);
        $this->assertNotNull($modelli['TR']);
        // limite di un terzo per le operazioni diverse
        $this->assertFalse(EsoneroAgricolo::isLimiteSuperato(6000.0, 3000.0));
        $this->assertTrue(EsoneroAgricolo::isLimiteSuperato(6000.0, 3001.0));
        $diversa = $this->creaRigaIva('22', 3000.0, 1, null, $vendita);
        $this->rispondi(['dati_fiscali' => [$dati], 'riga_iva' => [$agricola, $diversa]]);
        $modelli = (new ModelliContribuente($this->dataMapper))->getModelli($this->azienda, 2025);
        $this->assertNull($modelli['IVA']);
        $this->assertNotNull($modelli['TR']);
        // dichiarazione: agricole nella sezione 1 con la percentuale come aliquota, diverse nella 2, niente VH
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        $this->assertSame(4000, $quadri->getImporto('VE002001'));
        $this->assertSame(160, $quadri->getImporto('VE002002'));
        $this->assertSame(3000, $quadri->getImporto('VE023001'));
        $this->assertSame(160, $quadri->getImporto('VF040002'));
        $this->assertSame(3000, $quadri->getImporto('VF038001'));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'VH non si compila')));
        // volume dell'anno precedente oltre 7.000 euro: esonero non spettante
        $this->assertNotNull((new EsoneroAgricolo($this->dataMapper))->getMotivoDichiarazione($this->azienda, 2025));
    }

    public function testCausale7DellaTeorica(): void
    {
        $this->codiceNatura('N3.1', NaturaIva::esportazioni, 311);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $vendita->id = 1;
        $esportazione = $this->creaRigaIva('N3.1', 50000.0, 1, null, $vendita);
        $esportazione->percentualeCompensazione = 12.3;
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => [$esportazione]]);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        $quadri->imposta('VX004001', 4000);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        $this->assertSame(6150, $quadri->getImporto('VF054002'));
        $this->assertSame('7', $quadri->get('VX004003'));
        $presupposti = (new RimborsoIva($this->dataMapper))->getPresupposti($this->azienda, 2025, 6150, $quadri);
        $this->assertSame(6150, $presupposti['limite']);
        // oltre l'IVA teorica il rimborso non compete
        $quadri->imposta('VX004001', 7000);
        $errori = implode(' ', (new ControlliIva($this->dataMapper))->getErrori($dichiarazione, $quadri));
        $this->assertStringContainsString('IVA teorica', $errori);
    }

    public function testIvaTeoricaNelModelloTr(): void
    {
        $this->codiceNatura('N3.1', NaturaIva::esportazioni, 312);
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-02-10');
        $vendita->id = 1;
        $esportazione = $this->creaRigaIva('N3.1', 50000.0, 1, null, $vendita);
        $esportazione->percentualeCompensazione = 12.3;
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => [$esportazione]]);
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->modello = ModelloDichiarativo::ivaTr;
        $dichiarazione->trimestre = 1;
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper, $this->getCatalogoTr());
        $dati = (new PropostaTr($this->dataMapper))->proponiModulo($quadri, $this->azienda, 2025, 1, '');
        // l'IVA teorica sta nel TC5, non nell'imposta detraibile degli acquisti (TB32)
        $this->assertSame(0.0, $quadri->getDecimale('TB032001'));
        $this->assertSame(6150.0, $quadri->getDecimale('TC005002'));
        $this->assertSame(6150.0, $quadri->getDecimale('TC006002'));
        $this->assertSame(6150.0, $dati['credito']);
    }

    public function testEnumDelRegime(): void
    {
        foreach (RegimeDetrazioneIva::cases() as $regime) {
            $this->assertNotSame('', $regime->getLabel());
            $this->assertStringStartsWith('VF030', $regime->getCasellaVf30());
        }
        $this->assertTrue(RegimeDetrazioneIva::agricolo->isCompensazione());
        $this->assertTrue(RegimeDetrazioneIva::agricoloEsonerato->isCompensazione());
        $this->assertFalse(RegimeDetrazioneIva::agriturismo->isCompensazione());
        $this->assertSame(0.5, RegimeDetrazioneIva::attivitaConnesse->getQuotaForfetaria());
        $this->assertSame(1 / 3, RegimeDetrazioneIva::associazioniAgricole->getQuotaForfetaria());
    }

    public function testBeneAmmortizzabileFuoriDalVf53(): void
    {
        $righe = $this->righe();
        $conto = new Conto($this->dataMapper);
        $conto->id = 900;
        $conto->voceCee = VoceCee::attivoBII2;
        $righe[6]->conto = $conto;
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => $righe]);
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($this->azienda, new SismaDate('2025-01-01'), new SismaDate('2025-03-31'));
        // come testLiquidazioneRegimeArticolo34 senza i 110 dell'acquisto destinato
        $this->assertSame(2055.0, $risultato->ivaDetraibile);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        $this->assertSame(0, $quadri->getImporto('VF053002'));
        $this->assertSame(2055, $quadri->getImporto('VF071002'));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'ene ammortizzabile segnato')));
    }

    public function testQuotaImputabileDiUnAcquistoPromiscuo(): void
    {
        $righe = $this->righe();
        // stessa fattura: su due righe, 300 alle operazioni diverse e 700 indivisi
        $imputabile = $this->creaRigaIva('22', 300.0, 1, null, $righe[5]->registrazioneContabile);
        $imputabile->operazioneDiversa = true;
        $resto = $this->creaRigaIva('22', 700.0, 1, null, $righe[5]->registrazioneContabile);
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => [...$righe, $imputabile, $resto]]);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        // ai 110 dell'acquisto destinato si aggiungono solo i 66 della quota
        $this->assertSame(176, $quadri->getImporto('VF053002'));
    }

    public function testOperazioniDiverseSenzaAcquistiDestinati(): void
    {
        $righe = $this->righe();
        $righe[6]->operazioneDiversa = false;
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => $righe]);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        $this->assertSame(0, $quadri->getImporto('VF053002'));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'costi indivisibili')));
    }

    public function testAvvisoConnesseSoloSenzaAcquisti(): void
    {
        $righe = $this->righe();
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => $righe]);
        $dichiarazione = $this->creaDichiarazione();
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $this->creaQuadri($dichiarazione), []);
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'connesse occasionali (VF62) senza acquisti')));

        $acquisto = $this->creaRigaIva('22', 400.0, 1, null, $righe[5]->registrazioneContabile);
        $acquisto->qualifica = QualificaOperazione::connessaOccasionale;
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => [...$righe, $acquisto]]);
        $dichiarazione = $this->creaDichiarazione();
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $this->creaQuadri($dichiarazione), []);
        $this->assertEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'VF62')));
    }

    public function testAcquistiDelleConnesseNelVf18(): void
    {
        $righe = $this->righe();
        $acquisto = $this->creaRigaIva('22', 400.0, 1, null, $righe[5]->registrazioneContabile);
        $acquisto->qualifica = QualificaOperazione::connessaOccasionale;
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(RegimeDetrazioneIva::agricolo)], 'riga_iva' => [...$righe, $acquisto]]);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        // i due acquisti al 22% restano nel rigo dell'aliquota, quello delle connesse solo nel VF18
        $this->assertSame(3500, $quadri->getImporto('VF013001'));
        $this->assertSame(400, $quadri->getImporto('VF018001'));
        $this->assertSame(3900, $quadri->getImporto('VF025001'));
        $this->assertSame(770, $quadri->getImporto('VF025002'));
        $this->assertSame(2165, $quadri->getImporto('VF071002'));
        $this->assertSame(0, $quadri->getImporto('VF026002'));
    }

    public function testConnesseNeiModiOrdinari(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $vendita->id = 1;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-03-12');
        $acquisto->id = 2;
        $connessa = $this->creaRigaIva('22', 1000.0, 1, null, $vendita);
        $connessa->qualifica = QualificaOperazione::connessaOccasionale;
        $ordinaria = $this->creaRigaIva('22', 2000.0, 1, null, $acquisto);
        $perConnesse = $this->creaRigaIva('22', 400.0, 1, null, $acquisto);
        $perConnesse->qualifica = QualificaOperazione::connessaOccasionale;
        $this->rispondi(['dati_fiscali' => [$this->datiRegime(null)], 'riga_iva' => [$connessa, $ordinaria, $perConnesse]]);
        $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($this->azienda, new SismaDate('2025-01-01'), new SismaDate('2025-03-31'));
        // 440 dell'acquisto ordinario più il 50% dei 220 della connessa; niente sull'acquisto per le connesse
        $this->assertSame(550.0, $risultato->ivaDetraibile);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        $this->assertSame(1000, $quadri->getImporto('VF062001'));
        $this->assertSame(220, $quadri->getImporto('VF062002'));
        $this->assertSame(400, $quadri->getImporto('VF018001'));
        $this->assertSame(440, $quadri->getImporto('VF027002'));
        $this->assertSame(550, $quadri->getImporto('VF071002'));
        $this->assertSame([], array_filter((new \Dichiarativi\Application\Services\Iva\ControlliIva($this->dataMapper))->getSegnalazioni($quadri), fn(string $errore) => str_contains($errore, 'VF71')));
    }

    private function datiOpzioni(?RegimeDetrazioneIva $regime, string ...$opzioni)
    {
        $dati = $this->datiRegime($regime);
        $dati->opzioniOrdinarie = RegimeDetrazioneIva::scriviOpzioni($opzioni);
        return $dati;
    }

    public function testOpzioniERevocheDelQuadroVo(): void
    {
        foreach ([[RegimeDetrazioneIva::agricolo, 'VO003003', 'VO003004'], [RegimeDetrazioneIva::attivitaConnesse, 'VO003005', 'VO003006'], [RegimeDetrazioneIva::associazioniAgricole, 'VO031001', 'VO031002'],
    [RegimeDetrazioneIva::agriturismo, 'VO032001', 'VO032002'], [RegimeDetrazioneIva::enoturismo, 'VO035001', 'VO035002'], [RegimeDetrazioneIva::oleoturismo, 'VO036001', 'VO036002']] as [$regime, $opzione, $revoca]) {
            $this->assertArrayHasKey($opzione, $this->getCatalogoIva());
            $this->assertArrayHasKey($revoca, $this->getCatalogoIva());
            $this->assertSame([$opzione], OpzioniAgricole::calcola($this->datiOpzioni(null, $regime->value), $this->datiOpzioni(null), 0.0, 2025)['caselle']);
            $esito = OpzioniAgricole::calcola($this->datiOpzioni(null), $this->datiOpzioni(null, $regime->value), 0.0, 2025);
            $this->assertSame([$revoca], $esito['caselle']);
            $this->assertSame([$regime], $esito['revocate']);
        }
        // opzione confermata: niente da comunicare
        $this->assertSame([], OpzioniAgricole::calcola($this->datiOpzioni(null, 'AGR', 'TUR'), $this->datiOpzioni(null, 'TUR', 'AGR'), 0.0, 2025)['caselle']);
    }

    public function testRinunciaAllEsonero(): void
    {
        $esonerato = $this->datiOpzioni(RegimeDetrazioneIva::agricoloEsonerato);
        // regime speciale per rinuncia: solo la casella 1
        $this->assertSame(['VO003001'], OpzioniAgricole::calcola($this->datiOpzioni(RegimeDetrazioneIva::agricolo), $esonerato, 5000.0, 2025)['caselle']);
        // anche i modi ordinari: caselle 1 e 3 insieme, come dicono le istruzioni
        $this->assertEqualsCanonicalizing(['VO003001', 'VO003003'], OpzioniAgricole::calcola($this->datiOpzioni(null, 'AGR'), $esonerato, 5000.0, 2025)['caselle']);
        // oltre i 7.000 euro l'esonero non spetta: non c'è rinuncia
        $this->assertSame([], OpzioniAgricole::calcola($this->datiOpzioni(RegimeDetrazioneIva::agricolo), $esonerato, 8000.0, 2025)['caselle']);
        // senza una rinuncia indicata il rientro nei limiti non è una revoca
        $esito = OpzioniAgricole::calcola($esonerato, $this->datiOpzioni(RegimeDetrazioneIva::agricolo), 0.0, 2025);
        $this->assertSame([], $esito['caselle']);
        $this->assertStringContainsString('casella 2', $esito['avvisi'][0]);
    }

    public function testRinunciaIndicataNeiDatiFiscali(): void
    {
        $rinuncia = $this->datiOpzioni(RegimeDetrazioneIva::agricolo);
        $rinuncia->rinunciaEsonero = true;
        // prima indicazione: casella 1, una volta sola anche col passaggio dall'esonero
        $esonerato = $this->datiOpzioni(RegimeDetrazioneIva::agricoloEsonerato);
        $this->assertSame(['VO003001'], OpzioniAgricole::calcola($rinuncia, $esonerato, 5000.0, 2025)['caselle']);
        // la rinuncia che prosegue non si comunica di nuovo
        $this->assertSame([], OpzioniAgricole::calcola($rinuncia, $rinuncia, 5000.0, 2025)['caselle']);
        // fine della rinuncia: casella 2, senza avvisi sul passaggio
        $esito = OpzioniAgricole::calcola($esonerato, $rinuncia, 0.0, 2025);
        $this->assertSame(['VO003002'], $esito['caselle']);
        $this->assertSame([], $esito['avvisi']);
    }

    public function testOpzioniDelleAttivitaSeparate(): void
    {
        $attivita = new \Contabilita\Application\Entities\AttivitaIva($this->dataMapper);
        $attivita->descrizione = 'Agriturismo';
        $attivita->opzioneOrdinaria = RegimeDetrazioneIva::agriturismo;
        $attivita->opzioneDal = 2023;
        $this->assertSame(['VO032001'], OpzioniAgricole::calcolaAttivita($attivita, 2023)['caselle']);
        $this->assertSame([], OpzioniAgricole::calcolaAttivita($attivita, 2024)['caselle']);
        $this->assertTrue($attivita->isOpzioneAttivaNel(2024));
        // ultimo anno 2024: revoca dal 2025, prima del triennio
        $attivita->opzioneAl = 2024;
        $esito = OpzioniAgricole::calcolaAttivita($attivita, 2025);
        $this->assertSame(['VO032002'], $esito['caselle']);
        $this->assertStringContainsString('prima del triennio', $esito['avvisi'][0]);
        $this->assertFalse($attivita->isOpzioneAttivaNel(2025));
        // triennio compiuto: nessun avviso
        $attivita->opzioneAl = 2025;
        $this->assertSame([], OpzioniAgricole::calcolaAttivita($attivita, 2026)['avvisi']);
    }

    public function testRegimeLasciatoSenzaOpzione(): void
    {
        $esito = OpzioniAgricole::calcola($this->datiOpzioni(null), $this->datiOpzioni(RegimeDetrazioneIva::agriturismo), 0.0, 2025);
        $this->assertSame([], $esito['caselle']);
        $this->assertStringContainsString('senza l\'opzione', $esito['avvisi'][0]);
        $this->assertSame([], OpzioniAgricole::calcola($this->datiOpzioni(null, 'TUR'), $this->datiOpzioni(RegimeDetrazioneIva::agriturismo), 0.0, 2025)['avvisi']);
    }

    public function testOpzioneSenzaVersionePrecedente(): void
    {
        $dati = $this->datiOpzioni(null, 'AGR');
        $esito = OpzioniAgricole::calcola($dati, null, 0.0, 2025);
        $this->assertSame([], $esito['caselle']);
        $this->assertNotEmpty($esito['avvisi']);
        // attività iniziata nell'anno: l'opzione è di quest'anno
        $dati->dataInizioAttivita = new SismaDate('2025-02-01');
        $this->assertSame(['VO003003'], OpzioniAgricole::calcola($dati, null, 0.0, 2025)['caselle']);
    }

    public function testRevocaPrimaDelTriennio(): void
    {
        $senza = $this->datiOpzioni(null);
        $con = $this->datiOpzioni(null, 'TUR');
        $quadri = $this->creaQuadri();
        // 2025 senza, 2024 e 2023 con l'opzione, 2022 senza: applicata per due anni
        $this->testAdapter->accodaSelect([$senza], [$con], [$con], [$senza]);
        $avvisi = (new OpzioniAgricole($this->dataMapper))->proponi($quadri, $this->azienda, 2025);
        $this->assertNotNull($quadri->get('VO032002'));
        $this->assertStringContainsString('prima del triennio', $avvisi[0]);
        // 2024, 2023 e 2022 con l'opzione: triennio rispettato
        $this->testAdapter->accodaSelect([$senza], [$con], [$con], [$con]);
        $this->assertSame([], (new OpzioniAgricole($this->dataMapper))->proponi($this->creaQuadri(), $this->azienda, 2025));
    }

    public function testOpzioniNeiDatiFiscali(): void
    {
        $this->assertSame('AGR;TUR', RegimeDetrazioneIva::scriviOpzioni(['AGR', 'TUR', 'AGR', 'ESO', 'XXX']));
        $this->assertNull(RegimeDetrazioneIva::scriviOpzioni([]));
        $this->assertSame([RegimeDetrazioneIva::agricolo, RegimeDetrazioneIva::agriturismo], $this->datiOpzioni(null, 'AGR', 'TUR')->getOpzioniOrdinarie());
        $this->assertNotContains(RegimeDetrazioneIva::agricoloEsonerato, RegimeDetrazioneIva::getOpzionabili());
        $this->assertLessThanOrEqual(23, strlen(RegimeDetrazioneIva::scriviOpzioni(array_map(fn(RegimeDetrazioneIva $regime) => $regime->value, RegimeDetrazioneIva::getOpzionabili()))));
    }
}
