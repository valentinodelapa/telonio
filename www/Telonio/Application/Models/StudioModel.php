<?php

namespace Telonio\Application\Models;

use Telonio\Application\Entities\Studio;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\HelperClasses\Query;

class StudioModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Studio::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(): SismaCollection
    {
        return $this->getEntityCollection(null, ['denominazione' => 'ASC']);
    }

    public function esisteCodiceFiscale(string $codiceFiscale, ?Studio $escluso = null): bool
    {
        foreach ($this->getByCodiceFiscale($codiceFiscale) as $studio) {
            if (($escluso === null) || (isset($escluso->id) === false) || ($studio->id !== $escluso->id)) {
                return true;
            }
        }
        return false;
    }
}
