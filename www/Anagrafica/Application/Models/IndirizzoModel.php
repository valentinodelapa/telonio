<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\Indirizzo;
use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class IndirizzoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Indirizzo::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getStorico(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['tipoIndirizzo' => 'ASC', 'validoDal' => 'DESC']);
    }

    /**
     * Indirizzi vigenti alla data, indicizzati per valore del tipo indirizzo.
     *
     * @return array<string, Indirizzo>
     */
    public function getVigentiAl(Soggetto $soggetto, \DateTimeInterface $data): array
    {
        $vigenti = [];
        foreach ($this->getStorico($soggetto) as $indirizzo) {
            if ($indirizzo->isVigenteAl($data)) {
                $vigenti[$indirizzo->tipoIndirizzo->value] = $indirizzo;
            }
        }
        return $vigenti;
    }
}
