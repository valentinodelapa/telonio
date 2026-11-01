<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Entities\StampaDefinitiva;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class StampaDefinitivaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return StampaDefinitiva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Stampe del libro nell'anno, in ordine di periodo.
     *
     * @return StampaDefinitiva[]
     */
    public function getStampe(Soggetto $soggetto, TipoStampaDefinitiva $tipo, ?RegistroIva $registro, int $anno): array
    {
        $stampe = [];
        foreach ($this->getBySoggettoAndTipoAndAnno($soggetto, $tipo, $anno, null, ['dal' => 'ASC', 'id' => 'ASC']) as $stampa) {
            if (($stampa->tipo === $tipo) && (($tipo === TipoStampaDefinitiva::giornale) || ($stampa->registroIva?->id === $registro?->id))) {
                $stampe[] = $stampa;
            }
        }
        return $stampe;
    }

    public function getUltima(Soggetto $soggetto, TipoStampaDefinitiva $tipo, ?RegistroIva $registro, int $anno): ?StampaDefinitiva
    {
        $stampe = $this->getStampe($soggetto, $tipo, $registro, $anno);
        return count($stampe) > 0 ? end($stampe) : null;
    }

    /**
     * @return StampaDefinitiva[] tutte le stampe dell'azienda nell'anno
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        return iterator_to_array($this->getBySoggettoAndAnno($soggetto, $anno, null, ['tipo' => 'ASC', 'dal' => 'ASC', 'id' => 'ASC']), false);
    }
}
