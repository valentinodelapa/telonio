<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\RegolaRavvedimento;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\HelperClasses\Query;

class RegolaRavvedimentoModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RegolaRavvedimento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getRegola(CodiceTributo $codiceTributo): ?RegolaRavvedimento
    {
        return $this->getBySezioneAndCodice($codiceTributo->sezione, $codiceTributo->codice)[0] ?? null;
    }
}
