<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Services\Redditi\CalcoloFabbricatiEnc;
use Dichiarativi\Application\Services\Redditi\CalcoloTerreniEnc;
use Dichiarativi\Application\Services\Redditi\RigoFabbricato;
use Dichiarativi\Application\Services\Redditi\RigoTerreno;
use PHPUnit\Framework\TestCase;

/**
 * Redditi dei terreni e dei fabbricati degli enti non commerciali secondo
 * le istruzioni del modello Redditi ENC 2026 (quadro RA, colonne 9 e 10;
 * quadro RB, colonna 10).
 */
class RedditiFondiariEncTest extends TestCase
{

    private function terreno(string $chiave, int $dominicale, int $titolo, int $agrario, ?int $canone = null, ?int $casi = null, int $giorni = 365): RigoTerreno
    {
        $rigo = new RigoTerreno($chiave, $dominicale, $titolo, $agrario, $giorni, 100.0);
        $rigo->canone = $canone;
        $rigo->casiParticolari = $casi;
        return $rigo;
    }

    private function terreni(RigoTerreno ...$righi): array
    {
        (new CalcoloTerreniEnc())->calcola($righi);
        return $righi[0]->redditi;
    }

    public function testTerreni(): void
    {
        // non affittato: il dominicale (x 1,80 x 1,30) concorre all'IRES, a differenza dell'IRPEF; agrario x 1,70 x 1,30
        $this->assertSame([9 => 234, 10 => 177], $this->terreni($this->terreno('a', 100, 1, 80)));
        // affittato in regime vincolistico: il canone se inferiore all'80 per cento del dominicale, senza reddito agrario
        $this->assertSame([9 => 150], $this->terreni($this->terreno('b', 100, 2, 80, canone: 150)));
        $this->assertSame([9 => 234], $this->terreni($this->terreno('b', 100, 2, 80, canone: 200)));
        // affitto a giovani agricoltori: senza la rivalutazione dell'80 per cento
        $this->assertSame([9 => 130], $this->terreni($this->terreno('c', 100, 3, 80, casi: 4)));
        // perdita di prodotto per eventi naturali: nessun reddito
        $this->assertSame([], $this->terreni($this->terreno('d', 100, 1, 80, casi: 2)));
        // conduttore del fondo: solo il reddito agrario
        $this->assertSame([10 => 177], $this->terreni($this->terreno('e', 100, 4, 80)));
        // coltivatore diretto o IAP nei dati fiscali: non rileva per l'ente
        $rigo = $this->terreno('f', 100, 1, 80);
        $rigo->iap = true;
        $this->assertSame([9 => 234, 10 => 177], $this->terreni($rigo));
    }

    public function testTerrenoSuPiuRighiETotali(): void
    {
        // proprietà per 200 giorni, poi affitto a libero mercato: importi sul primo rigo, nel rigo RA52 i totali
        $proprio = $this->terreno('a', 365, 1, 365, giorni: 200);
        $affittato = $this->terreno('a', 365, 3, 365, giorni: 165);
        $affittato->continuazione = true;
        $altro = $this->terreno('b', 100, 1, 80);
        $calcolo = new CalcoloTerreniEnc();
        $calcolo->calcola([$proprio, $affittato, $altro]);
        $this->assertSame([9 => 854, 10 => 442], $proprio->redditi);
        $this->assertSame([], $affittato->redditi);
        $this->assertSame([9 => 1088, 10 => 619], $calcolo->totali([$proprio, $affittato, $altro]));
    }

    private function fabbricato(string $chiave, int $rendita, int $utilizzo, int $giorni = 365, ?int $canone = null, int $spese = 0, bool $storico = false, ?int $casi = null): RigoFabbricato
    {
        $rigo = new RigoFabbricato($chiave, $rendita, $utilizzo, $giorni, 100.0);
        $rigo->canone = $canone;
        $rigo->spese = $spese;
        $rigo->storico = $storico;
        $rigo->casiParticolari = $casi;
        return $rigo;
    }

    private function fabbricati(RigoFabbricato ...$righi): array
    {
        (new CalcoloFabbricatiEnc())->calcola($righi);
        return $righi[0]->redditi;
    }

    public function testFabbricatiSuUnRigo(): void
    {
        // sede dell'attività istituzionale o altro utilizzo: rendita rivalutata del 5 per cento
        $this->assertSame([10 => 945], $this->fabbricati($this->fabbricato('a', 900, 9)));
        // unità distrutta o inagibile esclusa per legge: nessun reddito
        $this->assertSame([], $this->fabbricati($this->fabbricato('a', 900, 9, casi: 1)));
        // a disposizione: aumento di un terzo, non per gli immobili storici (rendita già al 50 per cento)
        $this->assertSame([10 => 1260], $this->fabbricati($this->fabbricato('b', 900, 2)));
        $this->assertSame([10 => 525], $this->fabbricati($this->fabbricato('b', 500, 2, storico: true)));
        // canone libero: il maggiore tra rendita e canone al netto delle spese di manutenzione ordinaria
        $this->assertSame([10 => 9500], $this->fabbricati($this->fabbricato('c', 500, 3, canone: 9500)));
        $this->assertSame([10 => 8500], $this->fabbricati($this->fabbricato('c', 500, 3, canone: 9500, spese: 1000)));
        $this->assertSame([10 => 525], $this->fabbricati($this->fabbricato('c', 500, 3, canone: 400)));
        // canone concordato: il maggiore ridotto del 30 per cento
        $this->assertSame([10 => 4655], $this->fabbricati($this->fabbricato('d', 600, 8, canone: 6650)));
        // equo canone: sempre il canone
        $this->assertSame([10 => 300], $this->fabbricati($this->fabbricato('e', 500, 4, canone: 300)));
        // comodato in Abruzzo: rendita ridotta del 30 per cento
        $this->assertSame([10 => 735], $this->fabbricati($this->fabbricato('f', 1000, 15)));
        // canoni non percepiti senza canone indicato: la rendita
        $this->assertSame([10 => 1050], $this->fabbricati($this->fabbricato('g', 1000, 3, casi: 4)));
    }

    public function testFabbricatoSuPiuRighiETotale(): void
    {
        // a disposizione per 200 giorni, poi locato: il maggiore tra il totale delle rendite (con l'aumento di un terzo) e il canone
        $disposizione = $this->fabbricato('a', 1000, 2, 200);
        $locato = $this->fabbricato('a', 1000, 3, 165, canone: 1000);
        $locato->continuazione = true;
        $calcolo = new CalcoloFabbricatiEnc();
        $calcolo->calcola([$disposizione, $locato]);
        $this->assertSame([10 => 1242], $disposizione->redditi);
        $this->assertSame([], $locato->redditi);
        // con un canone più alto, il canone
        $locato->canone = 5000;
        $calcolo->calcola([$disposizione, $locato]);
        $this->assertSame([10 => 5000], $disposizione->redditi);
        $altro = $this->fabbricato('b', 900, 9);
        $calcolo->calcola([$disposizione, $locato, $altro]);
        $this->assertSame(5945, $calcolo->totale([$disposizione, $locato, $altro]));
    }
}
