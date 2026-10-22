<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Nazione;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\HelperClasses\Query;

class NazioneModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Nazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('denominazione', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('codiceIso', ComparisonOperator::equal, Placeholder::placeholder)
                ->appendCloseBlock();
        $bindValues[] = $searchKey . '%';
        $bindTypes[] = DataType::typeString;
        $bindValues[] = strtoupper($searchKey);
        $bindTypes[] = DataType::typeString;
    }

    public function getItalia(): Nazione
    {
        return $this->getByCodiceIso(Nazione::CODICE_ITALIA, null, ['validoDal' => 'DESC'], 0, 1)[0];
    }

    public function getByCodice(string $codiceCatastale): ?Nazione
    {
        return $this->getByCodiceCatastale(strtoupper($codiceCatastale), null, ['validoDal' => 'DESC'], 0, 1)[0] ?? null;
    }
}
