<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\RegistrazioneContabile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class PartitaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Partita::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerControparte(Controparte $controparte): SismaCollection
    {
        return $this->getByControparte($controparte, null, ['data' => 'ASC', 'id' => 'ASC']);
    }

    public function getPerSoggetto(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['data' => 'ASC', 'id' => 'ASC']);
    }

    public function getPerRegistrazione(RegistrazioneContabile $registrazione): SismaCollection
    {
        return $this->getByRegistrazioneContabile($registrazione, null, ['id' => 'ASC']);
    }
}
