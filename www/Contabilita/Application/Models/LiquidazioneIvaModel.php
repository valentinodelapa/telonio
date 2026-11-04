<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Telonio\Application\Entities\Studio;

class LiquidazioneIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return LiquidazioneIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Liquidazione del periodo; con $separato il trimestre separato delle
     * attività trimestrali di un'azienda mensile.
     */
    public function getPerPeriodo(Soggetto $soggetto, int $anno, int $periodo, bool $separato = false): ?LiquidazioneIva
    {
        foreach ($this->getBySoggettoAndAnnoAndPeriodoAndTrimestreSeparato($soggetto, $anno, $periodo, $separato) as $liquidazione) {
            if ($liquidazione->trimestreSeparato === $separato) {
                return $liquidazione;
            }
        }
        return null;
    }

    /**
     * Liquidazioni dell'anno della periodicità dell'azienda, per periodo.
     *
     * @return array<int, LiquidazioneIva>
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        $liquidazioni = [];
        foreach ($this->getBySoggettoAndAnnoAndTrimestreSeparato($soggetto, $anno, false, null, ['periodo' => 'ASC']) as $liquidazione) {
            if ($liquidazione->trimestreSeparato === false) {
                $liquidazioni[$liquidazione->periodo] = $liquidazione;
            }
        }
        return $liquidazioni;
    }

    /**
     * Trimestri separati delle attività trimestrali, per trimestre.
     *
     * @return array<int, LiquidazioneIva>
     */
    public function getTrimestriSeparati(Soggetto $soggetto, int $anno): array
    {
        $liquidazioni = [];
        foreach ($this->getBySoggettoAndAnnoAndTrimestreSeparato($soggetto, $anno, true, null, ['periodo' => 'ASC']) as $liquidazione) {
            if ($liquidazione->trimestreSeparato) {
                $liquidazioni[$liquidazione->periodo] = $liquidazione;
            }
        }
        return $liquidazioni;
    }

    /**
     * Tutte le liquidazioni dell'anno nella sequenza dei riporti (mesi e,
     * dopo il mese di fine trimestre, il trimestre separato).
     *
     * @return LiquidazioneIva[]
     */
    public function getSequenza(Soggetto $soggetto, int $anno): array
    {
        $separati = $this->getTrimestriSeparati($soggetto, $anno);
        $sequenza = [];
        foreach ($this->getPerAnno($soggetto, $anno) as $periodo => $liquidazione) {
            $sequenza[] = $liquidazione;
            if (($liquidazione->periodicita === PeriodicitaIva::mensile) && ($periodo % 3 === 0) && isset($separati[intdiv($periodo, 3)])) {
                $sequenza[] = $separati[intdiv($periodo, 3)];
            }
        }
        return $sequenza;
    }

    public function contaPerAnno(Studio $studio, int $anno): int
    {
        return $this->countByStudioAndAnno($studio, $anno);
    }
}
