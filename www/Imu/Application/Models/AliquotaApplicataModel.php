<?php

namespace Imu\Application\Models;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\Entities\AliquotaApplicata;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AliquotaApplicataModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AliquotaApplicata::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerAnno(Soggetto $soggetto, Immobile $immobile, int $anno): ?AliquotaApplicata
    {
        return $this->getBySoggettoAndImmobileAndAnno($soggetto, $immobile, $anno)[0] ?? null;
    }

    /**
     * @return array<int, AliquotaApplicata> per id dell'immobile
     */
    public function getPerImmobile(Soggetto $soggetto, int $anno): array
    {
        $aliquote = [];
        foreach ($this->getBySoggettoAndAnno($soggetto, $anno) as $aliquotaApplicata) {
            $aliquote[$aliquotaApplicata->immobile->id] = $aliquotaApplicata;
        }
        return $aliquote;
    }
}
