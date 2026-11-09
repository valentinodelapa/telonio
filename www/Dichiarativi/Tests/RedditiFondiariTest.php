<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Services\Redditi\CalcoloFabbricati;
use Dichiarativi\Application\Services\Redditi\CalcoloTerreni;
use Dichiarativi\Application\Services\Redditi\RigoFabbricato;
use Dichiarativi\Application\Services\Redditi\RigoTerreno;
use PHPUnit\Framework\TestCase;

/**
 * Redditi dei fabbricati e dei terreni secondo le specifiche tecniche del
 * modello Redditi PF 2026 (par. 6.2-6.3 e 7.2).
 */
class RedditiFondiariTest extends TestCase
{

    private function fabbricato(string $chiave, int $rendita, int $utilizzo, int $giorni = 365, float $percentuale = 100.0, ?int $canone = null, ?int $cedolare = null, ?int $casiImu = null): RigoFabbricato
    {
        $rigo = new RigoFabbricato($chiave, $rendita, $utilizzo, $giorni, $percentuale);
        $rigo->canone = $canone;
        $rigo->codiceCanone = $canone === null ? null : ($cedolare === null ? 1 : 3);
        $rigo->cedolare = $cedolare;
        $rigo->casiImu = $casiImu;
        return $rigo;
    }

    private function redditi(RigoFabbricato ...$righi): array
    {
        (new CalcoloFabbricati())->calcola($righi);
        return $righi[0]->redditi;
    }

    public function testFabbricatiSuUnRigo(): void
    {
        // abitazione principale: 1,05 x rendita nella colonna 19 (deduzione RN2); di lusso soggetta a IMU nella 17
        $this->assertSame([19 => 1050], $this->redditi($this->fabbricato('a', 1000, 1)));
        $this->assertSame([17 => 1050], $this->redditi($this->fabbricato('a', 1000, 1, casiImu: 2)));
        // a disposizione: maggiorazione di un terzo, IRPEF sostituita dall'IMU; nel comune dell'abitazione principale al 50%
        $this->assertSame([18 => 1260], $this->redditi($this->fabbricato('b', 900, 2)));
        $this->assertSame([13 => 630, 18 => 630], $this->redditi($this->fabbricato('b', 900, 2, casiImu: 3)));
        $this->assertSame([13 => 1260], $this->redditi($this->fabbricato('b', 900, 2, casiImu: 1)));
        // comodato a un familiare: senza maggiorazione
        $this->assertSame([18 => 945], $this->redditi($this->fabbricato('b', 900, 10)));
        // canone libero: il maggiore tra rendita e canone per la quota di possesso
        $this->assertSame([13 => 9500], $this->redditi($this->fabbricato('c', 500, 3, canone: 9500)));
        $this->assertSame([13 => 4750], $this->redditi($this->fabbricato('c', 500, 3, percentuale: 50, canone: 9500)));
        $this->assertSame([13 => 525], $this->redditi($this->fabbricato('c', 500, 3, canone: 400)));
        // concordato senza cedolare: 70 per cento
        $this->assertSame([13 => 4655], $this->redditi($this->fabbricato('d', 600, 8, canone: 6650)));
        // equo canone senza cedolare: sempre il canone
        $this->assertSame([13 => 300], $this->redditi($this->fabbricato('e', 500, 4, canone: 300)));
        // cedolare secca 21, 10 e 26 per cento
        $this->assertSame([14 => 8000], $this->redditi($this->fabbricato('f', 500, 3, canone: 8000, cedolare: 1)));
        $this->assertSame([15 => 7000], $this->redditi($this->fabbricato('g', 500, 8, canone: 7000, cedolare: 1)));
        $this->assertSame([16 => 3000], $this->redditi($this->fabbricato('h', 500, 3, canone: 3000, cedolare: 3)));
        // uso promiscuo: metà nella colonna 13 e metà nella 19
        $this->assertSame([13 => 525, 19 => 525], $this->redditi($this->fabbricato('i', 1000, 6)));
    }

    public function testFabbricatoSuPiuRighi(): void
    {
        // abitazione principale per 200 giorni, poi locata a canone libero: gli importi sul primo rigo
        $principale = $this->fabbricato('a', 1000, 1, 200);
        $locato = $this->fabbricato('a', 1000, 3, 165, canone: 5000);
        $locato->continuazione = true;
        $this->assertSame([13 => 5000, 19 => 575], $this->redditi($principale, $locato));
        $this->assertSame([], $locato->redditi);

        // abitazione principale parzialmente locata: rendita se maggiore o uguale al canone (con la deduzione), altrimenti il canone
        $this->assertSame([19 => 1050], $this->redditi($this->fabbricato('b', 1000, 11, canone: 800)));
        $this->assertSame([13 => 2000], $this->redditi($this->fabbricato('b', 1000, 11, canone: 2000)));
        $this->assertSame([19 => 735], $this->redditi($this->fabbricato('b', 1000, 12, canone: 800)));
    }

    public function testTotaliEImposteCedolare(): void
    {
        $righi = [$this->fabbricato('a', 1000, 1), $this->fabbricato('b', 500, 3, canone: 8000, cedolare: 1), $this->fabbricato('c', 500, 8, canone: 7000, cedolare: 1), $this->fabbricato('d', 500, 3, canone: 3000, cedolare: 3)];
        $calcolo = new CalcoloFabbricati();
        $calcolo->calcola($righi);
        ['totali' => $totali, 'imposte' => $imposte] = $calcolo->totali($righi);
        $this->assertSame(1050, $totali[19]);
        $this->assertSame([1 => 1680, 2 => 700, 3 => 780, 4 => 3160], $imposte);
    }

    private function terreno(string $chiave, int $dominicale, int $titolo, int $agrario, bool $iap = false, bool $imuNonDovuta = false, ?int $canone = null): RigoTerreno
    {
        $rigo = new RigoTerreno($chiave, $dominicale, $titolo, $agrario, 365, 100.0);
        $rigo->iap = $iap;
        $rigo->imuNonDovuta = $imuNonDovuta;
        $rigo->canone = $canone;
        return $rigo;
    }

    public function testTerreni(): void
    {
        $calcolo = new CalcoloTerreni();
        // non affittato: dominicale (x 1,80 x 1,30) sostituito dall'IMU, agrario (x 1,70 x 1,30) imponibile
        $rigo = $this->terreno('a', 100, 1, 80);
        $calcolo->calcola([$rigo]);
        $this->assertSame([12 => 177, 13 => 234], $rigo->redditi);
        // affittato in regime vincolistico: il canone se inferiore all'80 per cento del dominicale
        $rigo = $this->terreno('b', 100, 2, 80, canone: 150);
        $calcolo->calcola([$rigo]);
        $this->assertSame([11 => 150], $rigo->redditi);
        $rigo = $this->terreno('b', 100, 2, 80, canone: 200);
        $calcolo->calcola([$rigo]);
        $this->assertSame([11 => 234], $rigo->redditi);
        // coltivatore diretto con IMU non dovuta: senza la rivalutazione del 30 per cento, nelle colonne 14 e 15
        $rigo = $this->terreno('c', 100, 1, 80, iap: true, imuNonDovuta: true);
        $calcolo->calcola([$rigo]);
        $this->assertSame([14 => 180, 15 => 136], $rigo->redditi);
        $this->assertSame([11 => 0, 12 => 0, 13 => 316, 14 => 180, 15 => 136], $calcolo->totali([$rigo]));
    }

    public function testEsenzioneColtivatoriDirettiOltreDiecimilaEuro(): void
    {
        // esempio delle istruzioni del rigo RA23: dominicale IAP 22.000 e agrario 16.000, 25.500 imponibili
        $dominicale = $this->terreno('a', 0, 1, 0, iap: true);
        $dominicale->redditi = [14 => 22000, 15 => 16000];
        $totali = (new CalcoloTerreni())->totali([$dominicale]);
        $this->assertSame(25500, $totali[11] + $totali[12]);
        $this->assertSame(14763, $totali[11]);
        $this->assertSame(12500, $totali[13]);
        $this->assertSame([14 => 22000, 15 => 16000], [14 => $totali[14], 15 => $totali[15]]);
    }
}
