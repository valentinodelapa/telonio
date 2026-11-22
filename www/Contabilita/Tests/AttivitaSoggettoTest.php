<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\Attivita;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\NaturaAttivita;
use Contabilita\Application\Services\AttivitaSeparate;
use Contabilita\Application\Services\AttivitaSoggetto;
use Telonio\Tests\TestResultSet;

/**
 * Attività del soggetto come dimensione della contabilità: proposta
 * dell'attività delle righe, quota commerciale degli acquisti degli enti
 * non commerciali (art. 19-ter D.P.R. 633/1972), controlli e attività
 * separate dell'IVA solo con almeno due attività commerciali.
 */
class AttivitaSoggettoTest extends ContabilitaTestCase
{

    private Attivita $commerciale;
    private Attivita $istituzionale;

    private function attivita(int $id, int $numero, NaturaAttivita $natura): Attivita
    {
        $attivita = new Attivita($this->dataMapper);
        $attivita->id = $id;
        $attivita->studio = $this->azienda->studio;
        $attivita->soggetto = $this->azienda;
        $attivita->numero = $numero;
        $attivita->descrizione = $natura->getLabel();
        $attivita->natura = $natura;
        $attivita->dalAnno = 2020;
        return $attivita;
    }

    /**
     * Ente con un'attività commerciale e una istituzionale.
     */
    private function rispondiAttivita(Attivita ...$attivita): void
    {
        $this->testAdapter->impostaSelectCallback(fn(string $cmd) => new TestResultSet(preg_match('/FROM\W+attivita\W/', $cmd) === 1 ? $attivita : []));
    }

    private function riga(string $conto, float $dare, float $avere): RigaContabile
    {
        $riga = new RigaContabile($this->dataMapper);
        $riga->conto = $this->conti[$conto];
        $riga->dare = $dare;
        $riga->avere = $avere;
        return $riga;
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->commerciale = $this->attivita(401, 1, NaturaAttivita::commerciale);
        $this->istituzionale = $this->attivita(402, 2, NaturaAttivita::istituzionale);
    }

    public function testQuotaCommercialeDegliAcquistiDegliEnti(): void
    {
        $elenco = [1 => $this->commerciale, 2 => $this->istituzionale];
        // registro dell'attività: per intero o per niente; acquisto comune: somma delle quote commerciali; senza quote: da indicare
        $this->assertSame(1.0, AttivitaSoggetto::getQuotaCommerciale($this->commerciale, [], $elenco));
        $this->assertSame(0.0, AttivitaSoggetto::getQuotaCommerciale($this->istituzionale, [], $elenco));
        $this->assertSame(0.6, AttivitaSoggetto::getQuotaCommerciale(null, [1 => 60.0, 2 => 40.0], $elenco));
        $this->assertNull(AttivitaSoggetto::getQuotaCommerciale(null, [], $elenco));
        // l'imposta detraibile tiene conto della quota: 22% di 5.000 al 60 per cento
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-06-30');
        $riga = $this->creaRigaIva('22', 5000.0, 1, null, $acquisto);
        $riga->ripartizione = '1:60;2:40';
        $this->rispondiAttivita($this->commerciale, $this->istituzionale);
        (new AttivitaSoggetto($this->dataMapper))->applicaQuotaCommerciale($acquisto, [$riga]);
        $this->assertSame(0.6, $riga->quotaCommerciale);
        $this->assertSame(660.0, $riga->getImpostaDetraibile());
        $this->assertSame(440.0, $riga->getImpostaIndetraibile());
    }

    public function testControlliDelleRighe(): void
    {
        $this->rispondiAttivita($this->commerciale, $this->istituzionale);
        $servizio = new AttivitaSoggetto($this->dataMapper);
        // acquisto comune di un ente senza quote: errore (art. 19-ter, criteri oggettivi)
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-06-30');
        $riga = $this->creaRigaIva('22', 1000.0, 1, null, $acquisto);
        $errori = $servizio->verifica($acquisto, [$riga], []);
        $this->assertCount(1, $errori);
        $this->assertStringContainsString('19-ter', $errori[0]);
        $riga->ripartizione = '1:50;2:50';
        $this->assertSame([], $servizio->verifica($acquisto, [$riga], []));
        // acquisto nel registro dell'attività commerciale: nessuna quota richiesta
        $acquisto->registroIva->attivita = $this->commerciale;
        $riga->ripartizione = null;
        $this->assertSame([], $servizio->verifica($acquisto, [$riga], []));
        $acquisto->registroIva->attivita = null;
        // ricavo senza attività: errore; con l'attività predefinita del conto: proposto
        $giroconto = $this->creaRegistrazione(CausaleContabile::giroconto, null, '2025-03-10');
        $ricavo = $this->riga(FunzioneConto::ricavi->value, 0.0, 1000.0);
        $banca = $this->riga(FunzioneConto::banca->value, 1000.0, 0.0);
        $this->assertStringContainsString('attività del ricavo', $servizio->verifica($giroconto, [], [$banca, $ricavo])[0]);
        $ricavo->conto->attivita = $this->commerciale;
        $this->assertSame([], $servizio->verifica($giroconto, [], [$banca, $ricavo]));
        // ricavo indicato come comune: errore anche con l'attività del conto
        $ricavo->ripartizione = '';
        $this->assertCount(1, $servizio->verifica($giroconto, [], [$banca, $ricavo]));
        // quote che non sommano 100
        $costo = $this->riga('22.01.0001', 300.0, 0.0);
        $costo->ripartizione = '1:30;2:30';
        $this->assertStringContainsString('sommare 100', implode(' ', $servizio->verifica($giroconto, [], [$costo])));
        $ricavo->conto->attivita = null;
    }

    public function testProposteDelleRighe(): void
    {
        $this->rispondiAttivita($this->commerciale, $this->istituzionale);
        $servizio = new AttivitaSoggetto($this->dataMapper);
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-06-30');
        $rigaIva = $this->creaRigaIva('22', 5000.0, 1, null, $acquisto);
        $rigaIva->conto = $this->conti['22.01.0001'];
        $rigaIva->ripartizione = '1:60;2:40';
        $costo = $this->riga('22.01.0001', 5440.0, 0.0);
        $fornitore = $this->riga('13.01.0001', 0.0, 6100.0);
        $servizio->applica($acquisto, [$rigaIva], [$costo, $fornitore]);
        // costo comune con le quote della riga IVA dello stesso conto, riga patrimoniale senza attività
        $this->assertNull($costo->attivita);
        $this->assertSame('1:60;2:40', $costo->ripartizione);
        $this->assertNull($fornitore->attivita);
        // attività del registro IVA prima di quella del conto
        $acquisto->registroIva->attivita = $this->istituzionale;
        $costo->ripartizione = null;
        $costo->conto->attivita = $this->commerciale;
        $servizio->applica($acquisto, [$rigaIva], [$costo]);
        $this->assertSame($this->istituzionale, $costo->attivita);
        $this->assertSame(0.0, $rigaIva->quotaCommerciale);
        // riga indicata come comune: nessuna proposta dal conto
        $acquisto->registroIva->attivita = null;
        $costo->attivita = null;
        $costo->ripartizione = '';
        $servizio->applica($acquisto, [], [$costo]);
        $this->assertNull($costo->attivita);
        $costo->conto->attivita = null;
    }

    public function testAttivitaSeparateDellIva(): void
    {
        // l'attività istituzionale non è un'attività separata dell'IVA: con una sola commerciale la contabilità IVA è unificata
        $this->assertSame([], AttivitaSeparate::getSeparate([1 => $this->commerciale, 2 => $this->istituzionale]));
        $seconda = $this->attivita(403, 3, NaturaAttivita::commerciale);
        $this->assertSame([1, 3], array_keys(AttivitaSeparate::getSeparate([1 => $this->commerciale, 2 => $this->istituzionale, 3 => $seconda])));
        // quote IVA delle attività separate riportate a 1 escludendo la parte istituzionale
        $this->rispondiAttivita($this->commerciale, $this->istituzionale, $seconda);
        $acquisto = $this->creaRegistrazione(CausaleContabile::fatturaAcquisto, null, '2025-06-30');
        $riga = $this->creaRigaIva('22', 1000.0, 1, null, $acquisto);
        $riga->ripartizione = '1:50;2:20;3:30';
        $riga->quotaCommerciale = 0.8;
        $quote = (new AttivitaSeparate($this->dataMapper))->getQuote($riga, $this->azienda, 2025);
        $this->assertEqualsWithDelta([1 => 0.625, 3 => 0.375], $quote, 0.0001);
    }
}
