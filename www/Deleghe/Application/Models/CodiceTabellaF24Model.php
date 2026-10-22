<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\CodiceTabellaF24;
use Deleghe\Application\Enumerations\TabellaF24;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\HelperClasses\Query;

class CodiceTabellaF24Model extends BaseModel
{

    /** @var array<string, array<string, CodiceTabellaF24>> voci in uso per tabella e codice */
    private array $cache = [];

    #[\Override]
    protected function getEntityName(): string
    {
        return CodiceTabellaF24::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('codice', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('descrizione', ComparisonOperator::like, Placeholder::placeholder)
                ->appendCloseBlock();
        $bindValues[] = strtoupper($searchKey) . '%';
        $bindTypes[] = DataType::typeString;
        $bindValues[] = '%' . $searchKey . '%';
        $bindTypes[] = DataType::typeString;
    }

    /**
     * Voci in uso della tabella per codice.
     *
     * @return array<string, CodiceTabellaF24>
     */
    public function getVoci(TabellaF24 $tabella): array
    {
        if (isset($this->cache[$tabella->value]) === false) {
            $this->cache[$tabella->value] = [];
            foreach ($this->getByTabellaAndInUso($tabella, true, null, ['codice' => 'ASC']) as $voce) {
                $this->cache[$tabella->value][$voce->codice] = $voce;
            }
        }
        return $this->cache[$tabella->value];
    }

    public function getVoce(TabellaF24 $tabella, ?string $codice): ?CodiceTabellaF24
    {
        return $codice === null ? null : ($this->getVoci($tabella)[$codice] ?? null);
    }

    public function cerca(TabellaF24 $tabella, string $testo, int $limite): SismaCollection
    {
        return $this->getByTabellaAndInUso($tabella, true, $testo, ['codice' => 'ASC'], 0, $limite);
    }
}
