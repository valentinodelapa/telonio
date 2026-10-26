<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Telonio\Application\Entities\Studio;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ImmobileModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Immobile::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock();
        foreach (['indirizzo', 'descrizione', 'foglio', 'particella'] as $indice => $colonna) {
            if ($indice > 0) {
                $query->appendOr();
            }
            $query->appendCondition($colonna, ComparisonOperator::like, Placeholder::placeholder);
            $bindValues[] = in_array($colonna, ['foglio', 'particella'], true) ? $searchKey : '%' . $searchKey . '%';
            $bindTypes[] = DataType::typeString;
        }
        $query->appendCloseBlock();
    }

    public function cerca(Studio $studio, ?string $testo, ?TipoCatasto $tipoCatasto, int $offset, int $limite): SismaCollection
    {
        return $this->getEntityCollectionByProperties($this->generaFiltri($studio, $tipoCatasto), $this->normalizzaTesto($testo), ['foglio' => 'ASC', 'particella' => 'ASC', 'subalterno' => 'ASC'], $offset, $limite);
    }

    public function contaRisultati(Studio $studio, ?string $testo, ?TipoCatasto $tipoCatasto): int
    {
        return $this->countEntityCollectionByProperties($this->generaFiltri($studio, $tipoCatasto), $this->normalizzaTesto($testo));
    }

    private function generaFiltri(Studio $studio, ?TipoCatasto $tipoCatasto): array
    {
        return $tipoCatasto === null ? ['studio' => $studio] : ['studio' => $studio, 'tipoCatasto' => $tipoCatasto];
    }

    private function normalizzaTesto(?string $testo): ?string
    {
        $testo = trim((string) $testo);
        return $testo === '' ? null : $testo;
    }

    /**
     * Immobile dello studio con lo stesso identificativo catastale (comune
     * confrontato per codice catastale, quindi anche tra versioni diverse).
     */
    public function trovaPerChiaveCatastale(Studio $studio, Immobile $immobile): ?Immobile
    {
        foreach ($this->getByStudioAndTipoCatastoAndFoglioAndParticella($studio, $immobile->tipoCatasto, $immobile->foglio, $immobile->particella) as $candidato) {
            if (($candidato->getChiaveCatastale() === $immobile->getChiaveCatastale()) && ((isset($immobile->id) === false) || ($candidato->id !== $immobile->id))) {
                return $candidato;
            }
        }
        return null;
    }
}
