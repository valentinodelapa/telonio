<?php

namespace Contabilita\Tests;

use Contabilita\Application\Entities\Cespite;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Enumerations\VoceCee;
use Contabilita\Application\Services\Ammortamenti;
use Contabilita\Application\Services\BeniStrumentali;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Valore dei beni strumentali per i modelli ISA (istruzioni del quadro F,
 * rigo F21, e del quadro H, rigo H01): giorni di possesso, esclusioni e
 * riduzioni.
 */
class BeniStrumentaliTest extends ContabilitaTestCase
{

    private function cespite(VoceCee $voce, string $acquisto, float $costo, ?string $dismissione = null): Cespite
    {
        $conto = new Conto($this->dataMapper);
        $conto->voceCee = $voce;
        $cespite = new Cespite($this->dataMapper);
        $cespite->contoCespite = $conto;
        $cespite->dataAcquisto = new SismaDate($acquisto);
        $cespite->dataDismissione = $dismissione === null ? null : new SismaDate($dismissione);
        $cespite->costo = $costo;
        return $cespite;
    }

    public function testValoreNellAnno(): void
    {
        // acquistato il 1° dicembre: 31/365; venduto il 30 settembre: 273/365 (esempi delle istruzioni)
        $this->assertEqualsWithDelta(3650 * 31 / 365, BeniStrumentali::valoreNellAnno($this->cespite(VoceCee::attivoBII3, '2025-12-01', 3650.0), 2025), 0.001);
        $this->assertEqualsWithDelta(3650 * 273 / 365, BeniStrumentali::valoreNellAnno($this->cespite(VoceCee::attivoBII2, '2020-03-01', 3650.0, '2025-09-30'), 2025), 0.001);
        // posseduto tutto l'anno, anche bisestile: 365 giorni
        $this->assertSame(1000.0, BeniStrumentali::valoreNellAnno($this->cespite(VoceCee::attivoBII4, '2023-05-10', 1000.0), 2024));
        // dismesso prima dell'anno o acquistato dopo: nessun valore
        $this->assertSame(0.0, BeniStrumentali::valoreNellAnno($this->cespite(VoceCee::attivoBII4, '2020-01-01', 1000.0, '2024-06-30'), 2025));
        $this->assertSame(0.0, BeniStrumentali::valoreNellAnno($this->cespite(VoceCee::attivoBII4, '2026-01-10', 1000.0), 2025));
        // immobili, avviamento e immobilizzazioni in corso esclusi; immateriali compresi
        $this->assertSame(0.0, BeniStrumentali::valoreNellAnno($this->cespite(VoceCee::attivoBII1, '2020-01-01', 100000.0), 2025));
        $this->assertSame(0.0, BeniStrumentali::valoreNellAnno($this->cespite(VoceCee::attivoBI5, '2020-01-01', 50000.0), 2025));
        $this->assertSame(2000.0, BeniStrumentali::valoreNellAnno($this->cespite(VoceCee::attivoBI4, '2020-01-01', 2000.0), 2025));
        // beni acquistati nel 1995: costo ridotto del 20 per cento (solo imprese)
        $vecchio = $this->cespite(VoceCee::attivoBII2, '1995-06-01', 10000.0);
        $this->assertSame(8000.0, BeniStrumentali::valoreNellAnno($vecchio, 2025));
        $this->assertSame(10000.0, BeniStrumentali::valoreNellAnno($vecchio, 2025, true));
        // bene mobile ad uso promiscuo: 50 per cento
        $promiscuo = $this->cespite(VoceCee::attivoBII4, '2022-01-01', 1200.0);
        $promiscuo->percentualeDeducibile = 50.0;
        $this->assertSame(600.0, BeniStrumentali::valoreNellAnno($promiscuo, 2025));
        // autovettura: per intero nel quadro F, nel limite del costo fiscale nel quadro H
        $auto = $this->cespite(VoceCee::attivoBII4, '2022-01-01', 30000.0);
        $auto->percentualeDeducibile = Ammortamenti::DEDUCIBILE_AUTOVETTURE;
        $auto->limiteCostoFiscale = Ammortamenti::LIMITE_AUTOVETTURE;
        $this->assertSame(30000.0, BeniStrumentali::valoreNellAnno($auto, 2025));
        $this->assertSame(min(30000.0, Ammortamenti::LIMITE_AUTOVETTURE), BeniStrumentali::valoreNellAnno($auto, 2025, true));
    }
}
