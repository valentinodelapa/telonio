<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class DatiCatastaliModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return DatiCatastali::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getStorico(Immobile $immobile): SismaCollection
    {
        return $this->getByImmobile($immobile, null, ['validoDal' => 'DESC']);
    }

    public function getVigenteAl(Immobile $immobile, \DateTimeInterface $data): ?DatiCatastali
    {
        foreach ($this->getStorico($immobile) as $datiCatastali) {
            if ($datiCatastali->isVigenteAl($data)) {
                return $datiCatastali;
            }
        }
        return null;
    }
}
