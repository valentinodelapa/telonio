<?php

namespace Contabilita\Application\Models;

use Contabilita\Application\Entities\ComunicazioneLipe;
use Contabilita\Application\Entities\ModuloLipe;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use SismaFramework\Orm\CustomTypes\SismaCollection;

class ModuloLipeModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ModuloLipe::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getModuli(ComunicazioneLipe $comunicazione): SismaCollection
    {
        return $this->getByComunicazioneLipe($comunicazione, null, ['numeroModulo' => 'ASC']);
    }
}
