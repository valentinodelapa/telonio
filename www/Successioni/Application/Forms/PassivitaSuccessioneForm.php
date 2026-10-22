<?php

namespace Successioni\Application\Forms;

use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use Successioni\Application\Entities\PassivitaSuccessione;
use Successioni\Application\Enumerations\TipoPassivita;

/**
 * Passività del quadro ED. Dichiarazione e ordine sono impostati dal
 * controller; la ripartizione tra i beneficiari si indica nella stessa
 * pagina (righe gestite dal controller).
 */
class PassivitaSuccessioneForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return PassivitaSuccessione::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['valore']) && is_string($this->request->input['valore'])) {
            $this->addRequest('valore', preg_replace('/[.\s€]/u', '', $this->request->input['valore']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipoPassivita', FilterType::isEnumeration)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [250])
                ->addFilterFieldMode('estremiDocumento', FilterType::isMaxLimitString, [50])
                ->addFilterFieldMode('dataDocumento', FilterType::isDate)
                ->addFilterFieldMode('quotaNumeratore', FilterType::isInteger, [], true)
                ->addFilterFieldMode('quotaDenominatore', FilterType::isInteger, [], true)
                ->addFilterFieldMode('valore', FilterType::isInteger)
                ->addFilterFieldMode('beneSuccessione', FilterType::isEntity, [], true);
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
        if (($dati->tipoPassivita === TipoPassivita::speseFunerarie) && ($dati->valore > TipoPassivita::MASSIMO_SPESE_FUNERARIE)) {
            $this->formFilterError->valoreCustomMessage = 'Le spese funerarie sono deducibili fino a 1.033 euro.';
            $valido = false;
        }
        if (($dati->tipoPassivita !== null) && $dati->tipoPassivita->richiedeCespite() && ($dati->beneSuccessione === null)) {
            $this->formFilterError->beneSuccessioneCustomMessage = 'Indicare il cespite cui si riferisce la passività.';
            $valido = false;
        }
        if (($dati->beneSuccessione !== null) && ($dati->beneSuccessione->dichiarazioneSuccessione->id !== $this->entity->dichiarazioneSuccessione->id)) {
            $this->formFilterError->beneSuccessioneCustomMessage = 'Cespite non valido.';
            $valido = false;
        }
        if (($dati->valore !== null) && ($dati->valore <= 0)) {
            $this->formFilterError->valoreCustomMessage = 'Indicare il valore deducibile.';
            $valido = false;
        }
        return $valido;
    }
}
