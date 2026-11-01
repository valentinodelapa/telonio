<?php

namespace Contabilita\Application\Fixtures;

use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\CodiceIvaModel;
use Contabilita\Application\Models\RegistroIvaModel;
use Contabilita\Application\Services\CorrispettiviMensili;
use Contabilita\Application\Services\RegistrazioneDocumentoIva;
use SismaFramework\Core\BaseClasses\BaseFixture;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Esercizio 2025 demo del negozio Ferri, chiuso, con gli acquisti per la
 * rivendita (anche di altri prodotti) e i corrispettivi di dicembre: base
 * della verifica dei limiti della ventilazione per il 2026 (artt. 4 e 5
 * D.M. 24 febbraio 1973).
 */
class StoricoFerri2025Fixture extends BaseFixture
{

    #[\Override]
    protected function setDependencies(): void
    {
        $this->addDependency(SoggettoFerriFixture::class)
                ->addDependency(EsercizioFerri2026Fixture::class)
                ->addDependency(FornitoreGrossistaFerriFixture::class)
                ->addDependency(FornitoreServiziFerriFixture::class);
    }

    #[\Override]
    public function setEntity(): void
    {
        $ferri = $this->getEntityByFixtureName(SoggettoFerriFixture::class);
        $esercizio = new EsercizioContabile($this->dataMapper);
        $esercizio->studio = $ferri->studio;
        $esercizio->soggetto = $ferri;
        $esercizio->anno = 2025;
        $esercizio->dataInizio = new SismaDate('2025-01-01');
        $esercizio->dataFine = new SismaDate('2025-12-31');
        $this->dataMapper->save($esercizio);
        $grossista = $this->getEntityByFixtureName(FornitoreGrossistaFerriFixture::class);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $grossista, CausaleContabile::fatturaAcquisto, '2025-11-10', '2210', ['4' => 4000.0, '10' => 9000.0, '22' => 3000.0], ['rivendita' => true]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $grossista, CausaleContabile::fatturaAcquisto, '2025-11-20', '2251', ['22' => 1800.0], ['altriProdotti' => true]);
        RegistrazioneDocumentoIva::registra($this->dataMapper, $esercizio, $this->getEntityByFixtureName(FornitoreServiziFerriFixture::class), CausaleContabile::fatturaAcquisto, '2025-12-05', 'G/118', ['22' => 600.0]);
        $registro = (new RegistroIvaModel($this->dataMapper))->getPerTipo($ferri, TipoRegistroIva::corrispettivi)[0];
        $ventilare = (new CodiceIvaModel($this->dataMapper))->getPerCodice($ferri->studio, 'VEN');
        $importi = [];
        for ($giorno = new \DateTimeImmutable('2025-12-01'); (int) $giorno->format('n') === 12; $giorno = $giorno->modify('+1 day')) {
            if ((int) $giorno->format('w') !== 0) {
                $importi[$giorno->format('Y-m-d')][$ventilare->id] = 650.0 + ((int) $giorno->format('j') * 41) % 260;
            }
        }
        (new CorrispettiviMensili($this->dataMapper))->registra($registro, 2025, 12, $importi, [$ventilare->id => $ventilare]);
        $esercizio->chiuso = true;
        $this->addEntity($esercizio);
    }
}
