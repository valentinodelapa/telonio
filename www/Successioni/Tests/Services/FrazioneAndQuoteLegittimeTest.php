<?php

namespace Successioni\Tests\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Telonio\Tests\SuiteTestCase;
use Successioni\Application\Entities\BeneficiarioSuccessione;
use Successioni\Application\Enumerations\GradoParentela;
use Successioni\Application\Enumerations\TipoBeneficiario;
use Successioni\Application\Services\Frazione;
use Successioni\Application\Services\QuoteLegittime;
use Successioni\Application\Services\ValoreCatastale;

class FrazioneAndQuoteLegittimeTest extends SuiteTestCase
{

    private function creaBeneficiario(int $id, GradoParentela $grado, TipoBeneficiario $tipo = TipoBeneficiario::erede, bool $rinuncia = false, ?int $idRappresentato = null, ?GradoParentela $gradoRappresentato = null): BeneficiarioSuccessione
    {
        $soggetto = $this->creaSoggettoFisico($id, $this->studioTest, 'CF' . $id, 'COGN' . $id, 'NOME' . $id);
        $beneficiario = new BeneficiarioSuccessione($this->dataMapper);
        $beneficiario->id = $id;
        $beneficiario->soggetto = $soggetto;
        $beneficiario->gradoParentela = $grado;
        $beneficiario->tipoBeneficiario = $tipo;
        $beneficiario->rinuncia = $rinuncia;
        if ($idRappresentato !== null) {
            $beneficiario->rappresentato = $this->creaSoggettoFisico($idRappresentato, $this->studioTest, 'CFR' . $idRappresentato, 'RAP', 'UNO');
            $beneficiario->gradoParentelaRappresentato = $gradoRappresentato;
        }
        return $beneficiario;
    }

    public function testFrazione(): void
    {
        $f1 = new Frazione(2, 4);
        $this->assertSame(1, $f1->numeratore);
        $this->assertSame(2, $f1->denominatore);
        $this->assertSame('1/2', (string) $f1);
        $this->assertSame(0.5, $f1->toFloat());

        $fNeg = new Frazione(3, -6);
        $this->assertSame(-1, $fNeg->numeratore);
        $this->assertSame(2, $fNeg->denominatore);

        $fPerc = Frazione::daPercentuale(50.0);
        $this->assertTrue($fPerc->isUguale(new Frazione(1, 2)));

        $somma = (new Frazione(1, 3))->piu(new Frazione(1, 6));
        $this->assertTrue($somma->isUguale(new Frazione(1, 2)));

        $diff = (new Frazione(2, 3))->meno(new Frazione(1, 6));
        $this->assertTrue($diff->isUguale(new Frazione(1, 2)));

        $prod = (new Frazione(2, 3))->per(new Frazione(3, 4));
        $this->assertTrue($prod->isUguale(new Frazione(1, 2)));

        $div = (new Frazione(1, 2))->diviso(2);
        $this->assertTrue($div->isUguale(new Frazione(1, 4)));

        $this->expectException(\DivisionByZeroError::class);
        new Frazione(1, 0);
    }

    public function testQuoteLegittimeConiugeEFigli(): void
    {
        $servizio = new QuoteLegittime();
        $this->assertNull($servizio->calcola([]));
        $this->assertNotEmpty($servizio->getRegola());

        // Coniuge + 1 figlio -> 1/2 ciascuno
        $coniuge = $this->creaBeneficiario(1, GradoParentela::coniuge);
        $figlio1 = $this->creaBeneficiario(2, GradoParentela::figlio);
        $quote = $servizio->calcola([$coniuge, $figlio1]);
        $this->assertSame('1/2', (string) $quote[1]);
        $this->assertSame('1/2', (string) $quote[2]);

        // Coniuge + 2 figli (di cui uno rappresentato da 2 nipoti) -> 1/3 coniuge, 1/3 figlio1, 1/6 ciascun nipote
        $nipote1 = $this->creaBeneficiario(3, GradoParentela::nipoteLineaRetta, TipoBeneficiario::erede, false, 99, GradoParentela::figlio);
        $nipote2 = $this->creaBeneficiario(4, GradoParentela::nipoteLineaRetta, TipoBeneficiario::erede, false, 99, GradoParentela::figlio);
        $quote = $servizio->calcola([$coniuge, $figlio1, $nipote1, $nipote2]);
        $this->assertSame('1/3', (string) $quote[1]);
        $this->assertSame('1/3', (string) $quote[2]);
        $this->assertSame('1/6', (string) $quote[3]);
        $this->assertSame('1/6', (string) $quote[4]);
    }

    public function testQuoteLegittimeSenzaFigli(): void
    {
        $servizio = new QuoteLegittime();
        $coniuge = $this->creaBeneficiario(1, GradoParentela::coniuge);
        $genitore1 = $this->creaBeneficiario(2, GradoParentela::genitore);
        $fratelloGermano = $this->creaBeneficiario(3, GradoParentela::fratelloGermano);
        $fratelloUnilaterale = $this->creaBeneficiario(4, GradoParentela::fratelloUnilaterale);

        // Coniuge da solo -> 1/1
        $quote = $servizio->calcola([$coniuge]);
        $this->assertSame('1/1', (string) $quote[1]);

        // Coniuge + genitore + fratello -> 2/3, 1/4, 1/12
        $quote = $servizio->calcola([$coniuge, $genitore1, $fratelloGermano]);
        $this->assertSame('2/3', (string) $quote[1]);
        $this->assertSame('1/4', (string) $quote[2]);
        $this->assertSame('1/12', (string) $quote[3]);

        // Coniuge + ascendenti (nonni paterni e materni) -> 2/3 coniuge, 1/6 nonno paterno, 1/6 nonno materno
        $nonnoP = $this->creaBeneficiario(5, GradoParentela::nonnoPaterno);
        $nonnoM = $this->creaBeneficiario(6, GradoParentela::nonnoMaterno);
        $quote = $servizio->calcola([$coniuge, $nonnoP, $nonnoM]);
        $this->assertSame('2/3', (string) $quote[1]);
        $this->assertSame('1/6', (string) $quote[5]);
        $this->assertSame('1/6', (string) $quote[6]);

        // Coniuge + fratelli (1 germano + 1 unilaterale: peso 2 e 1 su 1/3 -> 2/9 e 1/9)
        $quote = $servizio->calcola([$coniuge, $fratelloGermano, $fratelloUnilaterale]);
        $this->assertSame('2/3', (string) $quote[1]);
        $this->assertSame('2/9', (string) $quote[3]);
        $this->assertSame('1/9', (string) $quote[4]);

        // Genitori e fratelli senza coniuge
        $quote = $servizio->calcola([$genitore1, $fratelloGermano]);
        $this->assertSame('1/2', (string) $quote[2]);
        $this->assertSame('1/2', (string) $quote[3]);

        // Soli fratelli (germano + unilaterale -> 2/3 e 1/3)
        $quote = $servizio->calcola([$fratelloGermano, $fratelloUnilaterale]);
        $this->assertSame('2/3', (string) $quote[3]);
        $this->assertSame('1/3', (string) $quote[4]);

        // Altri parenti stesso grado
        $cugino1 = $this->creaBeneficiario(7, GradoParentela::cugino);
        $cugino2 = $this->creaBeneficiario(8, GradoParentela::cugino);
        $quote = $servizio->calcola([$cugino1, $cugino2]);
        $this->assertSame('1/2', (string) $quote[7]);
        $this->assertSame('1/2', (string) $quote[8]);

        // Altri parenti gradi diversi -> null
        $zio = $this->creaBeneficiario(9, GradoParentela::zio);
        $this->assertNull($servizio->calcola([$cugino1, $zio]));
    }

    public function testValoreCatastale(): void
    {
        $this->assertNull(ValoreCatastale::getMoltiplicatore(CategoriaCatastale::f1, false));
        $this->assertSame(120.0, ValoreCatastale::getMoltiplicatore(CategoriaCatastale::a2, false));
        $this->assertSame(110.0, ValoreCatastale::getMoltiplicatore(CategoriaCatastale::a2, true));
        $this->assertSame(60.0, ValoreCatastale::getMoltiplicatore(CategoriaCatastale::a10, false));
        $this->assertSame(60.0, ValoreCatastale::getMoltiplicatore(CategoriaCatastale::d1, false));
        $this->assertSame(40.8, ValoreCatastale::getMoltiplicatore(CategoriaCatastale::c1, false));
        $this->assertSame(140.0, ValoreCatastale::getMoltiplicatore(CategoriaCatastale::c3, false));

        $catastoFab = new DatiCatastali($this->dataMapper);
        $catastoFab->categoria = CategoriaCatastale::a2;
        $catastoFab->rendita = 1000.0;
        // 1000 * 1.05 * 120 = 126000; prima casa: 1000 * 1.05 * 110 = 115500
        $this->assertSame(126000.0, ValoreCatastale::getValoreIntero($catastoFab, false, false));
        $this->assertSame(115500.0, ValoreCatastale::getValoreIntero($catastoFab, false, true));
        $this->assertSame(63000, ValoreCatastale::getValoreQuota($catastoFab, false, 0.5, false));

        $catastoTer = new DatiCatastali($this->dataMapper);
        $catastoTer->redditoDominicale = 100.0;
        // 100 * 1.25 * 90 = 11250
        $this->assertSame(11250.0, ValoreCatastale::getValoreIntero($catastoTer, true));
        $this->assertSame(11250, ValoreCatastale::getValoreQuota($catastoTer, true, 1.0));
    }
}
