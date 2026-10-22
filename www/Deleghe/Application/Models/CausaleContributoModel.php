<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\CausaleContributo;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\HelperClasses\Query;

class CausaleContributoModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CausaleContributo::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('causale', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('descrizione', ComparisonOperator::like, Placeholder::placeholder)
                ->appendCloseBlock();
        $bindValues[] = strtoupper($searchKey) . '%';
        $bindTypes[] = DataType::typeString;
        $bindValues[] = '%' . $searchKey . '%';
        $bindTypes[] = DataType::typeString;
    }

    public function getCausale(string $ente, ?string $causale): ?CausaleContributo
    {
        return $causale === null ? null : ($this->getByEnteAndCausale($ente, strtoupper($causale))[0] ?? null);
    }

    public function hasCausali(string $ente): bool
    {
        return $this->countByEnte($ente) > 0;
    }

    public function cerca(string $ente, string $testo, int $limite): SismaCollection
    {
        return $this->getByEnteAndInUso($ente, true, $testo, ['causale' => 'ASC'], 0, $limite);
    }
}
