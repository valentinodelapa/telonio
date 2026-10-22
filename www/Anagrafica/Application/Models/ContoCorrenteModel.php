<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\ContoCorrente;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ContoCorrenteModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ContoCorrente::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['attivo' => 'DESC', 'predefinito' => 'DESC', 'id' => 'ASC']);
    }

    public function getAttivi(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggettoAndAttivo($soggetto, true, null, ['predefinito' => 'DESC', 'id' => 'ASC']);
    }

    /**
     * Conto da proporre per gli addebiti: il predefinito, altrimenti il primo
     * attivo.
     */
    public function getPredefinito(Soggetto $soggetto): ?ContoCorrente
    {
        return $this->getAttivi($soggetto)[0] ?? null;
    }
}
