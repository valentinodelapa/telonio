<?php

namespace Imu\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use CommSuite\Application\Entities\Studio;
use Imu\Application\Entities\CalcoloImu;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CalcoloImuModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CalcoloImu::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerAnno(Soggetto $soggetto, int $anno): ?CalcoloImu
    {
        return $this->getBySoggettoAndAnno($soggetto, $anno)[0] ?? null;
    }

    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['anno' => 'DESC']);
    }

    public function contaPerAnno(Studio $studio, int $anno): int
    {
        return $this->countByStudioAndAnno($studio, $anno);
    }
}
