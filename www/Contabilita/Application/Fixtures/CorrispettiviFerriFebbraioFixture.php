<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Services\CorrispettiviMensili;
use SismaFramework\Core\BaseClasses\BaseFixture;

/**
 * Corrispettivi demo di febbraio 2026 del negozio Ferri con la maschera
 * mensile: da ventilare (VEN) dal lunedì al sabato e, il sabato, una
 * vendita di bevande alcoliche già distinta al 22%.
 */
class CorrispettiviFerriFebbraioFixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(CorrispettiviFerriGennaioFixture::class)
                ->addDependency(SoggettoFerriFixture::class)
                ->addDependency(AcquistoFerriFebbraioFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $ferri = $this->getEntityByFixtureName(SoggettoFerriFixture::class);
        $registro = (new RegistroIvaModel($this->dataMapper))->getPerTipo($ferri, TipoRegistroIva::corrispettivi)[0];
        $codiceIvaModel = new CodiceIvaModel($this->dataMapper);
        $ventilare = $codiceIvaModel->getPerCodice($ferri->studio, 'VEN');
        $ordinaria = $codiceIvaModel->getPerCodice($ferri->studio, '22');
        $importi = [];
        $giorno = new \DateTimeImmutable('2026-02-01');
        while ((int) $giorno->format('n') === 2) {
            $settimana = (int) $giorno->format('w');
            if ($settimana !== 0) {
                $importi[$giorno->format('Y-m-d')][$ventilare->id] = 420.0 + ((int) $giorno->format('j') * 37) % 230;
                if ($settimana === 6) {
                    $importi[$giorno->format('Y-m-d')][$ordinaria->id] = 61.0;
                }
            }
            $giorno = $giorno->modify('+1 day');
        }
        $this->addEntity((new CorrispettiviMensili($this->dataMapper))->registra($registro, 2026, 2, $importi, [$ventilare->id => $ventilare, $ordinaria->id => $ordinaria]));
    }
}
