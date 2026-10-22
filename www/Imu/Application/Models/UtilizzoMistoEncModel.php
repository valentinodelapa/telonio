<?php

namespace Imu\Application\Models;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\Entities\UtilizzoMistoEnc;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class UtilizzoMistoEncModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return UtilizzoMistoEnc::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerAnno(Soggetto $soggetto, Immobile $immobile, int $anno): ?UtilizzoMistoEnc
    {
        return $this->getBySoggettoAndImmobileAndAnno($soggetto, $immobile, $anno)[0] ?? null;
    }

    /**
     * @return array<int, UtilizzoMistoEnc> per id dell'immobile
     */
    public function getPerImmobile(Soggetto $soggetto, int $anno): array
    {
        $utilizzi = [];
        foreach ($this->getBySoggettoAndAnno($soggetto, $anno) as $utilizzo) {
            $utilizzi[$utilizzo->immobile->id] = $utilizzo;
        }
        return $utilizzi;
    }
}
