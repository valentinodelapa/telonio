<?php

namespace Successioni\Tests\Services;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\TipoCatasto;
use CommSuite\Tests\SuiteTestCase;
use CommSuite\Tests\TestResultSet;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\TassoInteresseLegale;
use Deleghe\Application\Enumerations\ModalitaUtilizzo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\DevoluzioneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Entities\RipartizionePassivita;
use Successioni\Application\Enumerations\Agevolazione;
use Successioni\Application\Enumerations\CodiceDiritto;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\QuadroBene;
use Successioni\Application\Enumerations\RiduzioneSuccessioniPrecedenti;
use Successioni\Application\Enumerations\TempisticaPagamento;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Enumerations\TipoCespite;
use Successioni\Application\Enumerations\TipologiaTerreno;
use Successioni\Application\Enumerations\TipoPassivita;
use Successioni\Application\Services\DatiDichiarazione;
use Successioni\Application\Services\Liquidazione;
use Successioni\Application\Services\PianoVersamenti;
use Successioni\Application\Services\Ravvedimento;

class LiquidazioneAndVersamentiTest extends SuiteTestCase
{

    private function preparaDatiDichiarazione(): array
    {
        $defunto = $this->creaSoggettoFisico(10, $this->studioTest, 'RSSMRA50A01H501U', 'ROSSI', 'MARIO');
        $defunto->dataDecesso = new SismaDate('2025-02-01');

        $dichiarazione = new DichiarazioneSuccessione($this->dataMapper);
        $dichiarazione->id = 1;
        $dichiarazione->studio = $this->studioTest;
        $dichiarazione->defunto = $defunto;
        $dichiarazione->copiaConforme = true;
        $dichiarazione->tempisticaPagamento = TempisticaPagamento::nonContestuale;
        $dichiarazione->dataDichiarazione = new SismaDate('2025-06-01');

        $soggettoErede = $this->creaSoggettoFisico(11, $this->studioTest, 'RSSGPP80A01H501K', 'ROSSI', 'GIUSEPPE');
        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->id = 1;
        $beneficiario->dichiarazioneSuccessione = $dichiarazione;
        $beneficiario->soggetto = $soggettoErede;
        $beneficiario->tipoBeneficiario = TipoBeneficiario::erede;
        $beneficiario->gradoParentela = GradoParentela::figlio;

        $comune = new Comune($this->dataMapper);
        $comune->id = 1;
        $comune->denominazione = 'ROMA';
        $comune->siglaProvincia = 'RM';
        $comune->codiceCatastale = 'H501';

        $immobile = new Immobile($this->dataMapper);
        $immobile->id = 1;
        $immobile->studio = $this->studioTest;
        $immobile->tipoCatasto = TipoCatasto::fabbricati;
        $immobile->comune = $comune;
        $immobile->foglio = '10';
        $immobile->particella = '100';

        $catasto = new DatiCatastali($this->dataMapper);
        $catasto->id = 1;
        $catasto->immobile = $immobile;
        $catasto->categoria = CategoriaCatastale::a2;
        $catasto->rendita = 2000.0;
        $catasto->validoDal = new SismaDate('2020-01-01');

        $beneFab = new BeneSuccessione($this->dataMapper);
        $beneFab->id = 1;
        $beneFab->dichiarazioneSuccessione = $dichiarazione;
        $beneFab->quadro = QuadroBene::fabbricati;
        $beneFab->immobile = $immobile;
        $beneFab->codiceDiritto = CodiceDiritto::proprieta;
        $beneFab->valore = 1200000;
        $beneFab->valorePrecedente = 200000;

        $devFab = new DevoluzioneSuccessione($this->dataMapper);
        $devFab->id = 1;
        $devFab->beneSuccessione = $beneFab;
        $devFab->beneficiarioSuccessione = $beneficiario;
        $devFab->quotaNumeratore = 1;
        $devFab->quotaDenominatore = 1;
        $devFab->codiceDiritto = CodiceDiritto::proprieta;
        $devFab->valoreQuota = 1200000.0;
        $devFab->riduzione = RiduzioneSuccessioniPrecedenti::unAnno;

        $passivita = new PassivitaSuccessione($this->dataMapper);
        $passivita->id = 1;
        $passivita->dichiarazioneSuccessione = $dichiarazione;
        $passivita->tipoPassivita = TipoPassivita::speseFunerarie;
        $passivita->descrizione = 'Spese funebri';
        $passivita->estremiDocumento = 'Fatt. 1';
        $passivita->dataDocumento = new SismaDate('2025-02-05');
        $passivita->valore = 1000;

        $rip = new RipartizionePassivita($this->dataMapper);
        $rip->id = 1;
        $rip->passivitaSuccessione = $passivita;
        $rip->beneficiarioSuccessione = $beneficiario;
        $rip->quotaNumeratore = 1;
        $rip->quotaDenominatore = 1;
        $rip->valoreQuota = 1000.0;

        $this->adapter->impostaSelectCallback(function (string $query) use ($beneficiario, $beneFab, $devFab, $catasto, $passivita, $rip): TestResultSet {
            if (str_contains($query, 'FROM `beneficiario_successione`')) {
                return new TestResultSet([$beneficiario]);
            }
            if (str_contains($query, 'FROM `bene_successione`')) {
                return new TestResultSet([$beneFab]);
            }
            if (str_contains($query, 'FROM `devoluzione_successione`')) {
                return new TestResultSet([$devFab]);
            }
            if (str_contains($query, 'FROM `dati_catastali`')) {
                return new TestResultSet([$catasto]);
            }
            if (str_contains($query, 'FROM `passivita_successione`')) {
                return new TestResultSet([$passivita]);
            }
            if (str_contains($query, 'FROM `ripartizione_passivita`')) {
                return new TestResultSet([$rip]);
            }
            return new TestResultSet();
        });

        $dati = new DatiDichiarazione($dichiarazione, $this->dataMapper);
        return [$dichiarazione, $dati, $beneficiario, $beneFab, $devFab];
    }

    public function testLiquidazioneCompleta(): void
    {
        [$dichiarazione, $dati, $beneficiario] = $this->preparaDatiDichiarazione();
        $liquidazione = new Liquidazione();
        $risultato = $liquidazione->liquida($dati);

        $this->assertTrue($risultato->hasQuadroEF());
        $this->assertSame(1200000, $risultato->riepilogo['immobili']);
        $this->assertSame(1000, $risultato->riepilogo['passivo']);
        $this->assertSame(1199000, $risultato->riepilogo['netto']);

        // Ipotecaria 2% di 1.200.000 = 24.000; Catastale 1% = 12.000
        $this->assertSame(24000, $risultato->ipotecariaDaVersare);
        $this->assertSame(12000, $risultato->catastaleDaVersare);
        $this->assertSame(120, $risultato->tassaIpotecaria);
        // Bollo: 85 (1 circoscrizione) + 32 (copia conforme) = 117
        $this->assertSame(117, $risultato->bollo);
        $this->assertSame(16.0, $risultato->tributiSpeciali);

        // Autoliquidazione successione:
        // QE = 1.200.000, QP = 1.000 -> AN = 1.199.000
        // Franchigia figlio = 1.000.000 -> QN = 199.000
        // Presunzione 10% = 19.900 -> Imponibile = 218.900
        // Imposta lorda 4% = 8.756
        // Riduzione art. 25 (1 anno = 50% su 200.000): 200.000 * (8756 / 1200000) * 0.5 = 729.666...
        // Imposta netta = 8756 - 729.67 = 8026.33 -> arrotondata a 8026
        $quota = $risultato->getQuota($beneficiario);
        $this->assertNotNull($quota);
        $this->assertSame(199000.0, $quota->quotaNetta);
        $this->assertSame(19900.0, $quota->presunzione);
        $this->assertSame(8026, $risultato->successioneDaVersare);
        $this->assertSame(0, $liquidazione->getSuccessioneConDichiarazione($dati, $risultato));

        // Con pagamento contestuale l'imposta di successione entra nel totaleDaVersare
        $dichiarazione->tempisticaPagamento = TempisticaPagamento::contestuale;
        $risultatoContestuale = $liquidazione->liquida($dati);
        $this->assertSame(8026, $liquidazione->getSuccessioneConDichiarazione($dati, $risultatoContestuale));
    }

    public function testRavvedimento(): void
    {
        [$dichiarazione, $dati] = $this->preparaDatiDichiarazione();
        // Scadenza presentazione: 2026-02-01; presentazione tardiva di 20 giorni: 2026-02-21
        $dichiarazione->dataPresentazione = new SismaDate('2026-02-21');

        $tasso = new TassoInteresseLegale($this->dataMapper);
        $tasso->id = 1;
        $tasso->validoDal = new SismaDate('2025-01-01');
        $tasso->tasso = 2.0;
        $tasso->provvedimento = 'D.M.';

        $this->adapter->impostaSelectCallback(function (string $query) use ($tasso): TestResultSet {
            if (str_contains($query, 'FROM `tasso_interesse_legale`')) {
                return new TestResultSet([$tasso]);
            }
            return new TestResultSet();
        });

        $risultato = (new Liquidazione())->liquida($dati);
        (new Ravvedimento($this->dataMapper))->calcola($dati, $risultato);

        $this->assertSame(20, $risultato->giorniRitardo);
        $this->assertGreaterThan(0, $risultato->getTotaleSanzioni());
        $this->assertGreaterThan(0, $risultato->getTotaleInteressi());
        $this->assertGreaterThan(0, $risultato->sanzionePresentazione);

        $this->assertSame(1.25, Ravvedimento::getPercentualePagamento(20));
        $this->assertSame(1.39, Ravvedimento::getPercentualePagamento(60));
        $this->assertSame(3.12, Ravvedimento::getPercentualePagamento(200));
        $this->assertSame(3.57, Ravvedimento::getPercentualePagamento(400));
        $this->assertSame(4.5, Ravvedimento::getPercentualePresentazione(20));
        $this->assertSame(15.0, Ravvedimento::getPercentualePresentazione(200));
    }

    public function testPianoVersamenti(): void
    {
        [$dichiarazione, $dati] = $this->preparaDatiDichiarazione();
        $risultato = (new Liquidazione())->liquida($dati);

        $piano = new PianoVersamenti($this->dataMapper);
        $rateUnica = $piano->getRate($dichiarazione, $risultato);
        $this->assertCount(1, $rateUnica);
        $this->assertSame('0101', $rateUnica[0]['rateazione']);
        $this->assertSame($risultato->successioneDaVersare * 100, $rateUnica[0]['importo']);

        // Con rateazione (acconto 2000 euro + 4 rate trimestrali)
        $dichiarazione->numeroRate = 4;
        $dichiarazione->acconto = 2000;
        $rate = $piano->getRate($dichiarazione, $risultato);
        $this->assertCount(5, $rate);
        $this->assertSame(200000, $rate[0]['importo']);
        $this->assertSame('0104', $rate[1]['rateazione']);
        $this->assertGreaterThan(0, $rate[1]['interessi']);

        // Generazione e registrazione versamenti F24
        $creaCodice = function (int $id, string $codice): CodiceTributo {
            $ct = new CodiceTributo($this->dataMapper);
            $ct->id = $id;
            $ct->sezione = SezioneCodiceTributo::erario;
            $ct->codice = $codice;
            $ct->descrizione = 'Tributo ' . $codice;
            $ct->modalita = ModalitaUtilizzo::debito;
            $ct->formatoRateazione = $codice === '1539' ? 'NNRR' : '0000';
            return $ct;
        };

        $this->adapter->impostaSelectCallback(function (string $query, array $bindValues) use ($creaCodice): TestResultSet {
            if (str_contains($query, 'FROM `codice_tributo`')) {
                foreach ($bindValues as $valore) {
                    if (in_array($valore, ['1539', '1635', '1549', '1535', '1537'], true)) {
                        return new TestResultSet([$creaCodice((int) $valore, (string) $valore)]);
                    }
                }
            }
            return new TestResultSet();
        });

        $versamenti = $piano->getVersamenti($dati, $risultato);
        $this->assertArrayHasKey(11, $versamenti);
        // 1 acconto + 4 rate * (imposta + interessi) = 9 versamenti
        $this->assertCount(9, $versamenti[11]);

        $differenze = $piano->registra($dati, $risultato);
        $this->assertEmpty($differenze);

        $piano->annulla($dichiarazione);
        $this->assertEmpty($piano->getRegistrati($dichiarazione));
    }
}
