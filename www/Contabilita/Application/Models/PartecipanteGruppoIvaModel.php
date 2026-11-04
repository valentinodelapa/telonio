<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\PartecipanteGruppoIva;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class PartecipanteGruppoIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return PartecipanteGruppoIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * @return PartecipanteGruppoIva[]
     */
    public function getPartecipanti(GruppoIva $gruppo): array
    {
        return iterator_to_array($this->getByGruppoIva($gruppo, null, ['annoIngresso' => 'ASC', 'id' => 'ASC']), false);
    }

    /**
     * Controllate che partecipano alla compensazione nell'anno.
     *
     * @return PartecipanteGruppoIva[]
     */
    public function getPartecipantiNel(GruppoIva $gruppo, int $anno): array
    {
        return array_values(array_filter($this->getPartecipanti($gruppo), fn(PartecipanteGruppoIva $partecipante) => $partecipante->partecipaNel($anno)));
    }

    /**
     * Partecipazione del soggetto, come controllata, a un gruppo nell'anno.
     */
    public function getPerControllata(Soggetto $soggetto, int $anno): ?PartecipanteGruppoIva
    {
        foreach ($this->getBySoggetto($soggetto, null, ['annoIngresso' => 'DESC']) as $partecipante) {
            if ($partecipante->partecipaNel($anno)) {
                return $partecipante;
            }
        }
        return null;
    }

    /**
     * @return PartecipanteGruppoIva[]
     */
    public function getTuttiPerControllata(Soggetto $soggetto): array
    {
        return iterator_to_array($this->getBySoggetto($soggetto, null, ['annoIngresso' => 'DESC']), false);
    }
}
