<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class DatiFiscaliModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return DatiFiscali::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Tutte le versioni del soggetto, dalla più recente.
     */
    public function getStorico(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['validoDal' => 'DESC']);
    }

    /**
     * Versione vigente alla data indicata: è il metodo da usare dagli altri
     * moduli della suite (es. denominazione del cliente a fine esercizio).
     */
    public function getVigenteAl(Soggetto $soggetto, SismaDate $data): ?DatiFiscali
    {
        $query = $this->initQuery();
        $query->setWhere()
                ->appendCondition('soggetto', ComparisonOperator::equal, Placeholder::placeholder, true)
                ->appendAnd()
                ->appendCondition('validoDal', ComparisonOperator::lessOrEqual, Placeholder::placeholder)
                ->appendAnd()
                ->appendOpenBlock()
                ->appendCondition('validoAl', ComparisonOperator::isNull, '')
                ->appendOr()
                ->appendCondition('validoAl', ComparisonOperator::greaterOrEqual, Placeholder::placeholder)
                ->appendCloseBlock();
        $query->setLimit(1);
        $query->close();
        return $this->dataMapper->findFirst($this->entityName, $query, [$soggetto, $data, $data], [DataType::typeEntity, DataType::typeDate, DataType::typeDate]);
    }
}
