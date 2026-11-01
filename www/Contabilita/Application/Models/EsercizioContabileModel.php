<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\EsercizioContabile;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class EsercizioContabileModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return EsercizioContabile::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerAnno(Soggetto $soggetto, int $anno): ?EsercizioContabile
    {
        return $this->getBySoggettoAndAnno($soggetto, $anno)[0] ?? null;
    }

    /**
     * Esercizi dell'azienda, dal più recente.
     */
    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['anno' => 'DESC']);
    }

    /**
     * Esercizio che comprende la data.
     */
    public function getPerData(Soggetto $soggetto, \DateTimeInterface $data): ?EsercizioContabile
    {
        foreach ($this->getElenco($soggetto) as $esercizio) {
            if ($esercizio->comprende($data)) {
                return $esercizio;
            }
        }
        return null;
    }

    public function getUltimo(Soggetto $soggetto): ?EsercizioContabile
    {
        return $this->getBySoggetto($soggetto, null, ['anno' => 'DESC'], 0, 1)[0] ?? null;
    }
}
