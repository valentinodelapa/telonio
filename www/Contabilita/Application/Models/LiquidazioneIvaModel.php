<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
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

    public function getPerPeriodo(Soggetto $soggetto, int $anno, int $periodo): ?LiquidazioneIva
    {
        return $this->getBySoggettoAndAnnoAndPeriodo($soggetto, $anno, $periodo)[0] ?? null;
    }

    /**
     * @return array<int, LiquidazioneIva> liquidazioni dell'anno per periodo
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        $liquidazioni = [];
        foreach ($this->getBySoggettoAndAnno($soggetto, $anno, null, ['periodo' => 'ASC']) as $liquidazione) {
            $liquidazioni[$liquidazione->periodo] = $liquidazione;
        }
        return $liquidazioni;
    }

    public function contaPerAnno(Studio $studio, int $anno): int
    {
        return $this->countByStudioAndAnno($studio, $anno);
    }
}
