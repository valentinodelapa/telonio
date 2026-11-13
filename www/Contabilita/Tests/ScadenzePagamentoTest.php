<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\Abbinamento;
use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Entities\ScadenzaPartita;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\ModalitaPagamento;
use Contabilita\Application\Enumerations\TipoChiusura;
use Contabilita\Application\Enumerations\TipoPartita;
use Contabilita\Application\Services\FatturaPa\LetturaFatturaPa;
use Contabilita\Application\Services\PartiteAperte;
use Contabilita\Application\Services\ScadenzePagamento;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Rate delle partite: termini della controparte, dati di pagamento della
 * fattura elettronica, controlli, residui per rata e scadenzario.
 */
class ScadenzePagamentoTest extends ContabilitaTestCase
{

    private const PAGAMENTI = '<DatiPagamento><CondizioniPagamento>TP01</CondizioniPagamento>'
            . '<DettaglioPagamento><ModalitaPagamento>MP05</ModalitaPagamento><DataScadenzaPagamento>2026-03-31</DataScadenzaPagamento><ImportoPagamento>660.00</ImportoPagamento><IBAN>IT60X0542811101000000123456</IBAN></DettaglioPagamento>'
            . '<DettaglioPagamento><ModalitaPagamento>MP12</ModalitaPagamento><DataRiferimentoTerminiPagamento>2026-02-05</DataRiferimentoTerminiPagamento><GiorniTerminiPagamento>30</GiorniTerminiPagamento><ImportoPagamento>660.00</ImportoPagamento></DettaglioPagamento>'
            . '</DatiPagamento>';

    private static function fatturaConPagamenti(): string
    {
        return str_replace('</DatiBeniServizi></FatturaElettronicaBody><FatturaElettronicaBody>', '</DatiBeniServizi>' . self::PAGAMENTI . '</FatturaElettronicaBody><FatturaElettronicaBody>', FatturaPaTest::xml());
    }

    public function testTerminiDellaControparte(): void
    {
        $this->assertSame([30, 60, 90], ScadenzePagamento::leggiGiorni('30,60,90'));
        $this->assertSame([], ScadenzePagamento::leggiGiorni(''));
        foreach (['60,30', '30,30', 'trenta', '800'] as $nonValido) {
            $this->assertNull(ScadenzePagamento::leggiGiorni($nonValido), $nonValido);
        }
        $beta = $this->creaControparte(9, 'Beta', $this->conti['06.01.0001'], null);
        $this->assertSame([], ScadenzePagamento::daTermini($beta, new \DateTimeImmutable('2026-01-10'), 100.0), 'senza termini nessuna rata');
        $beta->giorniPagamento = '30,60,90';
        $beta->fineMesePagamento = true;
        $beta->modalitaPagamento = ModalitaPagamento::riba;
        $rate = ScadenzePagamento::daTermini($beta, new \DateTimeImmutable('2026-01-10'), 100.0);
        $this->assertSame(['2026-02-28', '2026-03-31', '2026-04-30'], array_column($rate, 'data'), '30, 60 e 90 giorni fine mese dal 10 gennaio');
        $this->assertSame([33.33, 33.33, 33.34], array_column($rate, 'importo'));
        $this->assertSame('MP12', $rate[0]['modalita']);
        $beta->fineMesePagamento = false;
        $this->assertSame('2026-02-09', ScadenzePagamento::daTermini($beta, new \DateTimeImmutable('2026-01-10'), 100.0)[0]['data']);
    }

    public function testDatiDiPagamentoDellaFattura(): void
    {
        $corpo = (new LetturaFatturaPa())->leggi(self::fatturaConPagamenti())->getCorpo(1);
        $this->assertSame('TP01', $corpo->condizioniPagamento);
        $this->assertCount(2, $corpo->pagamenti);
        $this->assertSame(ModalitaPagamento::bonifico, $corpo->pagamenti[0]['modalita']);
        $this->assertSame('IT60X0542811101000000123456', $corpo->pagamenti[0]['iban']);
        $this->assertSame('2026-03-07', $corpo->pagamenti[1]['scadenza']->format('Y-m-d'), 'data di riferimento più 30 giorni');
        // in ordine di scadenza e riportate all'importo della partita
        $rate = ScadenzePagamento::daFattura($corpo->pagamenti, $corpo->data, 1000.0);
        $this->assertSame(['2026-03-07', '2026-03-31'], array_column($rate, 'data'));
        $this->assertSame([500.0, 500.0], array_column($rate, 'importo'));
        $this->assertSame(['MP12', 'MP05'], array_column($rate, 'modalita'));
        $this->assertSame([], ScadenzePagamento::daFattura([], $corpo->data, 1000.0));
    }

    public function testControlliEResidui(): void
    {
        $this->assertSame([['data' => '2026-01-31', 'importo' => 750.0, 'modalita' => null]],
                ScadenzePagamento::normalizza([['data' => '2026-01-31', 'importo' => 100.0, 'modalita' => null]], 750.0), 'la rata unica prende l\'importo della partita');
        $due = [['data' => '2026-02-28', 'importo' => 500.0, 'modalita' => null], ['data' => '2026-01-31', 'importo' => 250.0, 'modalita' => null]];
        $this->assertSame('2026-01-31', ScadenzePagamento::normalizza($due, 750.0)[0]['data']);
        $this->assertNull(ScadenzePagamento::verifica($due, 750.0));
        $this->assertStringContainsString('sommano 750,00 su un importo di 800,00', ScadenzePagamento::verifica($due, 800.0));
        $this->assertStringContainsString('data', ScadenzePagamento::verifica([['data' => '31/01/2026', 'importo' => 800.0, 'modalita' => null]], 800.0));
        $this->assertStringContainsString('modalità', ScadenzePagamento::verifica([['data' => '2026-01-31', 'importo' => 800.0, 'modalita' => 'MP99']], 800.0));
        $this->assertSame([0.0, 150.0, 300.0], ScadenzePagamento::residui([300.0, 300.0, 300.0], 450.0), 'gli incassi chiudono prima le rate più vecchie');
        $this->assertSame([0.0, 0.0], ScadenzePagamento::residui([300.0, 300.0], 600.0));
    }

    public function testRateNellaRegistrazioneENelloScadenzario(): void
    {
        $beta = $this->creaControparte(9, 'Beta', $this->conti['06.01.0001'], null);
        $beta->giorniPagamento = '30,60';
        $beta->fineMesePagamento = true;
        $partite = $abbinamenti = $rateSalvate = [];
        $this->testAdapter->impostaSelectCallback(function (string $cmd) use (&$partite, &$abbinamenti, &$rateSalvate) {
            return new TestResultSet(match (true) {
                preg_match('/FROM\W+partita\W/', $cmd) === 1 => $partite,
                preg_match('/FROM\W+abbinamento\W/', $cmd) === 1 => $abbinamenti,
                preg_match('/FROM\W+scadenza_partita\W/', $cmd) === 1 => $rateSalvate,
                default => [],
            });
        });
        $servizio = new PartiteAperte($this->dataMapper);
        $fattura = $this->creaRegistrazione(CausaleContabile::fatturaVendita, $beta, '2026-01-10');
        $righe = [];
        foreach ([['06.01.0001', 1220, 0], [FunzioneConto::ricavi->value, 0, 1000], [FunzioneConto::ivaVendite->value, 0, 220]] as [$conto, $dare, $avere]) {
            $riga = new RigaContabile($this->dataMapper);
            $riga->conto = $this->conti[$conto];
            $riga->controparte = $conto === '06.01.0001' ? $beta : null;
            $riga->dare = $dare;
            $riga->avere = $avere;
            $righe[] = $riga;
        }
        $gruppi = $servizio->getGruppi($fattura, $righe);
        $chiave = array_key_first($gruppi);
        // proposta dai termini della controparte
        $scelte = $servizio->proponi($fattura, $gruppi);
        $this->assertSame(['2026-02-28', '2026-03-31'], array_column($scelte[$chiave]['rate'], 'data'));
        $this->assertSame([610.0, 610.0], array_column($scelte[$chiave]['rate'], 'importo'));
        $this->assertSame([], $servizio->verifica($fattura, $gruppi, $scelte));
        // la fattura elettronica ha la precedenza sui termini della controparte
        $elettronica = new FatturaElettronica($this->dataMapper);
        $elettronica->contenuto = self::fatturaConPagamenti();
        $elettronica->indiceCorpo = 1;
        $this->assertSame(['2026-03-07', '2026-03-31'], array_column($servizio->proponi($fattura, $gruppi, $elettronica)[$chiave]['rate'], 'data'));
        // rate che non quadrano con la partita
        $scelte[$chiave]['rate'][1]['importo'] = 500.0;
        $this->assertStringContainsString('scadenze: le scadenze sommano 1.110,00', implode(' ', $servizio->verifica($fattura, $gruppi, $scelte)));

        // scrittura: una riga per rata e scadenza della partita alla prima rata
        $scelte = $servizio->proponi($fattura, $gruppi);
        $fattura->id = 50;
        $servizio->registra($fattura, $gruppi, $scelte);
        $inserimenti = array_values(array_filter($this->testAdapter->queryEseguite, fn(array $query) => str_starts_with($query['cmd'], 'INSERT') && str_contains($query['cmd'], 'scadenza_partita')));
        $this->assertCount(2, $inserimenti);

        // scadenzario: incasso di 800 su due rate da 610, la prossima scadenza è la seconda
        $partita = new Partita($this->dataMapper);
        $partita->id = 7;
        $partita->soggetto = $this->azienda;
        $partita->controparte = $beta;
        $partita->conto = $this->conti['06.01.0001'];
        $partita->registrazioneContabile = $fattura;
        $partita->tipo = TipoPartita::documento;
        $partita->data = new SismaDate('2026-01-10');
        $partita->dataScadenza = new SismaDate('2026-02-28');
        $partita->importo = 1220.0;
        $partite = [$partita];
        foreach ([['2026-02-28', 610.0], ['2026-03-31', 610.0]] as $indice => [$data, $importo]) {
            $rata = new ScadenzaPartita($this->dataMapper);
            $rata->id = $indice + 1;
            $rata->soggetto = $this->azienda;
            $rata->partita = $partita;
            $rata->numero = $indice + 1;
            $rata->dataScadenza = new SismaDate($data);
            $rata->importo = $importo;
            $rateSalvate[] = $rata;
        }
        $incasso = new Abbinamento($this->dataMapper);
        $incasso->id = 1;
        $incasso->soggetto = $this->azienda;
        $incasso->controparte = $beta;
        $incasso->partita = $partita;
        $incasso->registrazioneContabile = $this->creaRegistrazione(CausaleContabile::incasso, $beta, '2026-03-01');
        $incasso->data = new SismaDate('2026-03-01');
        $incasso->tipo = TipoChiusura::denaro;
        $incasso->importo = 800.0;
        $abbinamenti = [$incasso];
        $scadenzario = $servizio->getScadenzario($this->azienda);
        $voce = reset($scadenzario);
        $riga = $voce['partite'][0];
        $this->assertSame(420.0, $riga['residuo']);
        $this->assertSame([0.0, 420.0], array_column($riga['rate'], 'residuo'));
        $this->assertSame('2026-03-31', $riga['prossimaScadenza']->format('Y-m-d'));
        $this->assertSame([['data' => '2026-02-28', 'importo' => 610.0, 'modalita' => null], ['data' => '2026-03-31', 'importo' => 610.0, 'modalita' => null]], $servizio->getRate($partita));
    }
}
