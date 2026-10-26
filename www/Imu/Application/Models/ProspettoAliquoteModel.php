<?php

namespace Imu\Application\Models;

use Telonio\Application\Entities\Studio;
use Imu\Application\Entities\ProspettoAliquote;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

/**
 * Prospetti delle aliquote: quelli pubblicati (senza studio) e quelli
 * inseriti dallo studio, che prevalgono.
 */
class ProspettoAliquoteModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ProspettoAliquote::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPubblicato(string $codiceCatastale, int $anno): ?ProspettoAliquote
    {
        return $this->getByStudioAndCodiceCatastaleAndAnno(null, $codiceCatastale, $anno, null, ['id' => 'DESC'])[0] ?? null;
    }

    public function getDelloStudio(Studio $studio, string $codiceCatastale, int $anno): ?ProspettoAliquote
    {
        return $this->getByStudioAndCodiceCatastaleAndAnno($studio, $codiceCatastale, $anno, null, ['id' => 'DESC'])[0] ?? null;
    }

    /**
     * Tutti i prospetti pubblicati del comune per l'anno (più di uno per i
     * comuni nati da fusione con aliquote per ex comune).
     *
     * @return ProspettoAliquote[]
     */
    public function getPubblicatiTutti(string $codiceCatastale, int $anno): array
    {
        return array_values(iterator_to_array($this->getByStudioAndCodiceCatastaleAndAnno(null, $codiceCatastale, $anno, null, ['id' => 'ASC'])));
    }

    /**
     * @return ProspettoAliquote[]
     */
    public function getDelloStudioTutti(Studio $studio, string $codiceCatastale, int $anno): array
    {
        return array_values(iterator_to_array($this->getByStudioAndCodiceCatastaleAndAnno($studio, $codiceCatastale, $anno, null, ['id' => 'ASC'])));
    }

    /**
     * Prospetto pubblicato del comune per l'anno e l'ex comune.
     */
    public function getPubblicatoExComune(string $codiceCatastale, int $anno, ?string $exComune): ?ProspettoAliquote
    {
        return $this->getByStudioAndCodiceCatastaleAndAnnoAndExComune(null, $codiceCatastale, $anno, $exComune)[0] ?? null;
    }

    /**
     * Aliquote da applicare per lo studio: le sue, altrimenti il prospetto
     * pubblicato.
     */
    public function getPerStudio(Studio $studio, string $codiceCatastale, int $anno): ?ProspettoAliquote
    {
        return $this->getDelloStudio($studio, $codiceCatastale, $anno) ?? $this->getPubblicato($codiceCatastale, $anno);
    }

    /**
     * Prospetti dello studio e pubblicati di un comune, dal più recente.
     */
    public function getStoricoComune(Studio $studio, string $codiceCatastale): array
    {
        $prospetti = [];
        foreach ($this->getByCodiceCatastale($codiceCatastale, null, ['anno' => 'DESC', 'id' => 'DESC']) as $prospetto) {
            if (($prospetto->studio === null) || ($prospetto->studio->id === $studio->id)) {
                $prospetti[] = $prospetto;
            }
        }
        return $prospetti;
    }

    public function getPubblicatiAnno(int $anno): SismaCollection
    {
        return $this->getByStudioAndAnno(null, $anno);
    }
}
