<?php

namespace Dichiarativi\Tests;

use Dichiarativi\Application\Services\Redditi\PropostaFamiliari;
use PHPUnit\Framework\TestCase;

/**
 * Prospetto dei familiari a carico dai legami familiari: mesi a carico
 * (art. 12, comma 3, Tuir), mesi della detrazione dei figli da 21 a 29 anni
 * (istruzioni, colonna 10) e disposizione dei righi nei moduli.
 */
class PropostaFamiliariTest extends TestCase
{

    public function testMesiVigenza(): void
    {
        // matrimonio a giugno: 7 mesi (esempio delle istruzioni, rigo 1)
        $this->assertCount(7, PropostaFamiliari::mesiVigenza(new \DateTimeImmutable('2025-06-21'), null, 2025));
        // figlio nato ad agosto: 5 mesi
        $this->assertCount(5, PropostaFamiliari::mesiVigenza(new \DateTimeImmutable('2025-08-03'), null, 2025));
        // separazione a marzo: il mese di fine è compreso
        $this->assertSame([1, 2, 3], PropostaFamiliari::mesiVigenza(new \DateTimeImmutable('2010-05-01'), new \DateTimeImmutable('2025-03-10'), 2025));
        $this->assertSame([], PropostaFamiliari::mesiVigenza(new \DateTimeImmutable('2010-05-01'), new \DateTimeImmutable('2024-12-31'), 2025));
    }

    public function testMesiDetrazioneFigli(): void
    {
        $anno = range(1, 12);
        // nato ad agosto 2004, 21 anni ad agosto 2025: 5 mesi (esempio delle istruzioni, colonna 10)
        $this->assertSame(5, PropostaFamiliari::mesiDetrazioneFigli(new \DateTimeImmutable('2004-08-10'), $anno, 2025, false));
        // meno di 21 anni per tutto l'anno: nessun mese
        $this->assertSame(0, PropostaFamiliari::mesiDetrazioneFigli(new \DateTimeImmutable('2010-09-20'), $anno, 2025, false));
        // 30 anni a maggio: fino ad aprile
        $this->assertSame(4, PropostaFamiliari::mesiDetrazioneFigli(new \DateTimeImmutable('1995-05-02'), $anno, 2025, false));
        // con disabilità nessun limite dei 30 anni
        $this->assertSame(12, PropostaFamiliari::mesiDetrazioneFigli(new \DateTimeImmutable('1990-05-02'), $anno, 2025, true));
        // solo i mesi a carico
        $this->assertSame(3, PropostaFamiliari::mesiDetrazioneFigli(new \DateTimeImmutable('2000-01-15'), [10, 11, 12], 2025, false));
    }

    public function testImpagina(): void
    {
        $rigo = fn(string $relazione) => ['relazione' => $relazione];
        $pagine = PropostaFamiliari::impagina([
            'coniugi' => [['codiceFiscale' => 'A', 'mesi' => 12]],
            'familiari' => [$rigo('F'), $rigo('F'), $rigo('D'), $rigo('F'), $rigo('G')],
        ]);
        $this->assertSame([1, 2], array_keys($pagine));
        $this->assertSame([1, 2, 3, 4, 5], array_keys($pagine[1]));
        // nei moduli successivi dal rigo 3 (il rigo 2 è del primo figlio)
        $this->assertSame([3], array_keys($pagine[2]));
        $this->assertSame('G', $pagine[2][3]['relazione']);
    }
}
