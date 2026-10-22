<?php

namespace Imu\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Imu\Application\Entities\DichiarazioneImu;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class DichiarazioneImuModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return DichiarazioneImu::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto, int $anno): SismaCollection
    {
        return $this->getBySoggettoAndAnno($soggetto, $anno, null, ['denominazioneComune' => 'ASC', 'id' => 'ASC']);
    }

    public function getPerComune(Soggetto $soggetto, int $anno, string $codiceComune): ?DichiarazioneImu
    {
        return $this->getBySoggettoAndAnnoAndCodiceComune($soggetto, $anno, $codiceComune, null, ['id' => 'DESC'])[0] ?? null;
    }
}
