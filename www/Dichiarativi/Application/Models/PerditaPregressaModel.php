<?php

namespace Dichiarativi\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\PerditaPregressa;
use Dichiarativi\Application\Enumerations\TipoPerdita;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class PerditaPregressaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return PerditaPregressa::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return PerditaPregressa[]
     */
    public function getElenco(Soggetto $soggetto): array
    {
        return iterator_to_array($this->getBySoggetto($soggetto, null, ['anno' => 'DESC', 'tipo' => 'ASC']), false);
    }

    public function getPerAnno(Soggetto $soggetto, int $anno, TipoPerdita $tipo): ?PerditaPregressa
    {
        foreach ($this->getElenco($soggetto) as $perdita) {
            if (($perdita->anno === $anno) && ($perdita->tipo === $tipo)) {
                return $perdita;
            }
        }
        return null;
    }
}
