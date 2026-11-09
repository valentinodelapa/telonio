<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\Immobile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ContrattoLocazioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ContrattoLocazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Contratti dell'immobile, dal più recente.
     */
    public function getElenco(Immobile $immobile): SismaCollection
    {
        return $this->getByImmobile($immobile, null, ['dataInizio' => 'DESC']);
    }

    /**
     * Contratti in corso per almeno un giorno dell'intervallo in cui
     * l'immobile è locato, come immobile principale o come pertinenza.
     *
     * @return ContrattoLocazione[]
     */
    public function getInCorsoTra(Immobile $immobile, \DateTimeInterface $dal, \DateTimeInterface $al): array
    {
        $contratti = iterator_to_array($this->getElenco($immobile));
        foreach ((new PertinenzaLocataModel($this->dataMapper))->getByImmobile($immobile) as $pertinenza) {
            $contratti[] = $pertinenza->contrattoLocazione;
        }
        $inCorso = array_filter($contratti, fn(ContrattoLocazione $contratto) => $contratto->isInCorsoTra($dal, $al));
        usort($inCorso, fn(ContrattoLocazione $primo, ContrattoLocazione $secondo) => $primo->dataInizio->format('Y-m-d') <=> $secondo->dataInizio->format('Y-m-d'));
        return array_values($inCorso);
    }
}
