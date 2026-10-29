<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\Versamento;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RavvedimentoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Ravvedimento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Delega $delega): SismaCollection
    {
        return $this->getByDelega($delega, null, ['id' => 'ASC']);
    }

    /**
     * Importo del versamento omesso già regolarizzato con i ravvedimenti (più
     * di uno con il ravvedimento frazionato), in centesimi; con $escluso non
     * conta il ravvedimento indicato (es. quello che si sta modificando).
     */
    public function getImportoRavveduto(Versamento $omesso, ?int $escluso = null): int
    {
        $importo = 0;
        foreach ($this->getByVersamentoOmesso($omesso) as $ravvedimento) {
            if ($ravvedimento->id !== $escluso) {
                $importo += (int) round($ravvedimento->importoTributo * 100);
            }
        }
        return $importo;
    }

    /**
     * Parte del versamento omesso ancora da regolarizzare, in centesimi.
     */
    public function getResiduo(Versamento $omesso, ?int $escluso = null): int
    {
        return (int) round($omesso->importoDebito * 100) - $this->getImportoRavveduto($omesso, $escluso);
    }
}
