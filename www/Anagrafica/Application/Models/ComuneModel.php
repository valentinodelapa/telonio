<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Comune;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\HelperClasses\Query;
use SismaFramework\Orm\BaseClasses\BaseModel;

class ComuneModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Comune::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('denominazione', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('codiceCatastale', ComparisonOperator::equal, Placeholder::placeholder)
                ->appendCloseBlock();
        $bindValues[] = $searchKey . '%';
        $bindTypes[] = DataType::typeString;
        $bindValues[] = strtoupper($searchKey);
        $bindTypes[] = DataType::typeString;
    }

    /**
     * Versione più recente del comune con il codice catastale indicato.
     */
    public function getByCodice(string $codiceCatastale): ?Comune
    {
        return $this->getByCodiceCatastale(strtoupper($codiceCatastale), null, ['validoDal' => 'DESC'], 0, 1)[0] ?? null;
    }

    /**
     * Versione del comune in vigore alla data (es. alla data di nascita,
     * per decodificare il codice fiscale).
     */
    public function getByCodiceAl(string $codiceCatastale, \DateTimeInterface $data): ?Comune
    {
        foreach ($this->getByCodiceCatastale(strtoupper($codiceCatastale)) as $comune) {
            if ($comune->isVigenteAl($data)) {
                return $comune;
            }
        }
        return null;
    }
}
