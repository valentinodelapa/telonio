<?php

namespace Telonio\Application\Models;

use Telonio\Application\Entities\CasellaPosta;
use Telonio\Application\Entities\Studio;
use Telonio\Application\Enumerations\TipoCasellaPosta;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CasellaPostaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CasellaPosta::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getCasella(Studio $studio, TipoCasellaPosta $tipo): ?CasellaPosta
    {
        foreach ($this->getByStudio($studio) as $casella) {
            if ($casella->tipoCasella === $tipo) {
                return $casella;
            }
        }
        return null;
    }
}
