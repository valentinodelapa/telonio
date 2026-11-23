<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Enumerations\RegimeDetrazioneIva;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Services\ChiusuraEsercizio;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\VoceCee;
use Dichiarativi\Application\Entities\RettificaDetrazione;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\ProspettoRettifiche;
use Telonio\Tests\TestResultSet;

/**
 * Prospetto D: rigo VF70 e proposta per i beni ammortizzabili e le rimanenze
 * al passaggio tra il regime ordinario e un regime speciale agricolo.
 */
class ProspettoRettificheTest extends DichiarativiTestCase
{

    private function dati(?RegimeDetrazioneIva $regime, int $anno)
    {
        $dati = $this->creaDatiFiscali(\Anagrafica\Application\Enumerations\PeriodicitaIva::trimestrale);
        $dati->regimeDetrazioneIva = $regime;
        $dati->validoDal = new \SismaFramework\Orm\CustomTypes\SismaDate($anno . '-01-01');
        return $dati;
    }

    private function bene(string $data, float $imponibile, VoceCee $voce = VoceCee::attivoBII2)
    {
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, $data);
        $acquisto->id = random_int(100, 100000);
        $riga = $this->creaRigaIva('22', $imponibile, 1, null, $acquisto);
        $conto = new Conto($this->dataMapper);
        $conto->id = random_int(100, 100000);
        $conto->voceCee = $voce;
        $riga->conto = $conto;
        return $riga;
    }

    private function rispondi(array $tabelle): void
    {
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind = []) use ($tabelle): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            return new TestResultSet(preg_match('/FROM `([a-z_]+)`/', $cmd, $tabella) === 1 ? ($tabelle[$tabella[1]] ?? []) : []);
        });
    }

    public function testQuintiEDecimiMancanti(): void
    {
        // entrato in funzione nel 2024, passaggio nel 2025: mancano 4 anni su 5
        $this->assertSame(800.0, ProspettoRettifiche::calcolaBene(1000.0, 2024, 2025));
        // nel 2028 ne manca uno, dal 2029 è fuori dal periodo
        $this->assertSame(200.0, ProspettoRettifiche::calcolaBene(1000.0, 2024, 2028));
        $this->assertSame(0.0, ProspettoRettifiche::calcolaBene(1000.0, 2024, 2029));
        // fabbricati: decimi
        $this->assertSame(900.0, ProspettoRettifiche::calcolaBene(1000.0, 2024, 2025, ProspettoRettifiche::PERIODO_FABBRICATI));
        // un bene dello stesso anno non si rettifica
        $this->assertSame(0.0, ProspettoRettifiche::calcolaBene(1000.0, 2025, 2025));
    }

    public function testSenzaCambioDiRegimeNessunaProposta(): void
    {
        $this->rispondi(['dati_fiscali' => [$this->dati(null, 2020)], 'riga_iva' => [$this->bene('2024-03-10', 1000.0)]]);
        $proposta = (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025);
        $this->assertSame(['totale' => 0, 'beni' => [], 'rimanenze' => null], $proposta);
    }

    public function testDopoLAbrogazioneNessunaProposta(): void
    {
        $this->rispondi(['dati_fiscali' => [$this->dati(RegimeDetrazioneIva::agricolo, 2027)], 'riga_iva' => [$this->bene('2024-03-10', 1000.0)]]);
        $this->assertSame([], (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2027)['beni']);
    }

    public function testNonAmmortizzabiliEsclusi(): void
    {
        $riga = $this->bene('2024-03-10', 1000.0);
        $riga->conto = null;
        $this->rispondi(['dati_fiscali' => [$this->dati(null, 2020)], 'riga_iva' => [$riga]]);
        $this->assertSame([], (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025)['beni']);
    }

    /**
     * Regime che cambia dal 2025: i dati fiscali dipendono dalla data cercata.
     */
    private function rispondiPassaggio(?RegimeDetrazioneIva $prima, ?RegimeDetrazioneIva $ora, array $righe, array $contabili = []): void
    {
        $vecchi = $this->dati($prima, 2020);
        $nuovi = $this->dati($ora, 2025);
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind = []) use ($vecchi, $nuovi, $righe, $contabili): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            if (str_contains($cmd, 'FROM `dati_fiscali`')) {
                return new TestResultSet(str_contains(print_r($bind, true), '2025-') ? [$nuovi] : [$vecchi]);
            }
            if (str_contains($cmd, 'FROM `riga_contabile`')) {
                return new TestResultSet($contabili);
            }
            return new TestResultSet(str_contains($cmd, 'FROM `riga_iva`') ? $righe : []);
        });
    }

    private function movimento(VoceCee $voce, float $dare, float $avere = 0.0, ?string $origine = null): RigaContabile
    {
        $registrazione = $this->creaRegistrazione(CausaleContabile::giroconto, null, '2024-12-31');
        $registrazione->origine = $origine;
        $conto = new Conto($this->dataMapper);
        $conto->id = random_int(100, 100000);
        $conto->voceCee = $voce;
        $riga = new RigaContabile($this->dataMapper);
        $riga->registrazioneContabile = $registrazione;
        $riga->conto = $conto;
        $riga->dare = $dare;
        $riga->avere = $avere;
        return $riga;
    }

    /**
     * Rimanenze a fine 2024: esistenze riaperte 2.000, stornate, finali 3.000;
     * la chiusura e gli altri conti non contano.
     */
    private function movimentiRimanenze(): array
    {
        return [$this->movimento(VoceCee::attivoCI4, 2000.0, 0.0, ChiusuraEsercizio::ORIGINE_RIAPERTURA), $this->movimento(VoceCee::attivoCI4, 0.0, 2000.0),
            $this->movimento(VoceCee::attivoCI4, 3000.0), $this->movimento(VoceCee::attivoCI4, 0.0, 3000.0, ChiusuraEsercizio::ORIGINE),
            $this->movimento(VoceCee::attivoCII1, 5000.0)];
    }

    public function testRimanenzeDalSaldoConAliquotaMediaDegliAcquistiDiBeni(): void
    {
        $merci22 = $this->bene('2024-03-10', 1000.0, VoceCee::economicoB6);
        $merci10 = $this->bene('2024-05-10', 1000.0, VoceCee::economicoB6);
        $merci10->codiceIva = $this->codiciIva['10'];
        $merci10->imposta = 100.0;
        // un servizio e gli acquisti di un altro anno non entrano nella media
        $servizio = $this->bene('2024-06-10', 1000.0, VoceCee::economicoB7);
        $altroAnno = $this->bene('2023-06-10', 1000.0, VoceCee::economicoB6);
        $this->rispondiPassaggio(RegimeDetrazioneIva::agricolo, null, [$merci22, $merci10, $servizio, $altroAnno], $this->movimentiRimanenze());
        $proposta = (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025);
        $this->assertSame([], $proposta['beni']);
        $this->assertSame(3000.0, $proposta['rimanenze']['valoreProposto']);
        // (220 + 100) / 2.000 = 16%: si recuperano 480
        $this->assertSame(16.0, $proposta['rimanenze']['aliquotaProposta']);
        $this->assertSame(480.0, $proposta['rimanenze']['importo']);
        $this->assertSame(480, $proposta['totale']);
        $this->assertTrue(ProspettoRettifiche::haProposta($proposta));
    }

    public function testRimanenzeCorretteAMano(): void
    {
        $this->rispondiPassaggio(RegimeDetrazioneIva::agricolo, null, [$this->bene('2024-03-10', 1000.0, VoceCee::economicoB6), $this->bene('2024-03-10', 1000.0)], $this->movimentiRimanenze());
        $prospetto = new ProspettoRettifiche($this->dataMapper);
        $proposta = $prospetto->getProposta($this->azienda, 2025, null, 1000.0, 10.0);
        // la proposta resta visibile, il totale usa i valori a mano: 100 di rimanenze e 176 del bene
        $this->assertSame(3000.0, $proposta['rimanenze']['valoreProposto']);
        $this->assertSame(22.0, $proposta['rimanenze']['aliquotaProposta']);
        $this->assertSame(100.0, $proposta['rimanenze']['imposta']);
        $this->assertSame(276, $proposta['totale']);
        // senza valori a mano torna la proposta
        $this->assertSame(660 + 176, ProspettoRettifiche::impostaRimanenze($proposta, null, null)['totale']);
    }

    public function testRimanenzeDallOrdinarioSiRestituisconoConLaDetrazioneEffettiva(): void
    {
        $merci = $this->bene('2024-03-10', 1000.0, VoceCee::economicoB6);
        $merci->quotaCommerciale = 0.5;
        $this->rispondiPassaggio(null, RegimeDetrazioneIva::agricolo, [$merci], $this->movimentiRimanenze());
        $proposta = (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025);
        // detratti 110 su 1.000: 11% di 3.000, restituiti
        $this->assertSame(11.0, $proposta['rimanenze']['aliquotaProposta']);
        $this->assertSame(-330, $proposta['totale']);
    }

    public function testRimanenzeSenzaAcquistiDiBeniAliquotaDaIndicare(): void
    {
        $this->rispondiPassaggio(RegimeDetrazioneIva::agricolo, null, [], $this->movimentiRimanenze());
        $proposta = (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025);
        $this->assertNull($proposta['rimanenze']['aliquotaProposta']);
        $this->assertSame(0, $proposta['totale']);
        // il cambio di regime resta segnalato
        $this->assertTrue(ProspettoRettifiche::haProposta($proposta));
        $this->assertSame(300, ProspettoRettifiche::impostaRimanenze($proposta, null, 10.0)['totale']);
    }

    public function testDalRegimeSpecialeAllOrdinarioSiRecupera(): void
    {
        $this->rispondiPassaggio(RegimeDetrazioneIva::agricolo, null, [$this->bene('2024-03-10', 1000.0)]);
        $proposta = (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025);
        // 220 di imposta per quattro quinti
        $this->assertSame(176, $proposta['totale']);
    }

    public function testDallOrdinarioAlRegimeSpecialeSiRestituisce(): void
    {
        $this->rispondiPassaggio(null, RegimeDetrazioneIva::agricolo, [$this->bene('2023-03-10', 1000.0), $this->bene('2022-06-01', 1000.0, VoceCee::attivoBII1)]);
        $proposta = (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025);
        // tre quinti del bene del 2023 e sette decimi del fabbricato del 2022
        $this->assertSame(-(132 + 154), $proposta['totale']);
    }

    public function testBeneDiCostoUnitarioMinimoEscluso(): void
    {
        $this->rispondiPassaggio(RegimeDetrazioneIva::agricolo, null, [$this->bene('2024-03-10', 516.46), $this->bene('2024-03-10', 516.47)]);
        $this->assertCount(1, (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025)['beni']);
    }

    public function testBeneConCoefficienteElevatoFuoriDalTotale(): void
    {
        $normale = $this->bene('2024-03-10', 1000.0);
        $elevato = $this->bene('2024-04-10', 2000.0);
        $elevato->coefficienteElevato = true;
        $this->rispondiPassaggio(RegimeDetrazioneIva::agricolo, null, [$normale, $elevato]);
        $proposta = (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025);
        // resta in elenco, per poter togliere la spunta, ma non conta
        $this->assertCount(2, $proposta['beni']);
        $this->assertTrue($proposta['beni'][1]['escluso']);
        $this->assertSame(176, $proposta['totale']);
    }

    public function testSpuntaSalvataSoloSuiBeniInProposta(): void
    {
        $bene = $this->bene('2024-03-10', 1000.0);
        $bene->id = 7;
        $this->rispondiPassaggio(RegimeDetrazioneIva::agricolo, null, [$bene]);
        $prospetto = new ProspettoRettifiche($this->dataMapper);
        $proposta = $prospetto->getProposta($this->azienda, 2025);
        // un identificativo estraneo non ha effetto
        $this->assertSame(176, $prospetto->salvaEsclusi($proposta, [99 => '1'])['totale']);
        $aggiornata = $prospetto->salvaEsclusi($proposta, [7 => '1']);
        $this->assertTrue($bene->coefficienteElevato);
        $this->assertSame(0, $aggiornata['totale']);
    }

    public function testRigoDelCambioDiRegime(): void
    {
        $this->assertStringContainsString('mutamenti di regime', ProspettoRettifiche::RIGHI[3]);
    }

    public function testTraDueRegimiSpecialiNulla(): void
    {
        $this->rispondiPassaggio(RegimeDetrazioneIva::agricolo, RegimeDetrazioneIva::agriturismo, [$this->bene('2024-03-10', 1000.0)]);
        $this->assertSame([], (new ProspettoRettifiche($this->dataMapper))->getProposta($this->azienda, 2025)['beni']);
    }

    public function testTotaleDelProspettoNelVf70(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $dichiarazione->id = 77;
        $rigo = new RettificaDetrazione($this->dataMapper);
        $rigo->id = 1;
        $rigo->dichiarazione = $dichiarazione;
        $rigo->rigo = 2;
        $rigo->importo = -500.0;
        $altro = new RettificaDetrazione($this->dataMapper);
        $altro->id = 2;
        $altro->dichiarazione = $dichiarazione;
        $altro->rigo = 1;
        $altro->importo = 120.0;
        $this->rispondi(['dati_fiscali' => [$this->dati(null, 2020)], 'rettifica_detrazione' => [$rigo, $altro]]);
        $quadri = $this->creaQuadri($dichiarazione);
        (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri, []);
        $this->assertSame(-380, $quadri->getImporto('VF070002'));
        $this->assertSame(-380, $quadri->getImporto('VF071002'));
    }
}
