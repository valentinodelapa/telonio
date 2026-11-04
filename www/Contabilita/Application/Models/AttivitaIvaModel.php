<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\AttivitaIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AttivitaIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AttivitaIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return AttivitaIva[] per numero
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
     * @return AttivitaIva[] per numero
     */
    public function getAttiveNel(Soggetto $soggetto, int $anno): array
    {
        return array_filter($this->getElenco($soggetto), fn(AttivitaIva $attivita) => $attivita->isAttivaNel($anno));
    }

    public function getProssimoNumero(Soggetto $soggetto): int
    {
        $elenco = $this->getElenco($soggetto);
        return count($elenco) === 0 ? 1 : max(array_keys($elenco)) + 1;
    }
}
