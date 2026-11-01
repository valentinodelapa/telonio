<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\ComunicazioneLipe;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ComunicazioneLipeModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ComunicazioneLipe::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerTrimestre(Soggetto $soggetto, int $anno, int $trimestre): ?ComunicazioneLipe
    {
        return $this->getBySoggettoAndAnnoAndTrimestre($soggetto, $anno, $trimestre)[0] ?? null;
    }

    /**
     * @return array<int, ComunicazioneLipe> comunicazioni dell'anno per trimestre
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        $elenco = [];
        foreach ($this->getBySoggettoAndAnno($soggetto, $anno, null, ['trimestre' => 'ASC']) as $comunicazione) {
            $elenco[$comunicazione->trimestre] = $comunicazione;
        }
        return $elenco;
    }

    /**
     * Progressivo per l'identificativo della prossima comunicazione dell'azienda.
     */
    public function getProssimoProgressivo(Soggetto $soggetto): int
    {
        $massimo = 0;
        foreach ($this->getBySoggetto($soggetto) as $comunicazione) {
            $massimo = max($massimo, $comunicazione->progressivo);
        }
        return $massimo + 1;
    }
}
