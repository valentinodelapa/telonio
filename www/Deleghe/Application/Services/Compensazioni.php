<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\CodiceTributo;
use Deleghe\Application\Entities\Credito;
use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Exceptions\DelegaNonModificabileException;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Utilizzo in compensazione dei crediti dell'archivio: il rigo a credito
 * della delega riprende i dati del credito e ne riduce il residuo.
 *
 * La capienza è il saldo della delega che il credito può ridurre: per i
 * crediti IMU i soli debiti IMU dello stesso comune, per i crediti verso la
 * PA (PPAA) i soli debiti dell'elenco del D.M. 14/1/2014, per gli altri il
 * saldo delle sezioni che ammettono compensazioni (non ELIDE e accise).
 */
class Compensazioni
{

    private const SEZIONI_NON_COMPENSABILI = [SezioneF24::elide, SezioneF24::accise];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @throws DelegaNonModificabileException
     * @throws \InvalidArgumentException se il credito non si può utilizzare per l'importo indicato
     */
    public function compensa(Delega $delega, Credito $credito, float $importo): Versamento
    {
        if ($delega->isModificabile() === false) {
            throw new DelegaNonModificabileException($delega);
        }
        $centesimi = EsitoControllo::centesimi($importo);
        $residuo = (new CreditoModel($this->dataMapper))->getResiduo($credito);
        $errore = match (true) {
            $credito->soggetto->id !== $delega->soggetto->id => 'Il credito non è del contribuente della delega.',
            ($delega->modello->ammetteCrediti() === false) || ($delega->modello->ammetteSezione($credito->sezione) === false) => 'Il ' . $delega->modello->getLabel() . ' non ammette crediti nella sezione ' . $credito->sezione->getLabel() . '.',
            $credito->isUtilizzabile($delega->scadenza) === false => 'Il credito è utilizzabile dal ' . $credito->utilizzabileDal->format('d/m/Y') . '.',
            $centesimi <= 0 => 'Indicare l\'importo da compensare.',
            $centesimi > $residuo => 'L\'importo supera il residuo del credito (€ ' . EsitoControllo::euro($residuo) . ').',
            default => null,
        };
        if ($errore !== null) {
            throw new \InvalidArgumentException($errore);
        }
        $versamento = new Versamento($this->dataMapper);
        $versamento->studio = $delega->studio;
        $versamento->soggetto = $delega->soggetto;
        $versamento->scadenza = $delega->scadenza;
        $versamento->delega = $delega;
        $versamento->sezione = $credito->sezione;
        foreach (Credito::CAMPI_RIGO as $campo) {
            $versamento->$campo = $credito->$campo;
        }
        $versamento->importoCredito = round($importo, 2);
        $versamento->credito = $credito;
        $versamento->descrizione = $credito->descrizione ?? 'Compensazione credito ' . $credito->getCodice() . ' ' . ($credito->annoRiferimento ?? '');
        $errori = (new ControlloDelega($this->dataMapper))->verificaDatiRiga($versamento, $versamento->sezione, $delega->scadenza);
        if (count($errori) > 0) {
            throw new \InvalidArgumentException('Dati del credito non validi per il rigo: ' . implode(' ', $errori));
        }
        $versamento->ordine = (new VersamentoModel($this->dataMapper))->getProssimoOrdine($delega, $versamento->sezione);
        $primaTransazione = $this->dataMapper->startTransaction();
        $this->dataMapper->save($versamento);
        $delega->dataModifica = new SismaDateTime();
        $this->dataMapper->save($delega);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $versamento;
    }

    /**
     * Compensa con i crediti disponibili alla data della delega, nell'ordine
     * in cui sono diventati utilizzabili, fino alla capienza.
     *
     * @return Versamento[] righi a credito aggiunti
     */
    public function compensaAutomaticamente(Delega $delega): array
    {
        if ($delega->modello->ammetteCrediti() === false) {
            return [];
        }
        $aggiunti = [];
        foreach ((new CreditoModel($this->dataMapper))->getDisponibili($delega->soggetto, $delega->scadenza) as ['credito' => $credito, 'residuo' => $residuo]) {
            if ($delega->modello->ammetteSezione($credito->sezione) === false) {
                continue;
            }
            $importo = min($residuo, $this->getCapienza($delega, $credito));
            if ($importo > 0) {
                $aggiunti[] = $this->compensa($delega, $credito, $importo / 100);
            }
        }
        return $aggiunti;
    }

    /**
     * Importo in centesimi che il credito può ancora compensare nella delega.
     */
    public function getCapienza(Delega $delega, Credito $credito): int
    {
        $versamenti = iterator_to_array((new VersamentoModel($this->dataMapper))->getElenco($delega), false);
        $compensabili = array_filter($versamenti, fn(Versamento $versamento) => in_array($versamento->sezione, self::SEZIONI_NON_COMPENSABILI, true) === false);
        $capienza = $this->saldo($compensabili);
        if (($credito->sezione === SezioneF24::tributiLocali) && ($credito->codiceTributo?->isImu() === true)) {
            $capienza = min($capienza, $this->saldo(array_filter($compensabili, fn(Versamento $versamento) => ($versamento->sezione === SezioneF24::tributiLocali)
                                    && ($versamento->codiceTributo?->isImu() === true) && ($versamento->codiceEnte === $credito->codiceEnte))));
        } elseif ($credito->codiceTributo?->codice === CodiceTributo::CODICE_CREDITI_PA) {
            $controllo = new ControlloDelega($this->dataMapper);
            $debitiPa = array_sum(array_map(fn(Versamento $versamento) => $controllo->isCompensabileCreditiPa($versamento) ? EsitoControllo::centesimi($versamento->importoDebito) : 0, $compensabili));
            $creditiPa = array_sum(array_map(fn(Versamento $versamento) => $versamento->codiceTributo?->codice === CodiceTributo::CODICE_CREDITI_PA ? EsitoControllo::centesimi($versamento->importoCredito) : 0, $compensabili));
            $capienza = min($capienza, $debitiPa - $creditiPa);
        }
        return max(0, $capienza);
    }

    /**
     * @param Versamento[] $versamenti
     */
    private function saldo(array $versamenti): int
    {
        return array_sum(array_map(fn(Versamento $versamento) => EsitoControllo::centesimi($versamento->importoDebito) - EsitoControllo::centesimi($versamento->importoCredito), $versamenti));
    }
}
