<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\PianoConti;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Telonio\Application\Entities\Studio;

class PianoContiModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return PianoConti::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Modelli disponibili allo studio: prima quelli della suite, poi
     * quelli dello studio.
     *
     * @return PianoConti[]
     */
    public function getModelli(Studio $studio): array
    {
        $modelli = [];
        foreach ([null, $studio] as $titolare) {
            foreach ($this->getByStudioAndSoggetto($titolare, null, null, ['denominazione' => 'ASC']) as $piano) {
                $modelli[] = $piano;
            }
        }
        return $modelli;
    }

    /**
     * Piano dei conti dell'azienda (null = contabilità non aperta).
     */
    public function getPerAzienda(Soggetto $soggetto): ?PianoConti
    {
        return $this->getBySoggetto($soggetto)[0] ?? null;
    }

    public function contaAziende(Studio $studio): int
    {
        return $this->countByStudio($studio) - $this->countByStudioAndSoggetto($studio, null);
    }
}
