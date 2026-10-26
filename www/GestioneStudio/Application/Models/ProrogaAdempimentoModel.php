<?php

namespace GestioneStudio\Application\Models;

use Telonio\Application\Entities\Studio;
use GestioneStudio\Application\Entities\ProrogaAdempimento;
use GestioneStudio\Application\Entities\TermineAdempimento;
use GestioneStudio\Application\Enumerations\CondizioneProroga;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

/**
 * Proroghe del calendario fiscale (senza studio) e dello studio, che
 * prevalgono.
 */
class ProrogaAdempimentoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ProrogaAdempimento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Proroghe del termine visibili allo studio, per anno (dal più recente)
     * e con quella dello studio prima di quella del calendario.
     *
     * @return ProrogaAdempimento[]
     */
    public function getPerTermine(TermineAdempimento $termine, Studio $studio): array
    {
        $proroghe = array_values(array_filter(iterator_to_array($this->getByTermineAdempimento($termine, null, ['anno' => 'DESC', 'id' => 'DESC']), false),
                        fn(ProrogaAdempimento $proroga) => ($proroga->studio === null) || ($proroga->studio->id === $studio->id)));
        usort($proroghe, fn(ProrogaAdempimento $a, ProrogaAdempimento $b) => [$b->anno, $a->studio === null] <=> [$a->anno, $b->studio === null]);
        return $proroghe;
    }

    /**
     * Proroghe del termine per l'anno di riferimento in ordine di
     * precedenza: prima quelle dello studio, poi quelle del calendario
     * fiscale; per ciascuno, prima quelle condizionate (più specifiche).
     * Chi le applica sceglie la prima i cui requisiti sono soddisfatti.
     *
     * @return ProrogaAdempimento[]
     */
    public function getApplicabili(TermineAdempimento $termine, int $anno, Studio $studio): array
    {
        $proroghe = [];
        foreach ([$studio, null] as $titolare) {
            $delTitolare = iterator_to_array($this->getByTermineAdempimentoAndAnnoAndStudio($termine, $anno, $titolare, null, ['id' => 'DESC']), false);
            usort($delTitolare, fn(ProrogaAdempimento $a, ProrogaAdempimento $b) => ($a->condizione === CondizioneProroga::tutti) <=> ($b->condizione === CondizioneProroga::tutti));
            array_push($proroghe, ...$delTitolare);
        }
        return $proroghe;
    }
}
