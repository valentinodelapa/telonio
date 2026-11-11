<?php

namespace Dichiarativi\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\CertificazioneAutonomo;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CertificazioneAutonomoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CertificazioneAutonomo::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return CertificazioneAutonomo[]
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        return iterator_to_array($this->getBySoggettoAndAnnoImposta($soggetto, $anno, null, ['id' => 'ASC']), false);
    }
}
