<?php

namespace Anagrafica\Application\Models;

use Anagrafica\Application\Entities\CodiceAteco;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use SismaFramework\Orm\BaseClasses\BaseModel;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\HelperClasses\Query;

class CodiceAtecoModel extends BaseModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CodiceAteco::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('codice', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('titolo', ComparisonOperator::like, Placeholder::placeholder)
                ->appendCloseBlock();
        $bindValues[] = $searchKey . '%';
        $bindTypes[] = DataType::typeString;
        $bindValues[] = '%' . $searchKey . '%';
        $bindTypes[] = DataType::typeString;
    }

    /**
     * Sottocategorie della classificazione che corrispondono al testo
     * (inizio del codice o parte della descrizione).
     */
    public function cercaSottocategorie(ClassificazioneAteco $classificazione, string $testo, int $limite): SismaCollection
    {
        return $this->getByClassificazioneAndLivello($classificazione, CodiceAteco::LIVELLO_SOTTOCATEGORIA, $testo, ['codice' => 'ASC'], 0, $limite);
    }

    public function getByCodice(ClassificazioneAteco $classificazione, string $codice): ?CodiceAteco
    {
        return $this->getByClassificazioneAndCodice($classificazione, $codice)[0] ?? null;
    }

    /**
     * Sottocategoria ATECO 2025 suggerita per un codice ATECO 2022.
     */
    public function getRaccordo(CodiceAteco $codiceAteco): ?CodiceAteco
    {
        return $codiceAteco->codiceRaccordo === null ? null : $this->getByCodice(ClassificazioneAteco::ateco2025, $codiceAteco->codiceRaccordo);
    }

    /**
     * Antenati del codice, dalla sezione al padre diretto.
     *
     * @return CodiceAteco[]
     */
    public function getGerarchia(CodiceAteco $codiceAteco): array
    {
        $antenati = [];
        $codicePadre = $codiceAteco->codicePadre;
        while ($codicePadre !== null && ($padre = $this->getByCodice($codiceAteco->classificazione, $codicePadre)) !== null) {
            array_unshift($antenati, $padre);
            $codicePadre = $padre->codicePadre;
        }
        return $antenati;
    }
}
