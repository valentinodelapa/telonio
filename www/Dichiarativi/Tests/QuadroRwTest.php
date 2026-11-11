<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Entities\AttivitaEstera;
use Dichiarativi\Application\Enumerations\CodiceInvestimentoEstero as Codice;
use Dichiarativi\Application\Services\Redditi\CalcoloQuadroRw;
use Dichiarativi\Application\Services\Redditi\PropostaEsteri;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use Dichiarativi\Application\Services\TabelleRiferimento\StatiEsteriRedditi;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Tests\SuiteTestCase;

/**
 * Quadro RW: IVAFE dei conti correnti (soglia di giacenza per
 * intermediario e soglia di monitoraggio) e degli altri prodotti
 * finanziari, IVIE degli immobili e dell'abitazione principale, imposta
 * sulle cripto-attività, solo monitoraggio; elenco degli Stati esteri;
 * acconti.
 */
class QuadroRwTest extends SuiteTestCase
{

    private function attivita(Codice $codice, ?string $stato, float $valore, float $quota = 100.0, array $altri = []): AttivitaEstera
    {
        $attivita = new AttivitaEstera($this->dataMapper);
        $attivita->annoImposta = 2025;
        $attivita->codiceInvestimento = $codice;
        $attivita->codiceStatoEstero = $stato;
        $attivita->valoreIniziale = $valore;
        $attivita->valoreFinale = $valore;
        $attivita->quota = $quota;
        foreach ($altri as $campo => $dato) {
            $attivita->$campo = $dato;
        }
        return $attivita;
    }

    public function testStatiEsteri(): void
    {
        $this->assertSame('FRANCIA', StatiEsteriRedditi::getDenominazione('029'));
        $this->assertSame('SVIZZERA', StatiEsteriRedditi::getDenominazione('071'));
        $this->assertTrue(StatiEsteriRedditi::isPrivilegiato('051'));
        $this->assertFalse(StatiEsteriRedditi::isPrivilegiato('029'));
        $this->assertFalse(StatiEsteriRedditi::esiste('999'));
    }

    public function testContiCorrenti(): void
    {
        // esempio delle istruzioni: giacenza complessiva pro quota 4.500 euro, niente IVAFE né monitoraggio sotto 15.000 euro
        $calcolo = new CalcoloQuadroRw();
        $righi = $calcolo->righi([$this->attivita(Codice::contiCorrenti, '071', 1000, 100, ['intermediario' => 'Banca A']),
            $this->attivita(Codice::contiCorrenti, '071', 7000, 50, ['intermediario' => 'banca a'])]);
        $this->assertSame([], $righi);
        $this->assertCount(1, $calcolo->getAvvisi());
        // secondo esempio: 5.500 euro, IVAFE di 17 e 34 euro e valore complessivo in col. 8
        $righi = $calcolo->righi([$this->attivita(Codice::contiCorrenti, '071', 5000, 50, ['intermediario' => 'Banca A']),
            $this->attivita(Codice::contiCorrenti, '071', 3000, 100, ['intermediario' => 'Banca A'])]);
        $this->assertSame([5500, 5500], array_column($righi, 8));
        $this->assertSame([17, 34], array_column($righi, 29));
        $this->assertSame([365, 365], array_column($righi, 10));
        $this->assertSame(5, $righi[0][14]);
    }

    public function testProdottiFinanziariEImmobili(): void
    {
        $calcolo = new CalcoloQuadroRw();
        $righi = $calcolo->righi([
            // titoli in un Paese a fiscalità privilegiata: 0,40 per cento, credito per l'imposta estera
            $this->attivita(Codice::depositoTitoli, '051', 100000, 100, ['creditoImposta' => 50.0, 'proventi' => 1200.0, 'tipoProventi' => 'I']),
            // immobile in Francia al 50 per cento dal 10 luglio: 6 mesi (luglio conta, 22 giorni)
            $this->attivita(Codice::immobili, '029', 200000, 50, ['dataInizio' => new SismaDate('2025-07-10')]),
            // immobile con l'1,06 per cento non oltre 200 euro
            $this->attivita(Codice::immobili, '029', 15000),
            // abitazione principale di lusso: 0,40 per cento e detrazione di 200 euro
            $this->attivita(Codice::abitazionePrincipale, '029', 300000),
            $this->attivita(Codice::criptoAttivita, null, 10000),
            $this->attivita(Codice::immobili, '029', 100000, 100, ['titoloPossesso' => 3]),
            $this->attivita(Codice::operedArte, '071', 50000),
        ]);
        $this->assertSame(400, $righi[0][29]);
        $this->assertSame(50, $righi[0][12]);
        $this->assertSame(350, $righi[0][30]);
        $this->assertTrue($righi[0][21]);
        $this->assertSame(2, $righi[0][14]);
        $this->assertSame(6, $righi[1][11]);
        $this->assertSame(530, $righi[1][31]);
        $this->assertSame(530, $righi[1][32]);
        $this->assertSame(0, $righi[2][31]);
        $this->assertSame(1200, $righi[3][31]);
        $this->assertSame(200, $righi[3][13]);
        $this->assertSame(1000, $righi[3][32]);
        $this->assertSame(20, $righi[4][33]);
        $this->assertNull($righi[4][4]);
        $this->assertTrue($righi[5][16]);
        $this->assertArrayNotHasKey(31, $righi[5]);
        $this->assertTrue($righi[6][16]);
    }

    public function testTipoRedditoRm31(): void
    {
        $this->assertSame('G', PropostaEsteri::tipoRedditoRm31($this->attivita(Codice::contiCorrenti, '071', 1000, 100, ['tipoProventi' => 'I'])));
        $this->assertSame('A', PropostaEsteri::tipoRedditoRm31($this->attivita(Codice::obbligazioni, '094', 1000, 100, ['tipoProventi' => 'I'])));
        $this->assertSame('H', PropostaEsteri::tipoRedditoRm31($this->attivita(Codice::depositoTitoli, '069', 1000, 100, ['tipoProventi' => 'D'])));
        $this->assertSame('I', PropostaEsteri::tipoRedditoRm31($this->attivita(Codice::polizze, '094', 1000, 100, ['tipoProventi' => 'A'])));
    }

    public function testAcconti(): void
    {
        $this->assertSame([0.0, 0.0], VersamentiRedditi::acconti(51, false));
        $this->assertSame([0.0, 200.0], VersamentiRedditi::acconti(200, false));
        $this->assertSame([400.0, 600.0], VersamentiRedditi::acconti(1000, false));
        $this->assertSame([500.0, 500.0], VersamentiRedditi::acconti(1000, true));
    }
}
