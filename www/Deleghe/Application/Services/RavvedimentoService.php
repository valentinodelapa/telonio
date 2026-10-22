<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Registra un ravvedimento: calcola sanzione e interessi e genera nella
 * delega le righe del versamento (tramite ArchivioVersamenti, con origine
 * "Ravvedimento"):
 * - tributo, sanzione e interessi su righi distinti, con i codici della
 *   regola (es. IRPEF 4001 + 8901 + 1989);
 * - senza codice interessi (es. ritenute) gli interessi si sommano al
 *   tributo; senza codice sanzione (IMU) anche la sanzione, e il rigo
 *   della sezione IMU riporta il flag di ravvedimento.
 */
class RavvedimentoService
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function registra(Ravvedimento $ravvedimento): void
    {
        $delega = $ravvedimento->delega;
        $calcolo = (new CalcoloRavvedimento($this->dataMapper))->calcola($ravvedimento->importoTributo, $ravvedimento->scadenzaOriginale, $delega->scadenza,
                $ravvedimento->termineDichiarazione, $ravvedimento->termineDichiarazioneSuccessiva, $ravvedimento->circostanza);
        $ravvedimento->giorniRitardo = $calcolo['giorni'];
        $ravvedimento->percentualeSanzione = $calcolo['percentuale'];
        $ravvedimento->riduzione = $calcolo['riduzione'];
        $ravvedimento->sanzione = $calcolo['sanzione'];
        $ravvedimento->interessi = $calcolo['interessi'];
        $ravvedimento->dettaglioInteressi = json_encode($calcolo['periodi']);
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->save($ravvedimento);
        (new ArchivioVersamenti($this->dataMapper))->registra($delega->soggetto, Ravvedimento::ORIGINE, $ravvedimento->getRiferimentoOrigine(), $this->generaRighe($ravvedimento), $delega);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    public function elimina(Ravvedimento $ravvedimento): void
    {
        $delega = $ravvedimento->delega;
        $primaTransazione = $this->dataMapper->startTransaction();
        (new ArchivioVersamenti($this->dataMapper))->registra($delega->soggetto, Ravvedimento::ORIGINE, $ravvedimento->getRiferimentoOrigine(), [], $delega);
        $this->dataMapper->delete($ravvedimento);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    /**
     * @return Versamento[]
     */
    private function generaRighe(Ravvedimento $ravvedimento): array
    {
        $importo = $ravvedimento->importoTributo;
        if ($ravvedimento->codiceInteressi === null) {
            $importo += $ravvedimento->interessi;
        }
        if ($ravvedimento->codiceSanzione === null) {
            $importo += $ravvedimento->sanzione;
        }
        $tributo = $this->nuovaRiga($ravvedimento, $ravvedimento->codiceTributo, $importo, $ravvedimento->rateazione);
        if ($ravvedimento->sezione === SezioneF24::tributiLocali) {
            $tributo->acconto = $ravvedimento->acconto;
            $tributo->saldo = $ravvedimento->saldo;
            $tributo->numeroImmobili = $ravvedimento->numeroImmobili;
            $tributo->ravvedimento = $ravvedimento->codiceSanzione === null;
        }
        $righe = [$tributo];
        foreach ([[$ravvedimento->codiceSanzione, $ravvedimento->sanzione], [$ravvedimento->codiceInteressi, $ravvedimento->interessi]] as [$codice, $importoAccessorio]) {
            if (($codice !== null) && ($importoAccessorio > 0)) {
                $rateazione = $codice->formatoRateazione === $ravvedimento->codiceTributo->formatoRateazione ? $ravvedimento->rateazione : null;
                $righe[] = $this->nuovaRiga($ravvedimento, $codice, $importoAccessorio, $rateazione);
            }
        }
        return $righe;
    }

    private function nuovaRiga(Ravvedimento $ravvedimento, CodiceTributo $codice, float $importo, ?string $rateazione): Versamento
    {
        $riga = new Versamento($this->dataMapper);
        $riga->sezione = $ravvedimento->sezione;
        $riga->codiceTributo = $codice;
        $riga->codiceEnte = $ravvedimento->codiceEnte;
        $riga->rateazione = in_array($codice->formatoRateazione, ['0000'], true) ? null : $rateazione;
        $riga->annoRiferimento = $codice->annoRichiesto ? $ravvedimento->annoRiferimento : null;
        $riga->importoDebito = round($importo, 2);
        $riga->descrizione = 'Ravvedimento ' . $ravvedimento->codiceTributo->codice . ' ' . ($ravvedimento->annoRiferimento ?? '') . ' scad. ' . $ravvedimento->scadenzaOriginale->format('d/m/Y');
        return $riga;
    }
}
