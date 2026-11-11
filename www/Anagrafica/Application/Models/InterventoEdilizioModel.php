<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\InterventoEdilizio;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class InterventoEdilizioModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return InterventoEdilizio::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Interventi dell'immobile, dal più recente.
     */
    public function getElenco(Immobile $immobile): SismaCollection
    {
        return $this->getByImmobile($immobile, null, ['anno' => 'DESC', 'id' => 'ASC']);
    }

    /**
     * Interventi del soggetto con una rata nell'anno d'imposta, per anno
     * della spesa.
     *
     * @return InterventoEdilizio[]
     */
    public function getConRataNellAnno(Soggetto $soggetto, int $annoImposta): array
    {
        $interventi = array_filter(iterator_to_array($this->getBySoggetto($soggetto, null, ['anno' => 'ASC', 'id' => 'ASC'])),
                fn(InterventoEdilizio $intervento) => $intervento->getNumeroRata($annoImposta) !== null);
        return array_values($interventi);
    }
}
