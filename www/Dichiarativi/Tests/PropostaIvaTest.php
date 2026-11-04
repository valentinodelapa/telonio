<?php

namespace Dichiarativi\Tests;

use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use Dichiarativi\Application\Services\Iva\VersamentiIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Proposta della dichiarazione IVA dai registri dell'anno, dal credito
 * dell'anno precedente in Deleghe e dalle formule delle specifiche.
 */
class PropostaIvaTest extends DichiarativiTestCase
{

    private function codiceTributo(int $id, string $codice): CodiceTributo
    {
        $tributo = new CodiceTributo($this->dataMapper);
        $tributo->id = $id;
        $tributo->sezione = SezioneCodiceTributo::erario;
        $tributo->codice = $codice;
        $tributo->descrizione = 'Tributo ' . $codice;
        return $tributo;
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

    public function testPropostaDaiRegistri(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $vendita->id = 1;
        $split = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-04-10');
        $split->id = 2;
        $split->esigibilita = EsigibilitaIva::scissionePagamenti;
        $differita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-05-10');
        $differita->id = 3;
        $differita->esigibilita = EsigibilitaIva::differita;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-06-10');
        $acquisto->id = 4;
        $credito = new Credito($this->dataMapper);
        $credito->id = 1;
        $credito->studio = $this->studioPredefinito;
        $credito->soggetto = $this->azienda;
        $credito->sezione = SezioneF24::erario;
        $credito->codiceTributo = $this->codiceTributo(6099, '6099');
        $credito->annoRiferimento = 2024;
        $credito->importo = 300.0;
        $utilizzo = new Versamento($this->dataMapper);
        $utilizzo->id = 1;
        $utilizzo->studio = $this->studioPredefinito;
        $utilizzo->soggetto = $this->azienda;
        $utilizzo->scadenza = new SismaDate('2025-02-17');
        $utilizzo->sezione = SezioneF24::erario;
        $utilizzo->codiceTributo = $this->codiceTributo(1001, '1001');
        $utilizzo->annoRiferimento = 2025;
        $utilizzo->importoCredito = 100.0;
        $utilizzo->credito = $credito;
        $this->rispondi([
            'dati_fiscali' => [$this->creaDatiFiscali()],
            'riga_iva' => [
                $this->creaRigaIva('22', 1000.0, 1, null, $vendita),
                $this->creaRigaIva('22', 500.0, 1, null, $split),
                $this->creaRigaIva('10', 300.0, 1, null, $differita),
                $this->creaRigaIva('A22', 500.0, 1, null, $acquisto),
                $this->creaRigaIva('RC22', 200.0, 1, null, $acquisto),
                $this->creaRigaIva('N4', 100.0, 1, null, $acquisto),
            ],
            'credito' => [$credito],
            'versamento' => [$utilizzo],
        ]);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        $attesi = [
            // vendite: 22% immediata nella sezione 2, scissione dei pagamenti nel VE38, esigibilità differita nel VE37
            'VE023001' => 1000, 'VE023002' => 220, 'VE024001' => 1000, 'VE024002' => 220, 'VE025002' => 0, 'VE026002' => 220,
            'VE037001' => 300, 'VE038001' => 500, 'VE050001' => 1800,
            // acquisti: 40% dell'auto e integrazione RC nel VF13, il 60% indetraibile nel VF21, esenti nel VF18
            'VF013001' => 400, 'VF013002' => 88, 'VF021001' => 300, 'VF018002' => 100, 'VF025001' => 800, 'VF025002' => 88,
            'VF026002' => 0, 'VF027002' => 88, 'VF029004' => 800, 'VF071002' => 88,
            'VJ012001' => 200, 'VJ012002' => 44, 'VJ019002' => 44,
            // VL1 = VE26 + VJ19; credito 2024 di 300 compensato per 100
            'VL001001' => 264, 'VL002001' => 88, 'VL003001' => 176, 'VL008001' => 300, 'VL009001' => 100, 'VL025001' => 200,
            'VL030001' => 0, 'VL032001' => 0, 'VL033001' => 24, 'VL039001' => 24,
            'VX001001' => 0, 'VX002001' => 24, 'VX005001' => 24,
        ];
        foreach ($attesi as $codice => $valore) {
            $this->assertSame($valore, $quadri->getImporto($codice), $codice);
        }
        $this->assertStringContainsString('ATECO', $avvisi[0]);
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'VJ12')));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'Credito IVA 2024')));
        // operazioni senza partita IVA del cliente verso consumatori finali; senza indirizzi la regione resta da indicare
        $this->assertSame(1000, $quadri->getImporto('VT001003'));
        $this->assertSame(0, $quadri->getImporto('VT001005'));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'luogo di esercizio')));
        $this->assertSame(['VT001003: indicato 1.000, risulta 0.', 'VT001004: indicato 220, risulta 0.'],
                (new \Dichiarativi\Application\Services\Iva\ControlliIva($this->dataMapper))->getSegnalazioni($quadri));
        $this->assertNotNull($dichiarazione->dataElaborazione);
    }

    public function testDebitoConVersamentiPeriodiciEInteressiTrimestrali(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-11-10');
        $vendita->id = 1;
        $periodico = new Versamento($this->dataMapper);
        $periodico->id = 2;
        $periodico->studio = $this->studioPredefinito;
        $periodico->soggetto = $this->azienda;
        $periodico->scadenza = new SismaDate('2025-05-16');
        $periodico->sezione = SezioneF24::erario;
        $periodico->codiceTributo = $this->codiceTributo(6031, '6031');
        $periodico->annoRiferimento = 2025;
        $periodico->importoDebito = 1000.0;
        $liquidazione = new \Contabilita\Application\Entities\LiquidazioneIva($this->dataMapper);
        $liquidazione->id = 1;
        $liquidazione->studio = $this->studioPredefinito;
        $liquidazione->soggetto = $this->azienda;
        $liquidazione->periodicita = \Anagrafica\Application\Enumerations\PeriodicitaIva::trimestrale;
        $liquidazione->anno = 2025;
        $liquidazione->periodo = 1;
        $liquidazione->ivaEsigibile = 1000.0;
        $liquidazione->importoDovuto = 1010.0;
        $liquidazione->interessi = 10.0;
        $this->rispondi([
            'dati_fiscali' => [$this->creaDatiFiscali(\Anagrafica\Application\Enumerations\PeriodicitaIva::trimestrale)],
            'riga_iva' => [$this->creaRigaIva('22', 10000.0, 1, null, $vendita)],
            'liquidazione_iva' => [$liquidazione],
            'versamento' => [$periodico],
        ]);
        $dichiarazione = $this->creaDichiarazione();
        $quadri = $this->creaQuadri($dichiarazione);
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($dichiarazione, $quadri);
        // IVA dell'anno 2.200 + interessi 10, dovute 1.010, versate 1.000 (delega non trasmessa: esclusa)
        $this->assertSame(2200, $quadri->getImporto('VL003001'));
        $this->assertSame(10, $quadri->getImporto('VL023001'));
        $this->assertSame(1010, $quadri->getImporto('VL030002'));
        $this->assertSame(0, $quadri->getImporto('VL030003'));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'VL30')));
        $this->assertSame(1200, $quadri->getImporto('VL032001'));
        // interessi dell'1% dei trimestrali sull'IVA da versare con la dichiarazione
        $this->assertSame(12, $quadri->getImporto('VL036001'));
        $this->assertSame(1212, $quadri->getImporto('VX001001'));
        // il quadro VH riporta il primo trimestre (comunicazione non inviata), la liquidazione dei mesi successivi manca
        $this->assertSame(1010, $quadri->getImporto('VH004002'));
        // credito compensabile dal 1° gennaio fino a 5.000 euro, oltre dal decimo giorno dopo la presentazione
        $this->assertSame('2026-01-01', VersamentiIva::getUtilizzabileDal($dichiarazione, 5000.0)->format('Y-m-d'));
        $dichiarazione->dataPresentazione = new SismaDate('2026-04-28');
        $this->assertSame('2026-05-08', VersamentiIva::getUtilizzabileDal($dichiarazione, 5000.01)->format('Y-m-d'));
    }
}
