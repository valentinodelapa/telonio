<?php

namespace Successioni\Application\Forms;

use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use Successioni\Application\Entities\DichiarazioneSuccessione;

/**
 * Scelte del quadro EF: conto di addebito, tempistica e rateazione
 * dell'imposta di successione, data del versamento F24, imposte già
 * versate e crediti (sostitutive), circoscrizioni immobiliari.
 */
class PagamentoSuccessioneForm extends BaseForm
{

    public const IMPORTI = ['acconto', 'ipotecariaVersata', 'catastaleVersata', 'successioneVersata', 'creditoIpotecaria', 'creditoCatastale', 'creditoSuccessione'];

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
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('contoAddebito', FilterType::isEntity, [], true)
                ->addFilterFieldMode('tempisticaPagamento', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('numeroRate', FilterType::isInteger, [], true)
                ->addFilterFieldMode('dataVersamento', FilterType::isDate, [], true)
                ->addFilterFieldMode('numeroCircoscrizioni', FilterType::isInteger, [], true);
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
        return $valido;
    }
}
