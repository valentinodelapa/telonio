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

    /**
     * Dati catastali in vigore alla data o, se alla data non ce ne sono
     * (es. possesso anteriore al primo classamento registrato), i primi in
     * vigore dopo: chi li usa per una data anteriore a validoDal li tratta
     * come presunti.
     */
    public function getVigenteOSuccessivo(Immobile $immobile, \DateTimeInterface $data): ?DatiCatastali
    {
        $successivo = null;
        foreach ($this->getStorico($immobile) as $datiCatastali) {
            if ($datiCatastali->isVigenteAl($data)) {
                return $datiCatastali;
            }
            if ($datiCatastali->validoDal->format('Y-m-d') > $data->format('Y-m-d')) {
                $successivo = $datiCatastali;
            }
        }
        return $successivo;
    }
}
