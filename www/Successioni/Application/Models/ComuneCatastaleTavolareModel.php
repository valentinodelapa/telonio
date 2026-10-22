<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\ComuneCatastaleTavolare;

class ComuneCatastaleTavolareModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ComuneCatastaleTavolare::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Comune catastale dal nome (maiuscole e spazi non contano).
     */
    public function trovaPerNome(?string $nome): ?ComuneCatastaleTavolare
    {
        $nome = preg_replace('/\s+/', ' ', mb_strtoupper(trim((string) $nome)));
        return $nome === '' ? null : ($this->getByDenominazione($nome)[0] ?? null);
    }
}
