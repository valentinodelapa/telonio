<?php

namespace Contabilita\Application\Models;

use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneGruppoIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class LiquidazioneGruppoIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return LiquidazioneGruppoIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getPerPeriodo(GruppoIva $gruppo, int $anno, int $periodo, bool $separato = false): ?LiquidazioneGruppoIva
    {
        foreach ($this->getByGruppoIvaAndAnnoAndPeriodoAndTrimestreSeparato($gruppo, $anno, $periodo, $separato) as $liquidazione) {
            if ($liquidazione->trimestreSeparato === $separato) {
                return $liquidazione;
            }
        }
        return null;
    }

    /**
     * @return LiquidazioneGruppoIva[] per periodo
     */
    public function getPerAnno(GruppoIva $gruppo, int $anno, bool $separati = false): array
    {
        $elenco = [];
        foreach ($this->getByGruppoIvaAndAnnoAndTrimestreSeparato($gruppo, $anno, $separati, null, ['periodo' => 'ASC']) as $liquidazione) {
            if ($liquidazione->trimestreSeparato === $separati) {
                $elenco[$liquidazione->periodo] = $liquidazione;
            }
        }
        return $elenco;
    }

    /**
     * Tutte le liquidazioni del gruppo nella sequenza dei riporti.
     *
     * @return LiquidazioneGruppoIva[]
     */
    public function getSequenza(GruppoIva $gruppo, int $anno): array
    {
        $separati = $this->getPerAnno($gruppo, $anno, true);
        $sequenza = [];
        foreach ($this->getPerAnno($gruppo, $anno) as $periodo => $liquidazione) {
            $sequenza[] = $liquidazione;
            if (($periodo % 3 === 0) && isset($separati[intdiv($periodo, 3)]) && ($liquidazione->periodicita->value === 'M')) {
                $sequenza[] = $separati[intdiv($periodo, 3)];
            }
        }
        return $sequenza;
    }
}
