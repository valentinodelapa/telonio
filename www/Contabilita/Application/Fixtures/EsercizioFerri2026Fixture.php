<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\AperturaContabilita;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Contabilità demo del negozio Ferri: apertura con il piano dei conti
 * standard e l'esercizio 2026.
 */
class EsercizioFerri2026Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class)
                ->addDependency(DatiFiscaliFerriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $modello = (new PianoContiModel($this->dataMapper))->getEntityById(1)
                ?? throw new \RuntimeException('Piano dei conti standard assente: eseguire Contabilita/Database/dati-riferimento.sql prima delle fixture.');
        $this->addEntity((new AperturaContabilita($this->dataMapper))->apri($this->getEntityByFixtureName(SoggettoFerriFixture::class), $modello,
                        new SismaDate('2026-01-01'), new SismaDate('2026-12-31')));
    }
}
