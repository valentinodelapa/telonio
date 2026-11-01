<?php

namespace Contabilita\Application\Fixtures;

use Anagrafica\Application\Fixtures\DatiFiscaliAlfaAteco2025Fixture;
use Anagrafica\Application\Fixtures\SoggettoAlfaFixture;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\AperturaContabilita;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Contabilità demo di Alfa (società di capitali, contabilità ordinaria,
 * IVA mensile): apertura con il piano dei conti standard della suite e
 * l'esercizio 2026.
 */
class EsercizioAlfa2026Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoAlfaFixture::class)
                ->addDependency(DatiFiscaliAlfaAteco2025Fixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $modello = (new PianoContiModel($this->dataMapper))->getEntityById(1)
                ?? throw new \RuntimeException('Piano dei conti standard assente: eseguire Contabilita/Database/dati-riferimento.sql prima delle fixture.');
        $this->addEntity((new AperturaContabilita($this->dataMapper))->apri($this->getEntityByFixtureName(SoggettoAlfaFixture::class), $modello,
                        new SismaDate('2026-01-01'), new SismaDate('2026-12-31')));
    }
}
