<?php

namespace Successioni\Application\Models;

use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Entities\DichiarazioneSuccessione;
use Successioni\Application\Enumerations\QuadroBene;

class BeneSuccessioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return BeneSuccessione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(DichiarazioneSuccessione $dichiarazione): SismaCollection
    {
        return $this->getByDichiarazioneSuccessione($dichiarazione, null, ['quadro' => 'ASC', 'ordine' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return array<string, \Successioni\Application\Entities\BeneSuccessione[]> beni per valore di QuadroBene, nell'ordine dei quadri
     */
    public function getPerQuadro(DichiarazioneSuccessione $dichiarazione): array
    {
        $quadri = array_fill_keys(array_map(fn(QuadroBene $quadro) => $quadro->value, QuadroBene::cases()), []);
        foreach ($this->getElenco($dichiarazione) as $bene) {
            $quadri[$bene->quadro->value][] = $bene;
        }
        return $quadri;
    }

    public function getProssimoOrdine(DichiarazioneSuccessione $dichiarazione, QuadroBene $quadro): int
    {
        $ordine = 0;
        foreach ($this->getByDichiarazioneSuccessioneAndQuadro($dichiarazione, $quadro) as $bene) {
            $ordine = max($ordine, $bene->ordine);
        }
        return $ordine + 1;
    }
}
