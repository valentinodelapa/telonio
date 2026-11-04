<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ValoreDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ValoreDichiarazioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ValoreDichiarazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getValori(Dichiarazione $dichiarazione): SismaCollection
    {
        return $this->getByDichiarazione($dichiarazione, null, ['modulo' => 'ASC', 'codice' => 'ASC']);
    }
}
