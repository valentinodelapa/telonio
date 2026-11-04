<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\GruppoIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Telonio\Application\Entities\Studio;

class GruppoIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return GruppoIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return GruppoIva[]
     */
    public function getElenco(Studio $studio): array
    {
        return iterator_to_array($this->getByStudio($studio, null, ['annoInizio' => 'DESC']), false);
    }

    /**
     * Gruppo di cui il soggetto è controllante nell'anno.
     */
    public function getPerControllante(Soggetto $controllante, int $anno): ?GruppoIva
    {
        foreach ($this->getByControllante($controllante, null, ['annoInizio' => 'DESC']) as $gruppo) {
            if ($gruppo->isAttivoNel($anno)) {
                return $gruppo;
            }
        }
        return null;
    }

    /**
     * Gruppi di cui il soggetto è controllante, attivi o no.
     *
     * @return GruppoIva[]
     */
    public function getTuttiPerControllante(Soggetto $controllante): array
    {
        return iterator_to_array($this->getByControllante($controllante, null, ['annoInizio' => 'DESC']), false);
    }
}
