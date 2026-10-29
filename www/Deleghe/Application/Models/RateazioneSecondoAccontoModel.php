<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\RateazioneSecondoAcconto;
use Deleghe\Application\Entities\Versamento;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\HelperClasses\Query;

class RateazioneSecondoAccontoModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RateazioneSecondoAcconto::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @var array<int, RateazioneSecondoAcconto>|null norme per periodo d'imposta, lette una volta
     */
    private ?array $norme = null;

    /**
     * Norma straordinaria che consente di differire o rateizzare il
     * versamento (un secondo acconto del suo periodo d'imposta), o null.
     */
    public function getPerVersamento(Versamento $versamento): ?RateazioneSecondoAcconto
    {
        if ($this->norme === null) {
            $this->norme = [];
            foreach ($this->getEntityCollection() as $norma) {
                $this->norme[$norma->periodoImposta] = $norma;
            }
        }
        $norma = $this->norme[$versamento->annoRiferimento ?? 0] ?? null;
        return ($norma !== null) && $norma->comprende($versamento) ? $norma : null;
    }
}
