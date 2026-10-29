<?php

namespace Deleghe\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\PianoRateazione;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class PianoRateazioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return PianoRateazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['scadenza' => 'DESC', 'id' => 'DESC']);
    }
}
