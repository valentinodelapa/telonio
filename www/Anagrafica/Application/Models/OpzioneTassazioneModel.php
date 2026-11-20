<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\OpzioneTassazione;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class OpzioneTassazioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return OpzioneTassazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['annoInizio' => 'DESC']);
    }

    /**
     * Opzione della società vigente nell'anno, se c'è.
     */
    public function getVigente(Soggetto $soggetto, int $anno): ?OpzioneTassazione
    {
        foreach ($this->getBySoggetto($soggetto) as $opzione) {
            if ($opzione->isVigenteNel($anno)) {
                return $opzione;
            }
        }
        return null;
    }

    /**
     * Società del consolidato della consolidante nell'anno (compresa la
     * consolidante).
     *
     * @return OpzioneTassazione[]
     */
    public function getConsolidate(Soggetto $consolidante, int $anno): array
    {
        $opzioni = [];
        foreach ($this->getByConsolidante($consolidante) as $opzione) {
            if ($opzione->isVigenteNel($anno)) {
                $opzioni[] = $opzione;
            }
        }
        return $opzioni;
    }
}
