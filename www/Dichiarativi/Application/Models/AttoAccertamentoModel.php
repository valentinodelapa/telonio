<?php

namespace Dichiarativi\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\AttoAccertamento;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use SismaFramework\Orm\CustomTypes\SismaDate;

class AttoAccertamentoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return AttoAccertamento::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return AttoAccertamento[]
     */
    public function getElenco(Soggetto $soggetto): array
    {
        return iterator_to_array($this->getBySoggetto($soggetto, null, ['dataNotifica' => 'DESC', 'id' => 'DESC']), false);
    }

    /**
     * Atti notificati nei due anni precedenti la richiesta oltre le soglie.
     *
     * @return AttoAccertamento[]
     */
    public function getOltreSoglia(Soggetto $soggetto, SismaDate $richiesta): array
    {
        return array_values(array_filter($this->getElenco($soggetto), fn(AttoAccertamento $atto) => $atto->isNelBiennio($richiesta) && $atto->isOltreSoglia()));
    }
}
