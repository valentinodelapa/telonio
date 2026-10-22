<?php

namespace Anagrafica\Application\Fixtures;

use Anagrafica\Application\Entities\DocumentoIdentita;
use Anagrafica\Application\Enumerations\TipoDocumento;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Documento scaduto, per verificare la segnalazione in scheda.
 */
class DocumentoRossiFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoRossiFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $documento = new DocumentoIdentita($this->dataMapper);
        $documento->soggetto = $this->getEntityByFixtureName(SoggettoRossiFixture::class);
        $documento->tipoDocumento = TipoDocumento::cartaIdentita;
        $documento->numero = 'CA12345AB';
        $documento->rilasciatoDa = 'Comune di Roma';
        $documento->dataRilascio = new SismaDate('2015-05-01');
        $documento->dataScadenza = new SismaDate('2025-05-01');
        $this->addEntity($documento);
    }
}
