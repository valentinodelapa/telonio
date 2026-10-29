<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\PianoRateazione;
use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Models\RavvedimentoModel;
use Deleghe\Application\Models\VersamentoModel;
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
        $this->spostaInteressiRata($ravvedimento, true);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    public function elimina(Ravvedimento $ravvedimento): void
    {
        $delega = $ravvedimento->delega;
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->spostaInteressiRata($ravvedimento, false);
        (new ArchivioVersamenti($this->dataMapper))->registra($delega->soggetto, Ravvedimento::ORIGINE, $ravvedimento->getRiferimentoOrigine(), [], $delega);
        $this->dataMapper->delete($ravvedimento);
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    /**
     * Ravvedimento di una rata non pagata di un piano di rateazione: gli
     * interessi di dilazione della stessa rata si versano nella delega del
     * ravvedimento (senza sanzione) e tornano alla data della rata se il
     * ravvedimento viene eliminato.
     */
    private function spostaInteressiRata(Ravvedimento $ravvedimento, bool $nellaDelega): void
    {
        $quota = $ravvedimento->versamentoOmesso;
        if (($quota === null) || ($quota->origine !== PianoRateazione::ORIGINE)) {
            // solo le rate di un piano hanno righi di interessi da spostare
            return;
        }
        $delega = $ravvedimento->delega;
        // eliminando un ravvedimento, se la stessa rata è regolarizzata da un altro (altra quota del
        // rigo di interessi o altra frazione) gli interessi seguono quello invece di tornare alla data della rata
        $altro = $nellaDelega ? null : $this->getAltroRavvedimentoRata($ravvedimento);
        if (($altro !== null) && ($altro->delega->id === $delega->id)) {
            return;
        }
        $destinazione = $nellaDelega ? $delega : $altro?->delega;
        $versamentoModel = new VersamentoModel($this->dataMapper);
        foreach ((new RateazioneService($this->dataMapper))->getInteressiRata($quota, $nellaDelega ? null : $delega) as $interessi) {
            $interessi->delega = $destinazione;
            $interessi->scadenza = $destinazione?->scadenza ?? $quota->scadenza;
            if ($destinazione !== null) {
                $interessi->ordine = $versamentoModel->getProssimoOrdine($destinazione, $interessi->sezione);
            }
            $this->dataMapper->save($interessi);
        }
    }

    /**
     * Altro ravvedimento della stessa rata e dello stesso rigo di interessi
     * (un'altra quota o un'altra frazione della stessa quota), o null.
     */
    private function getAltroRavvedimentoRata(Ravvedimento $ravvedimento): ?Ravvedimento
    {
        $quota = $ravvedimento->versamentoOmesso;
        $ravvedimentoModel = new RavvedimentoModel($this->dataMapper);
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($quota->soggetto, $quota->origine, $quota->riferimentoOrigine) as $altraQuota) {
            if (CalcoloRateazione::isInteresse($altraQuota) || (CalcoloRateazione::getNumeroRata($altraQuota) !== CalcoloRateazione::getNumeroRata($quota))
                    || (CalcoloRateazione::getChiaveInteressi($altraQuota) !== CalcoloRateazione::getChiaveInteressi($quota))) {
                continue;
            }
            foreach ($ravvedimentoModel->getByVersamentoOmesso($altraQuota) as $altro) {
                if ($altro->id !== $ravvedimento->id) {
                    return $altro;
                }
            }
        }
        return null;
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
