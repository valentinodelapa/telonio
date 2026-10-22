<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class LegameModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Legame::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Soggetti collegati al soggetto (soci, amministratori, ...).
     */
    public function getCollegati(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['ruolo' => 'ASC', 'validoDal' => 'DESC']);
    }

    /**
     * Ruoli ricoperti dal soggetto presso altri soggetti.
     */
    public function getIncarichi(Soggetto $soggettoCollegato): SismaCollection
    {
        return $this->getBySoggettoCollegato($soggettoCollegato, null, ['validoDal' => 'DESC']);
    }
}
