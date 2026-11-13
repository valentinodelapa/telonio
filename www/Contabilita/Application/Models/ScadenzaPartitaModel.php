<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\ScadenzaPartita;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ScadenzaPartitaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ScadenzaPartita::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerPartita(Partita $partita): SismaCollection
    {
        return $this->getByPartita($partita, null, ['numero' => 'ASC']);
    }

    /**
     * Rate delle partite dell'azienda raggruppate per partita.
     *
     * @return array<int, ScadenzaPartita[]> per id della partita, in ordine di numero
     */
    public function getPerSoggetto(Soggetto $soggetto): array
    {
        $rate = [];
        foreach ($this->getBySoggetto($soggetto, null, ['numero' => 'ASC']) as $scadenza) {
            $rate[$scadenza->partita->id][] = $scadenza;
        }
        return $rate;
    }
}
