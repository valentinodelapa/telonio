<?php

namespace GestioneStudio\Application\Models;

use CommSuite\Application\Entities\Studio;
use GestioneStudio\Application\Entities\Adempimento;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

/**
 * Adempimenti del calendario fiscale (senza studio) e dello studio.
 */
class AdempimentoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Adempimento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendCondition('denominazione', ComparisonOperator::like, Placeholder::placeholder);
        $bindValues[] = '%' . $searchKey . '%';
        $bindTypes[] = DataType::typeString;
    }

    /**
     * Adempimenti visibili allo studio: prima il calendario fiscale, poi
     * quelli propri, per denominazione.
     *
     * @return Adempimento[]
     */
    public function getElenco(Studio $studio, bool $soloAttivi = true): array
    {
        $elenco = [];
        foreach ([null, $studio] as $titolare) {
            $adempimenti = $soloAttivi ? $this->getByStudioAndAttivo($titolare, true, null, ['denominazione' => 'ASC']) : $this->getByStudio($titolare, null, ['denominazione' => 'ASC']);
            array_push($elenco, ...iterator_to_array($adempimenti, false));
        }
        return $elenco;
    }

    public function esisteCodice(Studio $studio, string $codice, ?Adempimento $escluso = null): bool
    {
        foreach ([null, $studio] as $titolare) {
            foreach ($this->getByStudioAndCodice($titolare, $codice) as $adempimento) {
                if (($escluso === null) || (isset($escluso->id) === false) || ($adempimento->id !== $escluso->id)) {
                    return true;
                }
            }
        }
        return false;
    }
}
