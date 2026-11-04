<?php

namespace Dichiarativi\Tests;

use Anagrafica\Application\Entities\Comune;
use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Contabilita\Application\Entities\CodiceIva;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use Dichiarativi\Application\Enumerations\TipoModulo;
use Dichiarativi\Application\Services\Iva\ControlliIva;
use Dichiarativi\Application\Services\Iva\PropostaIva;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Qualifiche delle operazioni (VF34, VE40), aliquota teorica degli acquisti
 * con dichiarazione d'intento (VF35), importazioni (VF28, VC colonna 2),
 * regioni del VT dalle unità locali dei registri, data dell'operazione
 * straordinaria nei moduli, verifica del plafond e visto del CAF.
 */
class QualificheImportazioniPlafondTest extends DichiarativiTestCase
{

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $codice = new CodiceIva($this->dataMapper);
        $codice->id = 40;
        $codice->codice = 'N3.5';
        $codice->descrizione = 'Non imponibile con dichiarazione d\'intento';
        $codice->aliquota = 0.0;
        $codice->natura = NaturaIva::dichiarazioniIntento;
        $codice->detraibilita = 100.0;
        $codice->inversioneContabile = false;
        $this->codiciIva['N3.5'] = $codice;
    }

    /**
     * Risponde con le tabelle indicate; le righe IVA con data successiva
     * alla fine del periodo richiesto sono escluse come nella query reale.
     */
    private function rispondi(array $tabelle): void
    {
        $this->testAdapter->impostaSelectCallback(function (string $cmd, array $bind) use ($tabelle): TestResultSet {
            if (str_contains($cmd, 'COUNT(')) {
                return TestResultSet::conteggio(0);
            }
            $tabella = preg_match('/FROM `([a-z_]+)`/', $cmd, $trovata) === 1 ? $trovata[1] : '';
            $righe = $tabelle[$tabella] ?? [];
            if ($tabella === 'riga_iva') {
                $date = array_map(fn($valore) => $valore instanceof \DateTimeInterface ? $valore->format('Y-m-d') : (string) (is_scalar($valore) ? $valore : ''), $bind);
                $date = array_filter($date, fn(string $data) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) === 1);
                if (count($date) > 0) {
                    $fine = max($date);
                    $righe = array_values(array_filter($righe, fn($riga) => $riga->dataCompetenzaIva->format('Y-m-d') <= $fine));
                }
            }
            return new TestResultSet($righe);
        });
    }

    private function sede(int $id, TipoIndirizzo $tipo, string $provincia): Indirizzo
    {
        $comune = new Comune($this->dataMapper);
        $comune->id = $id;
        $comune->codiceCatastale = 'X' . $id;
        $comune->denominazione = 'Comune ' . $provincia;
        $comune->siglaProvincia = $provincia;
        $indirizzo = new Indirizzo($this->dataMapper);
        $indirizzo->id = $id;
        $indirizzo->soggetto = $this->azienda;
        $indirizzo->tipoIndirizzo = $tipo;
        $indirizzo->validoDal = new SismaDate('2020-01-01');
        $indirizzo->comune = $comune;
        return $indirizzo;
    }

    public function testQualificheAliquotaTeoricaEImportazioni(): void
    {
        $vendita = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $vendita->id = 1;
        $importazione = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-04-10');
        $importazione->id = 2;
        $importazione->importazione = true;
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-05-10');
        $acquisto->id = 3;
        $occasionale = $this->creaRigaIva('N4', 500.0, 1, null, $vendita);
        $occasionale->qualifica = QualificaOperazione::esenteOccasionale;
        $ecografo = $this->creaRigaIva('22', 2000.0, 1, null, $vendita);
        $ecografo->qualifica = QualificaOperazione::beneAmmortizzabile;
        $componenti = $this->creaRigaIva('N3.5', 2000.0, 1, null, $importazione);
        $componenti->aliquotaTeorica = 10.0;
        $this->rispondi([
            'dati_fiscali' => [$this->creaDatiFiscali()],
            'riga_iva' => [$this->creaRigaIva('22', 1000.0, 1, null, $vendita), $this->creaRigaIva('N4', 9000.0, 1, null, $vendita), $occasionale, $ecografo,
                $componenti, $this->creaRigaIva('22', 1000.0, 1, null, $importazione), $this->creaRigaIva('N3.5', 1000.0, 1, null, $acquisto)],
        ]);
        $quadri = $this->creaQuadri();
        $avvisi = (new PropostaIva($this->dataMapper))->proponi($this->creaDichiarazione(), $quadri);
        // bene ammortizzabile nel VE40, esente occasionale nel VF34 campo 2: pro-rata 1.000 / 10.000
        $this->assertSame(2000, $quadri->getImporto('VE040001'));
        $this->assertSame(500, $quadri->getImporto('VF034002'));
        $this->assertSame(10, $quadri->getImporto('VF034009'));
        // importazioni con e senza dichiarazione d'intento
        $this->assertSame(3000, $quadri->getImporto('VF028003'));
        $this->assertSame(220, $quadri->getImporto('VF028004'));
        // IVA non assolta: 10% teorico sui componenti, 22% predefinito sull'altro acquisto
        $this->assertSame(3000, $quadri->getImporto('VF017001'));
        $this->assertSame(420, $quadri->getImporto('VF035001'));
        $this->assertNotEmpty(array_filter($avvisi, fn(string $avviso) => str_contains($avviso, 'calcolata al 22%')));
        // VC: acquisti interni in colonna 1, importazioni in colonna 2, colonne 5 e 6 solo con il metodo mensile
        $this->assertSame(1000, $quadri->getImporto('VC005001'));
        $this->assertSame(2000, $quadri->getImporto('VC004002'));
        $this->assertSame(1000, $quadri->getImporto('VC013001'));
        $this->assertSame(2000, $quadri->getImporto('VC013002'));
        $this->assertNull($quadri->get('VC013005'));
        $this->assertSame('1', $quadri->get('VC014002'));
    }

    public function testRegioniDelVtDalleUnitaLocali(): void
    {
        $piacenza = $this->sede(2, TipoIndirizzo::sedeOperativa, 'PC');
        $sezionale = new RegistroIva($this->dataMapper);
        $sezionale->id = 9;
        $sezionale->studio = $this->studioPredefinito;
        $sezionale->soggetto = $this->azienda;
        $sezionale->tipo = TipoRegistroIva::vendite;
        $sezionale->sezionale = 'PC';
        $sezionale->descrizione = 'Ambulatorio di Piacenza';
        $sezionale->unitaLocale = $piacenza;
        $milano = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $milano->id = 1;
        $sedePiacenza = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-12');
        $sedePiacenza->id = 2;
        $sedePiacenza->registroIva = $sezionale;
        $this->rispondi([
            'dati_fiscali' => [$this->creaDatiFiscali()],
            'riga_iva' => [$this->creaRigaIva('22', 10000.0, 1, null, $milano), $this->creaRigaIva('22', 3000.0, 1, null, $sedePiacenza)],
            // la sede operativa del sezionale non è il luogo di esercizio degli altri registri
            'indirizzo' => [$this->sede(1, TipoIndirizzo::sedeLegale, 'MI'), $piacenza],
            'registro_iva' => [$sezionale],
        ]);
        $quadri = $this->creaQuadri();
        (new PropostaIva($this->dataMapper))->proponi($this->creaDichiarazione(), $quadri);
        $this->assertSame(13000, $quadri->getImporto('VT001003'));
        $this->assertSame(10000, $quadri->getImporto('VT011001'));
        $this->assertSame(2200, $quadri->getImporto('VT011002'));
        $this->assertSame(3000, $quadri->getImporto('VT007001'));
        $this->assertSame(660, $quadri->getImporto('VT007002'));
    }

    public function testDataDellOperazioneStraordinariaLimitaIlModulo(): void
    {
        $marzo = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-03-10');
        $marzo->id = 1;
        $settembre = $this->creaRegistrazione(CausaleContabile::fatturaVendita, null, '2025-09-10');
        $settembre->id = 2;
        $this->rispondi([
            'dati_fiscali' => [$this->creaDatiFiscali()],
            'riga_iva' => [$this->creaRigaIva('22', 1000.0, 1, null, $marzo), $this->creaRigaIva('22', 4000.0, 1, null, $settembre)],
        ]);
        $modulo = new ModuloDichiarazione($this->dataMapper);
        $modulo->numero = 2;
        $modulo->tipo = TipoModulo::soggettoTrasformato;
        $modulo->soggettoFonte = $this->azienda;
        $modulo->dataOperazione = new SismaDate('2025-07-01');
        $quadri = $this->creaQuadri();
        (new PropostaIva($this->dataMapper))->proponi($this->creaDichiarazione(), $quadri, [$modulo]);
        $this->assertSame(5000, $quadri->getImporto('VE023001', 1));
        // il soggetto trasformato fino al 30 giugno
        $this->assertSame(1000, $quadri->getImporto('VE023001', 2));
        $this->assertSame('01234567897', $quadri->get('VA001001', 2));
    }

    public function testVerificaDelPlafond(): void
    {
        $vuoto = ['acquisti' => array_fill(1, 12, 0.0), 'importazioni' => array_fill(1, 12, 0.0), 'volume' => array_fill(1, 12, 0.0), 'esportazioni' => array_fill(1, 12, 0.0)];
        // solare: 10.000 disponibili, 6.000 a febbraio e 5.000 a marzo
        $corrente = $vuoto;
        $corrente['acquisti'][2] = 6000.0;
        $corrente['importazioni'][3] = 5000.0;
        $avvisi = PropostaIva::verificaPlafond($corrente, $vuoto, false, 10000);
        $this->assertCount(1, $avvisi);
        $this->assertStringContainsString('plafond superato a marzo (utilizzati € 5.000, disponibili € 4.000)', $avvisi[0]);
        $this->assertSame([], PropostaIva::verificaPlafond($corrente, $vuoto, false, 11000));
        // mensile: 1.000 di esportazioni al mese nell'anno precedente, 12.000 utilizzati a gennaio
        $precedente = $vuoto;
        $precedente['esportazioni'] = array_fill(1, 12, 1000.0);
        $corrente = $vuoto;
        $corrente['acquisti'][1] = 12000.0;
        $this->assertSame([], PropostaIva::verificaPlafond($corrente, $precedente, true, 12000));
        // a febbraio i dodici mesi precedenti danno 11.000 - 12.000: nessuna disponibilità
        $corrente['acquisti'][2] = 100.0;
        $avvisi = PropostaIva::verificaPlafond($corrente, $precedente, true, 12000);
        $this->assertStringContainsString('plafond superato a febbraio', $avvisi[0]);
        // con il solare gli stessi acquisti superano il plafond annuale solo a febbraio
        $this->assertStringContainsString('plafond superato a febbraio', PropostaIva::verificaPlafond($corrente, $precedente, false, 12000)[0]);
    }

    public function testVistoDelCaf(): void
    {
        $dichiarazione = $this->creaDichiarazione();
        $caf = $this->creaSoggettoFisico(20, $this->studioPredefinito, '09889900008', 'CAF Imprese', '');
        $caf->tipoSoggetto = TipoSoggetto::societaCapitali;
        $responsabile = $this->creaSoggettoFisico(21, $this->studioPredefinito, 'CLMNNA75D45F205G', 'Colombo', 'Anna');
        $professionista = $this->creaSoggettoFisico(22, $this->studioPredefinito, 'MRNPLA70A01F205L', 'Marini', 'Paolo');
        $controlli = new ControlliIva($this->dataMapper);
        $messaggioCaf = 'Visto del CAF: indicare il CAF (codice fiscale numerico) e il responsabile dell\'assistenza fiscale (persona fisica).';
        $messaggioDoppio = 'Il visto di conformità è del professionista o del CAF, non di entrambi.';
        $dichiarazione->vistoCaf = $caf;
        $this->assertContains($messaggioCaf, $controlli->getErrori($dichiarazione, $this->creaQuadri($dichiarazione)));
        $this->assertTrue($dichiarazione->hasVisto());
        $dichiarazione->vistoResponsabileCaf = $responsabile;
        $errori = $controlli->getErrori($dichiarazione, $this->creaQuadri($dichiarazione));
        $this->assertNotContains($messaggioCaf, $errori);
        $this->assertNotContains($messaggioDoppio, $errori);
        $dichiarazione->vistoProfessionista = $professionista;
        $this->assertContains($messaggioDoppio, $controlli->getErrori($dichiarazione, $this->creaQuadri($dichiarazione)));
        // il CAF ha un codice fiscale numerico
        $dichiarazione->vistoProfessionista = null;
        $dichiarazione->vistoCaf = $professionista;
        $this->assertContains($messaggioCaf, $controlli->getErrori($dichiarazione, $this->creaQuadri($dichiarazione)));
    }
}
