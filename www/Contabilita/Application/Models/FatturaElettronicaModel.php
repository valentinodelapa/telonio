<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\FatturaElettronica;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Enumerations\DirezioneFattura;
use Contabilita\Application\Enumerations\StatoFatturaElettronica;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\Enumerations\ComparisonOperator;
use SismaFramework\Orm\Enumerations\DataType;
use SismaFramework\Orm\Enumerations\Placeholder;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;
use Telonio\Application\Entities\Studio;

class FatturaElettronicaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return FatturaElettronica::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {
        $query->appendOpenBlock()
                ->appendCondition('denominazioneControparte', ComparisonOperator::like, Placeholder::placeholder)
                ->appendOr()
                ->appendCondition('numero', ComparisonOperator::like, Placeholder::placeholder)
                ->appendCloseBlock();
        array_push($bindValues, '%' . $searchKey . '%', $searchKey . '%');
        array_push($bindTypes, DataType::typeString, DataType::typeString);
    }

    /**
     * Fatture dell'azienda con i filtri facoltativi, dalla più recente.
     */
    public function cerca(Soggetto $soggetto, ?StatoFatturaElettronica $stato, ?DirezioneFattura $direzione, string $testo, int $offset, int $limite): SismaCollection
    {
        return $this->getEntityCollectionByProperties(self::filtri($soggetto, $stato, $direzione), trim($testo) === '' ? null : trim($testo), ['data' => 'DESC', 'id' => 'DESC'], $offset, $limite);
    }

    public function conta(Soggetto $soggetto, ?StatoFatturaElettronica $stato, ?DirezioneFattura $direzione, string $testo): int
    {
        return $this->countEntityCollectionByProperties(self::filtri($soggetto, $stato, $direzione), trim($testo) === '' ? null : trim($testo));
    }

    private static function filtri(Soggetto $soggetto, ?StatoFatturaElettronica $stato, ?DirezioneFattura $direzione): array
    {
        $filtri = ['soggetto' => $soggetto];
        if ($stato !== null) {
            $filtri['stato'] = $stato;
        }
        if ($direzione !== null) {
            $filtri['direzione'] = $direzione;
        }
        return $filtri;
    }

    public function esisteImpronta(Soggetto $soggetto, string $impronta): bool
    {
        return $this->countBySoggettoAndImpronta($soggetto, $impronta) > 0;
    }

    public function getPerRegistrazione(RegistrazioneContabile $registrazione): ?FatturaElettronica
    {
        return $this->getByRegistrazioneContabile($registrazione)[0] ?? null;
    }

    public function contaDaRegistrare(Studio $studio): int
    {
        return $this->countByStudioAndStato($studio, StatoFatturaElettronica::daRegistrare);
    }
}
