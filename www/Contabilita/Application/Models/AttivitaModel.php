<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Attivita;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AttivitaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Attivita::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return Attivita[] per numero
     */
    public function getElenco(Soggetto $soggetto): array
    {
        $elenco = [];
        foreach ($this->getBySoggetto($soggetto, null, ['numero' => 'ASC']) as $attivita) {
            $elenco[$attivita->numero] = $attivita;
        }
        return $elenco;
    }

    /**
     * Attività separate dell'anno (vuoto con la contabilità unificata).
     *
     * @return Attivita[] per numero
     */
    public function getAttiveNel(Soggetto $soggetto, int $anno): array
    {
        return array_filter($this->getElenco($soggetto), fn(Attivita $attivita) => $attivita->isAttivaNel($anno));
    }

    public function getProssimoNumero(Soggetto $soggetto): int
    {
        $elenco = $this->getElenco($soggetto);
        return count($elenco) === 0 ? 1 : max(array_keys($elenco)) + 1;
    }
}
