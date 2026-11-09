<?php

namespace Dichiarativi\Application\Models;

use Dichiarativi\Application\Entities\CertificazioneUnica;
use Dichiarativi\Application\Entities\ValoreCertificazioneUnica;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class ValoreCertificazioneUnicaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return ValoreCertificazioneUnica::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Valori della certificazione per punto.
     *
     * @return array<int, float>
     */
    public function getValori(CertificazioneUnica $certificazione): array
    {
        $valori = [];
        foreach ($this->getByCertificazioneUnica($certificazione) as $valore) {
            $valori[$valore->punto] = $valore->valore;
        }
        ksort($valori);
        return $valori;
    }
}
