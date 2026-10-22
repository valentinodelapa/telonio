<?php

namespace Deleghe\Application\Models;

use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\HelperClasses\Query;

class CodiceTributoModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CodiceTributo::class;
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
     * Codici in uso della sezione che corrispondono al testo (inizio del
     * codice o parte della descrizione).
     */
    public function cerca(SezioneCodiceTributo $sezione, string $testo, int $limite): SismaCollection
    {
        return $this->getBySezioneAndInUso($sezione, true, $testo, ['codice' => 'ASC'], 0, $limite);
    }

    /**
     * Consultazione della tabella: tutti i codici o quelli di una sezione,
     * con o senza i codici non più in uso.
     */
    public function consulta(?SezioneCodiceTributo $sezione, bool $ancheSoppressi, string $testo, int $offset, int $limite): SismaCollection
    {
        $filtri = self::filtri($sezione, $ancheSoppressi);
        $testo = trim($testo) === '' ? null : trim($testo);
        $ordine = ['sezione' => 'ASC', 'codice' => 'ASC'];
        return count($filtri) === 0 ? $this->getEntityCollection($testo, $ordine, $offset, $limite) : $this->getEntityCollectionByProperties($filtri, $testo, $ordine, $offset, $limite);
    }

    public function contaRisultati(?SezioneCodiceTributo $sezione, bool $ancheSoppressi, string $testo): int
    {
        $filtri = self::filtri($sezione, $ancheSoppressi);
        $testo = trim($testo) === '' ? null : trim($testo);
        return count($filtri) === 0 ? $this->countEntityCollection($testo) : $this->countEntityCollectionByProperties($filtri, $testo);
    }

    private static function filtri(?SezioneCodiceTributo $sezione, bool $ancheSoppressi): array
    {
        $filtri = [];
        if ($sezione !== null) {
            $filtri['sezione'] = $sezione;
        }
        if ($ancheSoppressi === false) {
            $filtri['inUso'] = true;
        }
        return $filtri;
    }

    public function getByCodice(SezioneCodiceTributo $sezione, string $codice): ?CodiceTributo
    {
        return $this->getBySezioneAndCodice($sezione, strtoupper(trim($codice)))[0] ?? null;
    }
}
