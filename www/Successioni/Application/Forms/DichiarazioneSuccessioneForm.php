<?php

namespace Successioni\Application\Forms;

use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use Successioni\Application\Entities\DichiarazioneSuccessione;

/**
 * Frontespizio della dichiarazione (con i dati del quadro EH: luogo del
 * decesso, separazione, prima casa del dichiarante, estremi per le
 * esenzioni e le riduzioni). Studio e defunto sono impostati dal
 * controller.
 */
class DichiarazioneSuccessioneForm extends BaseForm
{

    private const CAMPI_MAIUSCOLI = ['volumePrima', 'numeroPrima', 'testamentoSerie', 'testamentoNumero', 'testamentoSottonumero', 'vincoloProtocollo', 'precedenteUfficio', 'precedenteVolume', 'precedenteNumero'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return DichiarazioneSuccessione::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::CAMPI_MAIUSCOLI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, strtoupper(trim($this->request->input[$campo])));
            }
        }
        if (isset($this->request->input['email']) && is_string($this->request->input['email'])) {
            $this->addRequest('email', trim($this->request->input['email']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipoDichiarazione', FilterType::isEnumeration)
                ->addFilterFieldMode('annoPrima', FilterType::isInteger, [], true)
                ->addFilterFieldMode('volumePrima', FilterType::isMaxLimitString, [5], true)
                ->addFilterFieldMode('numeroPrima', FilterType::isMaxLimitString, [6], true)
                ->addFilterFieldMode('devoluzioneLegge', FilterType::isBoolean)
                ->addFilterFieldMode('devoluzioneTestamento', FilterType::isBoolean)
                ->addFilterFieldMode('testamentoPubblicoUfficiale', FilterType::isMaxLimitString, [50], true)
                ->addFilterFieldMode('testamentoDataPubblicazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('testamentoUfficio', FilterType::isEntity, [], true)
                ->addFilterFieldMode('testamentoSerie', FilterType::isMaxLimitString, [2], true)
                ->addFilterFieldMode('testamentoNumero', FilterType::isMaxLimitString, [6], true)
                ->addFilterFieldMode('testamentoSottonumero', FilterType::isMaxLimitString, [3], true)
                ->addFilterFieldMode('testamentoDataRegistrazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('testamentoEstero', FilterType::isBoolean)
                ->addFilterFieldMode('leggeEstera', FilterType::isBoolean)
                ->addFilterFieldMode('eventiEccezionali', FilterType::isBoolean)
                ->addFilterFieldMode('beneficioInventario', FilterType::isBoolean)
                ->addFilterFieldMode('comuneDecesso', FilterType::isEntity, [], true)
                ->addFilterFieldMode('statoDecesso', FilterType::isMaxLimitString, [50], true)
                ->addFilterFieldMode('statoCivile', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('residenzaEstera', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('separazioneColpa', FilterType::isBoolean)
                ->addFilterFieldMode('presentatore', FilterType::isEntity, [], true)
                ->addFilterFieldMode('codiceCarica', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('rappresentato', FilterType::isEntity, [], true)
                ->addFilterFieldMode('decorrenzaTermine', FilterType::isDate, [], true)
                ->addFilterFieldMode('telefono', FilterType::isMaxLimitString, [20], true)
                ->addFilterFieldMode('email', FilterType::isEmail, [], true)
                ->addFilterFieldMode('senzaVolture', FilterType::isBoolean)
                ->addFilterFieldMode('copiaConforme', FilterType::isBoolean)
                ->addFilterFieldMode('intermediario', FilterType::isEntity, [], true)
                ->addFilterFieldMode('impegno', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('dataImpegno', FilterType::isDate, [], true)
                ->addFilterFieldMode('primaCasaOpzione', FilterType::isMaxLimitString, [1], true)
                ->addFilterFieldMode('primaCasaTrasferimentoPrima', FilterType::isBoolean)
                ->addFilterFieldMode('primaCasaNessunaAltraComune', FilterType::isBoolean)
                ->addFilterFieldMode('primaCasaAltraCasa', FilterType::isMaxLimitString, [1], true)
                ->addFilterFieldMode('vincoloProtocollo', FilterType::isMaxLimitString, [30], true)
                ->addFilterFieldMode('vincoloData', FilterType::isDate, [], true)
                ->addFilterFieldMode('precedenteUfficio', FilterType::isMaxLimitString, [4], true)
                ->addFilterFieldMode('precedenteAnno', FilterType::isInteger, [], true)
                ->addFilterFieldMode('precedenteVolume', FilterType::isMaxLimitString, [5], true)
                ->addFilterFieldMode('precedenteNumero', FilterType::isMaxLimitString, [6], true)
                ->addFilterFieldMode('dataDichiarazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('annotazioni', FilterType::isMaxLimitString, [1000], true);
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
        if ($dati->tipoDichiarazione?->isSostitutiva() && (($dati->annoPrima === null) || ($dati->volumePrima === null) || ($dati->numeroPrima === null))) {
            $this->formFilterError->annoPrimaCustomMessage = 'Per la dichiarazione sostitutiva indicare anno, volume e numero della prima dichiarazione.';
            $valido = false;
        }
        if (($dati->devoluzioneLegge === false) && ($dati->devoluzioneTestamento === false)) {
            $this->formFilterError->devoluzioneLeggeCustomMessage = 'Indicare la devoluzione per legge, per testamento o entrambe.';
            $valido = false;
        }
        if (($dati->presentatore !== null) && ($dati->presentatore->isPersonaFisica() === false)) {
            $this->formFilterError->presentatoreCustomMessage = 'Il dichiarante deve essere una persona fisica.';
            $valido = false;
        }
        if (($dati->presentatore !== null) && ($dati->presentatore->id === $this->entity->defunto->id)) {
            $this->formFilterError->presentatoreCustomMessage = 'Il dichiarante non può essere il defunto.';
            $valido = false;
        }
        if (($dati->presentatore !== null) && ($dati->codiceCarica === null)) {
            $this->formFilterError->codiceCaricaCustomMessage = 'Indicare il codice di carica del dichiarante.';
            $valido = false;
        }
        if (($dati->comuneDecesso !== null) && ($dati->statoDecesso !== null)) {
            $this->formFilterError->statoDecessoCustomMessage = 'Indicare il comune o lo stato estero del decesso, non entrambi.';
            $valido = false;
        }
        if (($dati->intermediario !== null) && (($dati->impegno === null) || ($dati->dataImpegno === null))) {
            $this->formFilterError->dataImpegnoCustomMessage = 'Indicare l\'impegno a trasmettere e la sua data.';
            $valido = false;
        }
        if (($dati->telefono !== null) && (preg_match('/^[0-9 +\/.-]+$/', $dati->telefono) !== 1)) {
            $this->formFilterError->telefonoCustomMessage = 'Il telefono contiene solo cifre.';
            $valido = false;
        }
        if (($dati->testamentoSerie !== null) && (preg_match('/^[1-4][A-Z]?$/', $dati->testamentoSerie) !== 1)) {
            $this->formFilterError->testamentoSerieCustomMessage = 'Serie: una cifra da 1 a 4, eventualmente seguita da una lettera (es. 1T).';
            $valido = false;
        }
        return $valido;
    }
}
