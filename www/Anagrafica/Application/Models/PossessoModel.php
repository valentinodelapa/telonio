<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class PossessoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Possesso::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Storico dei possessi del soggetto, dal più recente.
     */
    public function getStoricoSoggetto(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['validoDal' => 'DESC']);
    }

    /**
     * Storico dei possessori dell'immobile, dal più recente.
     */
    public function getStoricoImmobile(Immobile $immobile): SismaCollection
    {
        return $this->getByImmobile($immobile, null, ['validoDal' => 'DESC']);
    }

    /**
     * Possessi in vigore alla data, es. per l'IMU o i redditi fondiari di
     * un anno.
     *
     * @return Possesso[]
     */
    public function getVigentiAl(Immobile $immobile, \DateTimeInterface $data): array
    {
        return array_values(array_filter(iterator_to_array($this->getStoricoImmobile($immobile)), fn(Possesso $possesso) => $possesso->isVigenteAl($data)));
    }

    /**
     * Versioni dello stesso possesso: stesso soggetto, immobile e titolo.
     */
    public function getAmbito(Possesso $possesso): SismaCollection
    {
        return $this->getByImmobileAndSoggettoAndTitolo($possesso->immobile, $possesso->soggetto, $possesso->titolo);
    }
}
