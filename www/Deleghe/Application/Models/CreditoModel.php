<?php

namespace Deleghe\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Credito;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CreditoModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return Credito::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['annoRiferimento' => 'DESC', 'id' => 'ASC']);
    }

    /**
     * Importo utilizzato nelle deleghe, in centesimi; con $escluso non conta
     * l'utilizzo indicato (es. quello che si sta modificando).
     */
    public function getUtilizzato(Credito $credito, ?int $escluso = null): int
    {
        $utilizzato = 0;
        foreach ((new VersamentoModel($this->dataMapper))->getUtilizzi($credito) as $utilizzo) {
            if ($utilizzo->id !== $escluso) {
                $utilizzato += (int) round($utilizzo->importoCredito * 100);
            }
        }
        return $utilizzato;
    }

    /**
     * Residuo da utilizzare, in centesimi.
     */
    public function getResiduo(Credito $credito, ?int $escluso = null): int
    {
        return (int) round($credito->importo * 100) - $this->getUtilizzato($credito, $escluso);
    }

    /**
     * Crediti con residuo, utilizzabili alla data di versamento.
     *
     * @return array<int, array{credito: Credito, residuo: int}>
     */
    public function getDisponibili(Soggetto $soggetto, \DateTimeInterface $dataVersamento): array
    {
        $disponibili = [];
        foreach ($this->getBySoggetto($soggetto, null, ['utilizzabileDal' => 'ASC', 'id' => 'ASC']) as $credito) {
            $residuo = $this->getResiduo($credito);
            if (($residuo > 0) && $credito->isUtilizzabile($dataVersamento)) {
                $disponibili[] = ['credito' => $credito, 'residuo' => $residuo];
            }
        }
        return $disponibili;
    }
}
