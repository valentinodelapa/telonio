<?php

namespace GestioneStudio\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use CommSuite\Application\Entities\Studio;
use GestioneStudio\Application\Entities\Adempimento;
use GestioneStudio\Application\Entities\AssegnazioneAdempimento;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class AssegnazioneAdempimentoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AssegnazioneAdempimento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Versioni dell'assegnazione dello stesso adempimento al soggetto
     * (ambito dello storico).
     */
    public function getAmbito(AssegnazioneAdempimento $assegnazione): SismaCollection
    {
        return $this->getBySoggettoAndAdempimento($assegnazione->soggetto, $assegnazione->adempimento, null, ['validoDal' => 'ASC']);
    }

    /**
     * @return AssegnazioneAdempimento[] tutte le versioni, per adempimento e decorrenza
     */
    public function getPerSoggetto(Soggetto $soggetto): array
    {
        $assegnazioni = iterator_to_array($this->getBySoggetto($soggetto, null, ['validoDal' => 'DESC']), false);
        usort($assegnazioni, fn(AssegnazioneAdempimento $a, AssegnazioneAdempimento $b) => [$a->adempimento->denominazione, $b->validoDal->format('Y-m-d')] <=> [$b->adempimento->denominazione, $a->validoDal->format('Y-m-d')]);
        return $assegnazioni;
    }

    /**
     * Assegnazioni dello studio valide in almeno un giorno del periodo.
     *
     * @return AssegnazioneAdempimento[]
     */
    public function getValideNelPeriodo(Studio $studio, SismaDate $dal, SismaDate $al, ?Soggetto $soggetto = null): array
    {
        $bindValues = [$soggetto ?? $studio, $al, $dal];
        $bindTypes = [DataType::typeEntity, DataType::typeDate, DataType::typeDate];
        $query = $this->initQuery();
        $query->setWhere()
                ->appendCondition($soggetto === null ? 'studio' : 'soggetto', ComparisonOperator::equal, Placeholder::placeholder, true)
                ->appendAnd()
                ->appendCondition('validoDal', ComparisonOperator::lessOrEqual, Placeholder::placeholder)
                ->appendAnd()
                ->appendOpenBlock()
                ->appendCondition('validoAl', ComparisonOperator::isNull, '')
                ->appendOr()
                ->appendCondition('validoAl', ComparisonOperator::greaterOrEqual, Placeholder::placeholder)
                ->appendCloseBlock();
        $query->setOrderBy(['validoDal' => 'ASC']);
        $query->close();
        return iterator_to_array($this->dataMapper->find($this->entityName, $query, $bindValues, $bindTypes), false);
    }

    /**
     * Soggetti a cui l'adempimento è assegnato con una versione in vigore
     * alla data o successiva.
     *
     * @return array<int, AssegnazioneAdempimento> per id del soggetto
     */
    public function getAssegnatiDal(Studio $studio, Adempimento $adempimento, SismaDate $data): array
    {
        $assegnati = [];
        foreach ($this->getByStudioAndAdempimento($studio, $adempimento, null, ['validoDal' => 'ASC']) as $assegnazione) {
            if (($assegnazione->validoAl === null) || ($assegnazione->validoAl->format('Y-m-d') >= $data->format('Y-m-d'))) {
                $assegnati[$assegnazione->soggetto->id] = $assegnazione;
            }
        }
        return $assegnati;
    }
}
