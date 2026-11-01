<?php

namespace Contabilita\Tests;

use Contabilita\Application\Controllers\AziendaController;
use Contabilita\Application\Controllers\ContoController;
use Contabilita\Application\Controllers\FatturaElettronicaController;
use Contabilita\Application\Controllers\RegistrazioneController;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Services\RiepilogoScrivania;
use Contabilita\Application\Services\TabelleRiferimento\TabelleContabilita;
use SismaFramework\Core\Exceptions\AccessDeniedException;
use Telonio\Tests\TestResultSet;

class TabelleEControllersTest extends ContabilitaTestCase
{

    public function testSorgentiDelleTabelleDiRiferimento(): void
    {
        $tabelle = new TabelleContabilita();
        $dati = $tabelle->leggi();
        $this->assertCount(1, $dati['piani']);
        $this->assertGreaterThan(150, count($dati['conti']));
        $conti = array_column($dati['conti'], null, 'codice');
        $this->assertSame('CLI', $conti['06.01']['funzione']);
        $this->assertSame($conti['06']['id'], $conti['06.01']['parent_conto_id']);
        $this->assertSame('S', $conti['07.01.0001']['livello']);
        $codici = array_column($dati['codiciIva'], null, 'codice');
        $this->assertSame('22.00', $codici['22']['aliquota']);
        $this->assertSame(1, $codici['RC22']['inversione_contabile']);
        $this->assertSame('N6.3', $codici['N6.3']['natura']);
        $istruzioni = implode("\n", iterator_to_array($tabelle->istruzioni($dati), false));
        $this->assertStringContainsString('INSERT INTO `piano_conti`', $istruzioni);
        $this->assertStringContainsString('UPDATE `codice_iva` SET `attivo` = 0', $istruzioni);
    }

    public function testSorgentiNonValide(): void
    {
        $cartella = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'contabilita_' . bin2hex(random_bytes(4)) . DIRECTORY_SEPARATOR;
        mkdir($cartella);
        file_put_contents($cartella . 'piani-conti.csv', "id;denominazione;descrizione\n1;Prova;\n");
        file_put_contents($cartella . 'codici-iva.csv', implode(';', TabelleContabilita::COLONNE_CODICI_IVA) . "\n1;22;Ordinaria;22.00;;100;0;0;art. 16\n");
        $casi = [
            "1;1;01;Attivo;A;;\n1;2;01.01.0001;Salto di livello;A;;\n" => 'conto superiore',
            "1;1;01;Attivo;A;;\n1;2;01.01;Clienti;A;CLI;P.D.7\n" => 'voce CEE',
            "1;1;01;Attivo;A;;\n1;2;01.01;Clienti;A;IVV;\n" => 'funzione',
            "1;1;01;Attivo;A;;\n1;2;01.01;Clienti;A;CLI;\n" => 'Fornitori',
        ];
        try {
            foreach ($casi as $conti => $atteso) {
                file_put_contents($cartella . 'conti.csv', implode(';', TabelleContabilita::COLONNE_CONTI) . "\n" . $conti);
                try {
                    (new TabelleContabilita($cartella))->leggi();
                    $this->fail('Sorgente accettata: ' . $atteso);
                } catch (\UnexpectedValueException $errore) {
                    $this->assertStringContainsString($atteso, $errore->getMessage());
                }
            }
            file_put_contents($cartella . 'conti.csv', implode(';', TabelleContabilita::COLONNE_CONTI) . "\n1;1;01;Attivo;A;;\n1;2;01.01;Clienti;A;CLI;\n1;3;02;Passivo;P;;\n1;4;02.01;Fornitori;P;FOR;\n");
            file_put_contents($cartella . 'codici-iva.csv', implode(';', TabelleContabilita::COLONNE_CODICI_IVA) . "\n1;N4;Esenti;10.00;N4;100;0;0;art. 10\n");
            $this->expectExceptionMessage('la natura si indica solo senza aliquota');
            (new TabelleContabilita($cartella))->leggi();
        } finally {
            array_map('unlink', glob($cartella . '*'));
            rmdir($cartella);
        }
    }

    private function preparaController(array $tabelle): object
    {
        $this->autenticaUtente($this->utenteCorrente);
        $this->rispondiConti(['utente' => [$this->utenteCorrente]] + $tabelle);
        return $this->catturaRenderERouter();
    }

    public function testIngressoEAperturaDellaContabilita(): void
    {
        $cattura = $this->preparaController(['soggetto' => [$this->azienda]]);
        $controller = new AziendaController($this->dataMapper);
        $controller->index();
        $this->assertSame('azienda/index', $cattura->vista);
        $this->assertFalse($cattura->vars['aziende'][0]['aperta']);
        $controller->show($this->azienda);
        $this->assertSame('azienda/apri/soggetto/1', $cattura->redirectUrl);
        $controller->apri($this->creaRequest([], [], [], 'GET'), $this->azienda);
        $this->assertSame('azienda/apri', $cattura->vista);
        $this->assertSame(date('Y') . '-01-01', $cattura->vars['formEntity']->dataInizio->format('Y-m-d'), 'esercizio proposto: anno solare in corso');
    }

    public function testSchedaDellAziendaERicercaDeiConti(): void
    {
        $cattura = $this->preparaController(['piano_conti' => [$this->piano], 'esercizio_contabile' => [$this->esercizio], 'conto' => array_values($this->conti)]);
        (new AziendaController($this->dataMapper))->show($this->azienda, 2026);
        $this->assertSame('azienda/show', $cattura->vista);
        $this->assertSame($this->esercizio, $cattura->vars['esercizio']);
        (new ContoController($this->dataMapper))->cerca($this->azienda, 'banca');
        $this->assertNotEmpty($cattura->json);
        (new RegistrazioneController($this->dataMapper))->create($this->creaRequest([], [], [], 'GET'), $this->azienda, 2026, CausaleContabile::liquidazioneIva->value);
        $this->assertSame('azienda/show/soggetto/1/anno/2026', $cattura->redirectUrl, 'la liquidazione non si registra a mano');
        (new RegistrazioneController($this->dataMapper))->create($this->creaRequest([], [], [], 'GET'), $this->azienda, 2026, CausaleContabile::fatturaVendita->value);
        $this->assertSame('registrazione/form', $cattura->vista);
        $this->assertCount(2, $cattura->vars['datiIva']);
    }

    public function testAziendaDiAltroStudio(): void
    {
        $this->preparaController([]);
        $this->azienda->studio = $this->creaStudio(2, 'Altro studio', '98765432109');
        $this->expectException(AccessDeniedException::class);
        (new AziendaController($this->dataMapper))->show($this->azienda);
    }

    public function testFileCaricatiERiepilogo(): void
    {
        $this->assertSame([], FatturaElettronicaController::fileCaricati(null));
        $this->assertCount(1, FatturaElettronicaController::fileCaricati(['name' => 'a.xml', 'tmp_name' => '/tmp/a', 'error' => 0, 'size' => 1]));
        $this->assertCount(2, FatturaElettronicaController::fileCaricati(['name' => ['a.xml', 'b.p7m'], 'tmp_name' => ['/tmp/a', '/tmp/b'], 'error' => [0, 0], 'size' => [1, 2]]));
        $this->testAdapter->impostaSelectCallback(fn(string $cmd): TestResultSet => TestResultSet::conteggio(2));
        $indicatori = (new RiepilogoScrivania($this->dataMapper))->getIndicatori($this->studioPredefinito);
        $this->assertSame(2, $indicatori['Fatture elettroniche da registrare']);
    }
}
