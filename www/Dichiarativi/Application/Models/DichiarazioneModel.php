<?php

namespace Dichiarativi\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Telonio\Application\Entities\Studio;

class DichiarazioneModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Dichiarazione::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto, int $anno): SismaCollection
    {
        return $this->getBySoggettoAndAnno($soggetto, $anno, null, ['modello' => 'ASC']);
    }

    /**
     * Dichiarazione del modello per l'anno (e il trimestre per i modelli
     * trimestrali).
     */
    public function getPerModello(Soggetto $soggetto, ModelloDichiarativo $modello, int $anno, ?int $trimestre = null): ?Dichiarazione
    {
        foreach ($this->getBySoggettoAndModelloAndAnnoAndTrimestre($soggetto, $modello, $anno, $trimestre, null, ['id' => 'DESC']) as $dichiarazione) {
            if ($dichiarazione->trimestre === $trimestre) {
                return $dichiarazione;
            }
        }
        return null;
    }

    /**
     * Modelli IVA TR del soggetto per l'anno, per trimestre.
     *
     * @return array<int, Dichiarazione>
     */
    public function getTrimestrali(Soggetto $soggetto, ModelloDichiarativo $modello, int $anno): array
    {
        $elenco = [];
        foreach ($this->getBySoggettoAndModelloAndAnno($soggetto, $modello, $anno) as $dichiarazione) {
            if ($dichiarazione->trimestre !== null) {
                $elenco[$dichiarazione->trimestre] = $dichiarazione;
            }
        }
        ksort($elenco);
        return $elenco;
    }

    public function getPerStudio(Studio $studio, int $anno): SismaCollection
    {
        return $this->getByStudioAndAnno($studio, $anno);
    }

    public function contaPerAnno(Studio $studio, int $anno, ?ModelloDichiarativo $modello = null): int
    {
        return $modello === null ? $this->countByStudioAndAnno($studio, $anno) : $this->countByStudioAndModelloAndAnno($studio, $modello, $anno);
    }
}
