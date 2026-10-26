<?php

namespace GestioneStudio\Application\Models;

use Telonio\Application\Entities\Studio;
use GestioneStudio\Application\Entities\LavorazioneScadenza;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class LavorazioneScadenzaModel extends DependentModel
{

    private const RIFERIMENTI_PER_QUERY = 500;

    #[\Override]
    protected function getEntityName(): string
    {
        return LavorazioneScadenza::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerScadenza(Studio $studio, string $modulo, string $riferimento): ?LavorazioneScadenza
    {
        return $this->getByStudioAndModuloAndRiferimento($studio, $modulo, $riferimento)[0] ?? null;
    }

    /**
     * Lavorazioni registrate per le scadenze indicate.
     *
     * @param array<string, string[]> $riferimenti riferimenti per modulo
     * @return array<string, LavorazioneScadenza> per chiave modulo:riferimento
     */
    public function getPerRiferimenti(Studio $studio, array $riferimenti): array
    {
        $lavorazioni = [];
        foreach ($riferimenti as $modulo => $elenco) {
            foreach (array_chunk(array_values(array_unique($elenco)), self::RIFERIMENTI_PER_QUERY) as $blocco) {
                $query = $this->initQuery();
                $query->setWhere()
                        ->appendCondition('studio', ComparisonOperator::equal, Placeholder::placeholder, true)
                        ->appendAnd()
                        ->appendCondition('modulo', ComparisonOperator::equal, Placeholder::placeholder)
                        ->appendAnd()
                        ->appendCondition('riferimento', ComparisonOperator::in, array_fill(0, count($blocco), Placeholder::placeholder));
                $query->close();
                $bindValues = array_merge([$studio, $modulo], $blocco);
                $bindTypes = array_merge([DataType::typeEntity, DataType::typeString], array_fill(0, count($blocco), DataType::typeString));
                foreach ($this->dataMapper->find($this->entityName, $query, $bindValues, $bindTypes) as $lavorazione) {
                    $lavorazioni[$lavorazione->modulo . ':' . $lavorazione->riferimento] = $lavorazione;
                }
            }
        }
        return $lavorazioni;
    }
}
