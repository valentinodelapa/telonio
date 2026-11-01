<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Controparte;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ControparteModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Controparte::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('denominazione', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('partitaIva', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('codiceFiscale', ComparisonOperator::like, Placeholder::placeholder)
                ->appendCloseBlock();
        array_push($bindValues, '%' . $searchKey . '%', $searchKey . '%', $searchKey . '%');
        array_push($bindTypes, DataType::typeString, DataType::typeString, DataType::typeString);
    }

    public function cerca(Soggetto $soggetto, string $testo, int $offset, int $limite): SismaCollection
    {
        $testo = trim($testo);
        return $this->getBySoggetto($soggetto, $testo === '' ? null : $testo, ['denominazione' => 'ASC'], $offset, $limite);
    }

    public function conta(Soggetto $soggetto, string $testo): int
    {
        $testo = trim($testo);
        return $this->countBySoggetto($soggetto, $testo === '' ? null : $testo);
    }

    /**
     * Controparte dell'azienda con la partita IVA o, in mancanza, il codice
     * fiscale indicati.
     */
    public function trova(Soggetto $soggetto, ?string $partitaIva, ?string $codiceFiscale): ?Controparte
    {
        if (($partitaIva !== null) && ($partitaIva !== '')) {
            $trovata = $this->getBySoggettoAndPartitaIva($soggetto, $partitaIva)[0] ?? null;
            if ($trovata !== null) {
                return $trovata;
            }
        }
        if (($codiceFiscale !== null) && ($codiceFiscale !== '')) {
            return $this->getBySoggettoAndCodiceFiscale($soggetto, $codiceFiscale)[0] ?? null;
        }
        return null;
    }
}
