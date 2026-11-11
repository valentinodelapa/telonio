<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\Abbinamento;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\TipoChiusura;
use Contabilita\Application\Enumerations\TipoPartita;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Services\ComponentiReddito;
use Contabilita\Application\Services\PartiteAperte;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\TestResultSet;

/**
 * Partite aperte del partitario: gruppi della registrazione, modi di
 * chiusura, proposta dalla più vecchia, controlli degli abbinamenti e
 * quote incassate per cassa (ritenute, acconti, insoluti, abbuoni).
 */
class PartiteAperteTest extends ContabilitaTestCase
{

    private int $idRegistrazione = 1;
    private int $idPartita = 1;
    private int $idAbbinamento = 1;
    private Controparte $beta;

    /** @var Partita[] */
    private array $partite = [];

    /** @var Abbinamento[] */
    private array $abbinamenti = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->beta = $this->creaControparte(9, 'Beta', $this->conti['06.01.0001'], null);
        $this->testAdapter->impostaSelectCallback(fn(string $cmd) => new TestResultSet(match (true) {
            preg_match('/FROM\W+partita\W/', $cmd) === 1 => $this->partite,
            preg_match('/FROM\W+abbinamento\W/', $cmd) === 1 => $this->abbinamenti,
            default => [],
        }));
    }

    private function registrazione(CausaleContabile $causale, string $data): RegistrazioneContabile
    {
        $registrazione = $this->creaRegistrazione($causale, $this->beta, $data);
        $registrazione->id = $this->idRegistrazione++;
        return $registrazione;
    }

    private function riga(RegistrazioneContabile $registrazione, string $conto, float $dare, float $avere): RigaContabile
    {
        $riga = new RigaContabile($this->dataMapper);
        $riga->registrazioneContabile = $registrazione;
        $riga->soggetto = $this->azienda;
        $riga->dataRegistrazione = $registrazione->dataRegistrazione;
        $riga->conto = $this->conti[$conto];
        $riga->controparte = $conto === '06.01.0001' ? $this->beta : null;
        $riga->dare = $dare;
        $riga->avere = $avere;
        return $riga;
    }

    private function partita(TipoPartita $tipo, string $data, float $importo, ?Partita $origine = null): Partita
    {
        $partita = new Partita($this->dataMapper);
        $partita->id = $this->idPartita++;
        $partita->soggetto = $this->azienda;
        $partita->controparte = $this->beta;
        $partita->conto = $this->conti['06.01.0001'];
        $partita->registrazioneContabile = $this->registrazione($tipo === TipoPartita::documento ? CausaleContabile::fatturaVendita : CausaleContabile::incasso, $data);
        $partita->tipo = $tipo;
        $partita->data = new SismaDate($data);
        $partita->dataDocumento = new SismaDate($data);
        $partita->numeroDocumento = (string) $partita->id;
        $partita->importo = $importo;
        $partita->partitaOrigine = $origine;
        $this->partite[] = $partita;
        return $partita;
    }

    private function abbina(Partita $partita, TipoChiusura $tipo, string $data, float $importo, ?Partita $collegata = null): void
    {
        $abbinamento = new Abbinamento($this->dataMapper);
        $abbinamento->id = $this->idAbbinamento++;
        $abbinamento->soggetto = $this->azienda;
        $abbinamento->controparte = $this->beta;
        $abbinamento->partita = $partita;
        $abbinamento->registrazioneContabile = $this->registrazione(CausaleContabile::incasso, $data);
        $abbinamento->data = new SismaDate($data);
        $abbinamento->tipo = $tipo;
        $abbinamento->importo = $importo;
        $abbinamento->partitaCollegata = $collegata;
        $this->abbinamenti[] = $abbinamento;
    }

    public function testRipartizioneProporzionaleTraIModiDiChiusura(): void
    {
        // incasso di due parcelle da 1.000 con la ritenuta del 20 per cento
        $this->assertEquals([1 => ['DEN' => 800.0, 'RIT' => 200.0], 2 => ['DEN' => 800.0, 'RIT' => 200.0]],
                PartiteAperte::ripartisci([1 => 1000.0, 2 => 1000.0], ['DEN' => 1600.0, 'RIT' => 400.0]));
        // la più vecchia per prima, la seconda in parte
        $this->assertEquals([1 => ['DEN' => 1000.0], 2 => ['DEN' => 500.0]], PartiteAperte::ripartisci([1 => 1000.0, 2 => 1000.0], ['DEN' => 1500.0]));
        // importo oltre le partite aperte: l'eccedenza resta fuori
        $this->assertEquals([1 => ['DEN' => 300.0]], PartiteAperte::ripartisci([1 => 300.0], ['DEN' => 500.0]));
        $this->assertSame([], PartiteAperte::ripartisci([], ['DEN' => 500.0]));
    }

    public function testComposizioneDallaRegistrazione(): void
    {
        $this->conti[FunzioneConto::accontoIva->value]->funzione = FunzioneConto::ritenuteSubite;
        $incasso = $this->registrazione(CausaleContabile::incasso, '2025-03-01');
        $righe = [$this->riga($incasso, FunzioneConto::banca->value, 790, 0), $this->riga($incasso, FunzioneConto::accontoIva->value, 200, 0),
            $this->riga($incasso, '22.01.0001', 10, 0), $this->riga($incasso, '06.01.0001', 0, 1000)];
        $gruppi = (new PartiteAperte($this->dataMapper))->getGruppi($incasso, $righe);
        $this->assertCount(1, $gruppi);
        $gruppo = reset($gruppi);
        $this->assertFalse($gruppo['apertura']);
        $this->assertSame(-1000.0, $gruppo['importo']);
        $this->assertEquals(['DEN' => 790.0, 'RIT' => 200.0, 'ABB' => 10.0], $gruppo['composizione']);

        // nota di credito: tutta come nota
        $this->assertSame(['NC' => 400.0], PartiteAperte::composizione(CausaleContabile::notaCreditoEmessa, 400, 0, 400, 0));
        // compensazione con il debito verso lo stesso fornitore
        $this->assertSame(['CMP' => 300.0], PartiteAperte::composizione(CausaleContabile::giroconto, 300, 0, 0, 300));

        // fattura in scissione dei pagamenti: la partita è al netto dell'IVA stornata
        $fattura = $this->registrazione(CausaleContabile::fatturaVendita, '2025-03-01');
        $gruppi = (new PartiteAperte($this->dataMapper))->getGruppi($fattura, [$this->riga($fattura, '06.01.0001', 1220, 0),
            $this->riga($fattura, FunzioneConto::ricavi->value, 0, 1000), $this->riga($fattura, FunzioneConto::ivaVendite->value, 0, 220),
            $this->riga($fattura, FunzioneConto::ivaVendite->value, 220, 0), $this->riga($fattura, '06.01.0001', 0, 220)]);
        $this->assertSame(1000.0, reset($gruppi)['importo']);
        $this->assertTrue(reset($gruppi)['apertura']);
    }

    public function testProposteEControlli(): void
    {
        $prima = $this->partita(TipoPartita::documento, '2025-01-10', 1000);
        $seconda = $this->partita(TipoPartita::documento, '2025-02-10', 2000);
        $this->abbina($prima, TipoChiusura::denaro, '2025-01-30', 400);
        $servizio = new PartiteAperte($this->dataMapper);
        $abbinabili = $servizio->getAbbinabili($this->beta, $this->conti['06.01.0001'], false);
        $this->assertSame([600.0, 2000.0], array_values(array_map(fn(array $voce) => $voce['residuo'], $abbinabili)));

        // incasso di 3.000: chiude le due partite (600 + 2.000), 400 di eccedenza come acconto
        $incasso = $this->registrazione(CausaleContabile::incasso, '2025-03-01');
        $gruppi = $servizio->getGruppi($incasso, [$this->riga($incasso, FunzioneConto::banca->value, 3000, 0), $this->riga($incasso, '06.01.0001', 0, 3000)]);
        $scelte = $servizio->proponi($incasso, $gruppi);
        $chiave = array_key_first($gruppi);
        $this->assertEquals([$prima->id => ['DEN' => 600.0], $seconda->id => ['DEN' => 2000.0]], $scelte[$chiave]['abbinamenti']);
        $this->assertTrue($scelte[$chiave]['acconto']);
        $this->assertSame([], $servizio->verifica($incasso, $gruppi, $scelte));

        // senza acconto l'eccedenza non si salva
        $scelte[$chiave]['acconto'] = false;
        $this->assertStringContainsString('eccedenza come acconto', implode(' ', $servizio->verifica($incasso, $gruppi, $scelte)));
        // oltre il residuo della partita
        $scelte[$chiave] = ['abbinamenti' => [$prima->id => ['DEN' => 1000.0]], 'acconto' => true, 'riapre' => null];
        $this->assertStringContainsString('al massimo 600,00', implode(' ', $servizio->verifica($incasso, $gruppi, $scelte)));
        // gruppo senza scelta: abbinamento obbligatorio
        $this->assertStringContainsString('indicare l\'abbinamento', implode(' ', $servizio->verifica($incasso, $gruppi, [])));

        // fattura successiva: usa l'acconto aperto
        $acconto = $this->partita(TipoPartita::acconto, '2025-03-01', 400);
        $fattura = $this->registrazione(CausaleContabile::fatturaVendita, '2025-04-01');
        $gruppi = $servizio->getGruppi($fattura, [$this->riga($fattura, '06.01.0001', 1000, 0), $this->riga($fattura, FunzioneConto::ricavi->value, 0, 1000)]);
        $scelte = $servizio->proponi($fattura, $gruppi);
        $this->assertEquals([$acconto->id => ['ACC' => 400.0]], $scelte[array_key_first($gruppi)]['abbinamenti']);

        // l'insoluto riapre un documento solo da una registrazione senza documento
        $scelte[array_key_first($gruppi)]['riapre'] = $prima->id;
        $this->assertStringContainsString('insoluto', implode(' ', $servizio->verifica($fattura, $gruppi, $scelte)));
    }

    public function testQuoteIncassatePerCassa(): void
    {
        // parcella del 2025: 800 incassati e 200 di ritenuta nel 2025
        $parcella = $this->partita(TipoPartita::documento, '2025-01-10', 1000);
        $this->abbina($parcella, TipoChiusura::denaro, '2025-02-10', 800);
        $this->abbina($parcella, TipoChiusura::ritenuta, '2025-02-10', 200);
        // fattura del 2025: acconto di 500 del 2024, 1.000 incassati nel 2025, 500 di abbuono
        $acconto = $this->partita(TipoPartita::acconto, '2024-12-01', 500);
        $fattura = $this->partita(TipoPartita::documento, '2025-03-10', 2000);
        $this->abbina($fattura, TipoChiusura::acconto, '2025-03-10', 500, $acconto);
        $this->abbina($fattura, TipoChiusura::denaro, '2025-05-10', 1000);
        $this->abbina($fattura, TipoChiusura::abbuono, '2025-06-10', 500);
        // insoluto della parcella nel 2026, incassato per metà
        $insoluto = $this->partita(TipoPartita::insoluto, '2026-01-20', 1000, $parcella);
        $this->abbina($insoluto, TipoChiusura::denaro, '2026-03-01', 500);

        $quote2025 = ComponentiReddito::getQuoteAbbinate($this->partite, $this->abbinamenti, 2025);
        $this->assertEqualsWithDelta([$parcella->registrazioneContabile->id => 1.0, $fattura->registrazioneContabile->id => 0.5], $quote2025, 0.0001);
        $this->assertEqualsWithDelta([$fattura->registrazioneContabile->id => 0.25], ComponentiReddito::getQuoteAbbinate($this->partite, $this->abbinamenti, 2024), 0.0001);
        // nel 2026 l'insoluto toglie 1.000 e l'incasso ne ripristina 500
        $this->assertEqualsWithDelta([$parcella->registrazioneContabile->id => -0.5], ComponentiReddito::getQuoteAbbinate($this->partite, $this->abbinamenti, 2026), 0.0001);
    }

    public function testPerCassaConLePartite(): void
    {
        $ricavi = $this->conti[FunzioneConto::ricavi->value];
        $ricavi->voceCee = VoceCee::economicoA1;
        $consulenze = $this->conti['22.01.0001'];
        $consulenze->voceCee = VoceCee::economicoB7;
        $partita = $this->partita(TipoPartita::documento, '2025-01-10', 1220);
        $fattura = $partita->registrazioneContabile;
        $this->abbina($partita, TipoChiusura::denaro, '2025-02-10', 1000);
        $this->abbina($partita, TipoChiusura::abbuono, '2025-02-10', 220);
        $abbuono = $this->abbinamenti[1]->registrazioneContabile;
        $righe = [$this->riga($fattura, '06.01.0001', 1220, 0), $this->riga($fattura, FunzioneConto::ricavi->value, 0, 1000), $this->riga($fattura, FunzioneConto::ivaVendite->value, 0, 220),
            // abbuono: il costo non concorre (la parte non incassata non è tassata)
            $this->riga($abbuono, '22.01.0001', 220, 0), $this->riga($abbuono, '06.01.0001', 0, 220)];
        $id = 1;
        foreach ($righe as $riga) {
            $riga->id = $id++;
        }
        $this->testAdapter->impostaSelectCallback(fn(string $cmd) => new TestResultSet(match (true) {
            preg_match('/FROM\W+partita\W/', $cmd) === 1 => $this->partite,
            preg_match('/FROM\W+abbinamento\W/', $cmd) === 1 => $this->abbinamenti,
            default => $righe,
        }));
        $servizio = new ComponentiReddito($this->dataMapper);
        $componenti = $servizio->perCassa($this->azienda, 2025, true);
        $this->assertEqualsWithDelta(1000 * 1000 / 1220, $componenti['RE2'], 0.01);
        $this->assertSame(['RE2'], array_keys($componenti));
        $this->assertSame([], $servizio->getAvvisi());
    }
}
