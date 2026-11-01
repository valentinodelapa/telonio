<?php

namespace Contabilita\Application\Models;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\RegistroIva;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use SismaFramework\Orm\CustomTypes\SismaCollection;
use SismaFramework\Orm\ExtendedClasses\DependentModel;
use SismaFramework\Orm\HelperClasses\Query;

class RegistroIvaModel extends DependentModel
{

    #[\Override]
    protected function getEntityName(): string
    {
        return RegistroIva::class;
    }

    #[\Override]
    protected function appendSearchCondition(Query &$query, string $searchKey, array &$bindValues, array &$bindTypes): void
    {

    }

    public function getElenco(Soggetto $soggetto): SismaCollection
    {
        return $this->getBySoggetto($soggetto, null, ['tipo' => 'ASC', 'sezionale' => 'ASC']);
    }

    /**
     * Registri attivi del tipo, prima il principale.
     */
    public function getPerTipo(Soggetto $soggetto, TipoRegistroIva $tipo): SismaCollection
    {
        return $this->getBySoggettoAndTipoAndAttivo($soggetto, $tipo, true, null, ['sezionale' => 'ASC']);
    }

    public function getRegistro(Soggetto $soggetto, TipoRegistroIva $tipo, string $sezionale = ''): ?RegistroIva
    {
        return $this->getBySoggettoAndTipoAndSezionale($soggetto, $tipo, $sezionale)[0] ?? null;
    }
}
