<?php

namespace Dichiarativi\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\GaranziaRimborso;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Telonio\Application\Entities\Studio;

class GaranziaRimborsoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return GaranziaRimborso::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return GaranziaRimborso[]
     */
    public function getElenco(Soggetto $soggetto): array
    {
        return iterator_to_array($this->getBySoggetto($soggetto, null, ['dataRilascio' => 'DESC', 'id' => 'DESC']), false);
    }

    /**
     * @return GaranziaRimborso[]
     */
    public function getPerStudio(Studio $studio): array
    {
        return iterator_to_array($this->getByStudio($studio, null, ['dataRilascio' => 'DESC', 'id' => 'DESC']), false);
    }
}
