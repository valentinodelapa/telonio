<?php

namespace Contabilita\Application\Models;

use Contabilita\Application\Entities\AttivitaIva;
use Contabilita\Application\Entities\LiquidazioneAttivita;
use Contabilita\Application\Entities\LiquidazioneIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class LiquidazioneAttivitaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return LiquidazioneAttivita::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return LiquidazioneAttivita[] per numero dell'attività
     */
    public function getPerLiquidazione(LiquidazioneIva $liquidazione): array
    {
        $elenco = [];
        foreach ($this->getByLiquidazioneIva($liquidazione) as $dettaglio) {
            $elenco[$dettaglio->attivitaIva->numero] = $dettaglio;
        }
        ksort($elenco);
        return $elenco;
    }

    /**
     * @return LiquidazioneAttivita[]
     */
    public function getPerAttivita(AttivitaIva $attivita): array
    {
        return iterator_to_array($this->getByAttivitaIva($attivita), false);
    }
}
