<?php

namespace Imu\Application\Models;

use Imu\Application\Entities\CoefficienteFabbricatiD;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CoefficienteFabbricatiDModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CoefficienteFabbricatiD::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Coefficienti del decreto per l'anno d'imposta, per anno di costo
     * (vuoto se il decreto non è in archivio).
     *
     * @return array<int, float>
     */
    public function getPerAnno(int $annoImposta): array
    {
        $coefficienti = [];
        foreach ($this->getByAnnoImposta($annoImposta) as $coefficiente) {
            $coefficienti[$coefficiente->annoCosto] = $coefficiente->coefficiente;
        }
        return $coefficienti;
    }
}
