<?php

namespace Dichiarativi\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\CertificazioneUnica;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class CertificazioneUnicaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return CertificazioneUnica::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    /**
     * Certificazioni del contribuente per l'anno d'imposta.
     *
     * @return CertificazioneUnica[]
     */
    public function getPerAnno(Soggetto $soggetto, int $anno): array
    {
        return iterator_to_array($this->getBySoggettoAndAnnoImposta($soggetto, $anno, null, ['id' => 'ASC']), false);
    }

    /**
     * Certificazioni da riportare nella dichiarazione: senza quelle
     * comprese nel conguaglio di un altro sostituto.
     *
     * @return CertificazioneUnica[]
     */
    public function getDaDichiarare(Soggetto $soggetto, int $anno): array
    {
        return array_values(array_filter($this->getPerAnno($soggetto, $anno), fn(CertificazioneUnica $certificazione) => $certificazione->conguagliata === false));
    }
}
