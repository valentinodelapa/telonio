<?php

namespace Successioni\Application\Forms;

use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use SismaFramework\Orm\ExtendedClasses\StandardEntity;
use Successioni\Application\Entities\DichiarazioneSuccessione;

/**
 * Scelte del quadro EF: conto di addebito, tempistica e rateazione
 * dell'imposta di successione, data del versamento F24, imposte già
 * versate e crediti (sostitutive), credito d'imposta per il riacquisto
 * della prima casa (quadro EH, sezione III), circoscrizioni immobiliari.
 */
class PagamentoSuccessioneForm extends BaseForm
{

    public const IMPORTI = ['acconto', 'ipotecariaVersata', 'catastaleVersata', 'successioneVersata', 'creditoIpotecaria', 'creditoCatastale', 'creditoSuccessione', 'creditoImporto'];
    private const CAMPI_MAIUSCOLI = ['creditoAcquistoSerie', 'creditoAcquistoNumero', 'creditoAcquistoSottonumero', 'creditoVenditaSerie', 'creditoVenditaNumero', 'creditoVenditaSottonumero'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return DichiarazioneSuccessione::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (array_merge(self::IMPORTI, ['numeroRate', 'numeroCircoscrizioni']) as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, preg_replace('/[.\s€]/u', '', $this->request->input[$campo]));
            }
        }
        foreach (self::CAMPI_MAIUSCOLI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(trim($this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('contoAddebito', FilterType::isEntity, [], true)
                ->addFilterFieldMode('tempisticaPagamento', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('numeroRate', FilterType::isInteger, [], true)
                ->addFilterFieldMode('dataVersamento', FilterType::isDate, [], true)
                ->addFilterFieldMode('numeroCircoscrizioni', FilterType::isInteger, [], true)
                ->addFilterFieldMode('creditoTipo', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('pagamentoAnticipatoTrust', FilterType::isBoolean);
        foreach (['Acquisto', 'Vendita'] as $atto) {
            $this->addFilterFieldMode('credito' . $atto . 'Ufficio', FilterType::isEntity, [], true)
                    ->addFilterFieldMode('credito' . $atto . 'Serie', FilterType::isMaxLimitString, [2], true)
                    ->addFilterFieldMode('credito' . $atto . 'Numero', FilterType::isMaxLimitString, [6], true)
                    ->addFilterFieldMode('credito' . $atto . 'Sottonumero', FilterType::isMaxLimitString, [3], true)
                    ->addFilterFieldMode('credito' . $atto . 'Data', FilterType::isDate, [], true);
        }
        foreach (self::IMPORTI as $campo) {
            $this->addFilterFieldMode($campo, FilterType::isInteger, [], true);
        }
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        $valido = true;
        if (($dati->numeroRate !== null) && (($dati->numeroRate < 1) || ($dati->numeroRate > 12))) {
            $this->formFilterError->numeroRateCustomMessage = 'Da 1 a 12 rate trimestrali (fino a 8 per importi da rateizzare fino a 20.000 euro).';
            $valido = false;
        }
        if (($dati->numeroRate !== null) !== ($dati->acconto !== null)) {
            $this->formFilterError->accontoCustomMessage = 'Per la rateazione indicare insieme il numero di rate e l\'acconto (almeno il 20%).';
            $valido = false;
        }
        foreach (self::IMPORTI as $campo) {
            if (($dati->$campo !== null) && ($dati->$campo < 0)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Importo non valido.';
                $valido = false;
            }
        }
        return $this->verificaCredito($dati) && $valido;
    }

    /**
     * Quadro EH, sezione III: tipo, importo ed estremi di registrazione
     * dell'acquisto sono richiesti insieme; la vendita è facoltativa ma
     * completa e non anteriore all'acquisto.
     */
    private function verificaCredito(StandardEntity $dati): bool
    {
        $valido = true;
        $acquisto = [$dati->creditoAcquistoUfficio, $dati->creditoAcquistoSerie, $dati->creditoAcquistoNumero, $dati->creditoAcquistoData];
        $vendita = [$dati->creditoVenditaUfficio, $dati->creditoVenditaSerie, $dati->creditoVenditaNumero, $dati->creditoVenditaData];
        $presente = fn(array $valori) => count(array_filter($valori, fn($valore) => $valore !== null)) > 0;
        $completo = fn(array $valori) => count(array_filter($valori, fn($valore) => $valore === null)) === 0;
        if (($dati->creditoImporto !== null) || ($dati->creditoTipo !== null) || $presente($acquisto) || $presente($vendita)) {
            if (($dati->creditoImporto === null) || ($dati->creditoTipo === null)) {
                $this->formFilterError->creditoImportoCustomMessage = 'Indicare il tipo di imposta e l\'importo del credito.';
                $valido = false;
            }
            if ($completo($acquisto) === false) {
                $this->formFilterError->creditoAcquistoUfficioCustomMessage = 'Indicare ufficio, serie, numero e data di registrazione dell\'acquisto.';
                $valido = false;
            }
        }
        if ($presente($vendita) && ($completo($vendita) === false)) {
            $this->formFilterError->creditoVenditaUfficioCustomMessage = 'Indicare ufficio, serie, numero e data di registrazione della vendita.';
            $valido = false;
        }
        foreach (['creditoAcquistoSerie', 'creditoVenditaSerie'] as $campo) {
            if (($dati->$campo !== null) && (preg_match('/^[1-4][A-Z]?$/', $dati->$campo) !== 1)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Serie: una cifra da 1 a 4, eventualmente seguita da una lettera (es. 1T).';
                $valido = false;
            }
        }
        foreach (['creditoAcquistoNumero', 'creditoAcquistoSottonumero', 'creditoVenditaNumero', 'creditoVenditaSottonumero'] as $campo) {
            if (($dati->$campo !== null) && (ctype_digit($dati->$campo) === false)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Solo cifre.';
                $valido = false;
            }
        }
        if (($dati->creditoAcquistoData !== null) && ($dati->creditoVenditaData !== null) && ($dati->creditoVenditaData < $dati->creditoAcquistoData)) {
            $this->formFilterError->creditoVenditaDataCustomMessage = 'La vendita non può essere anteriore all\'acquisto.';
            $valido = false;
        }
        return $valido;
    }
}
