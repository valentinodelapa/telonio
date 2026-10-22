<?php

namespace Imu\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\Entities\AccontoVersatoImu;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AccontoVersatoImuModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AccontoVersatoImu::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return array<string, AccontoVersatoImu> per "comune|codice tributo"
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        $acconti = [];
        foreach ($this->getBySoggettoAndAnno($soggetto, $anno, null, ['codiceComune' => 'ASC', 'codiceTributo' => 'ASC']) as $acconto) {
            $acconti[$acconto->getChiave()] = $acconto;
        }
        return $acconti;
    }
}
