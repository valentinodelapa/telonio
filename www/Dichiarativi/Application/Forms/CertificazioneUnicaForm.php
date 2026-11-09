<?php

namespace Dichiarativi\Application\Forms;

use Dichiarativi\Application\Entities\CertificazioneUnica;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Testata della Certificazione Unica: sostituto d'imposta e anno. I punti
 * della certificazione sono letti dal controller.
 */
class CertificazioneUnicaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return CertificazioneUnica::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['codiceFiscaleSostituto', 'denominazioneSostituto'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(trim(preg_replace('/\s+/', ' ', $this->request->input[$campo]))));
            }
        }
        $this->addRequest('conguagliata', ($this->request->input['conguagliata'] ?? '') === '1' ? '1' : '0');
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('annoImposta', FilterType::isInteger)
                ->addFilterFieldMode('codiceFiscaleSostituto', FilterType::isMaxLimitString, [16])
                ->addFilterFieldMode('denominazioneSostituto', FilterType::isMaxLimitString, [100])
                ->addFilterFieldMode('conguagliata', FilterType::isBoolean)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $codice = (string) $this->entityData->codiceFiscaleSostituto;
        if (($codice !== '') && (preg_match('/^\d{11}$/', $codice) !== 1) && (preg_match('/^[A-Z0-9]{16}$/', $codice) !== 1)) {
            $this->formFilterError->codiceFiscaleSostitutoCustomMessage = 'Codice fiscale di 11 cifre o di 16 caratteri.';
            return false;
        }
        return true;
    }
}
