<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\UfficioRegistrazione;

class UfficioRegistrazioneModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return UfficioRegistrazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('codice', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('denominazione', ComparisonOperator::like, Placeholder::placeholder)
                ->appendCloseBlock();
        $bindValues[] = $searchKey . '%';
        $bindValues[] = '%' . $searchKey . '%';
        $bindTypes[] = DataType::typeString;
        $bindTypes[] = DataType::typeString;
    }

    public function getByCodiceUfficio(string $codice): ?\Successioni\Application\Entities\UfficioRegistrazione
    {
        return $this->getByCodice($codice)[0] ?? null;
    }
}
