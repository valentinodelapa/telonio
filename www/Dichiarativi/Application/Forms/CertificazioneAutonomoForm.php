<?php

namespace Dichiarativi\Application\Forms;

use Dichiarativi\Application\Entities\CertificazioneAutonomo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Certificazione Unica del lavoro autonomo e dei redditi diversi (soggetto
 * e anno impostati dal controller): sostituto, causale, importi non
 * negativi, somme non soggette e spese non oltre l'ammontare lordo.
 */
class CertificazioneAutonomoForm extends BaseForm
{

    private const IMPORTI = ['ammontareLordo', 'sommeNonSoggette', 'ritenuteAcconto', 'ritenuteImposta', 'ritenuteSospese', 'addizionaleRegionale', 'addizionaleComunale', 'speseInerenti'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return CertificazioneAutonomo::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['codiceFiscaleSostituto', 'denominazioneSostituto'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(trim(preg_replace('/\s+/', ' ', $this->request->input[$campo]))));
            }
        }
        foreach (self::IMPORTI as $campo) {
            $valore = is_string($this->request->input[$campo] ?? null) ? trim($this->request->input[$campo]) : '';
            $valore = $valore === '' ? '0' : $valore;
            $this->addRequest($campo, str_contains($valore, ',') ? str_replace(',', '.', str_replace('.', '', $valore)) : $valore);
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codiceFiscaleSostituto', FilterType::isMaxLimitString, [16])
                ->addFilterFieldMode('denominazioneSostituto', FilterType::isMaxLimitString, [100])
                ->addFilterFieldMode('causale', FilterType::isMaxLimitString, [2])
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
        foreach (self::IMPORTI as $campo) {
            $this->addFilterFieldMode($campo, FilterType::isNumeric);
        }
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $dati = $this->entityData;
        $codice = (string) $dati->codiceFiscaleSostituto;
        if (($codice !== '') && (preg_match('/^\d{11}$/', $codice) !== 1) && (preg_match('/^[A-Z0-9]{16}$/', $codice) !== 1)) {
            $this->formFilterError->codiceFiscaleSostitutoCustomMessage = 'Codice fiscale di 11 cifre o di 16 caratteri.';
            $valido = false;
        }
        if (isset(CertificazioneAutonomo::CAUSALI[(string) $dati->causale]) === false) {
            $this->formFilterError->causaleCustomMessage = 'Causale non prevista per il quadro RL.';
            $valido = false;
        }
        foreach (self::IMPORTI as $campo) {
            if (($dati->$campo !== null) && ($dati->$campo < 0)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Importo non negativo.';
                $valido = false;
            }
        }
        if (($dati->sommeNonSoggette ?? 0) > ($dati->ammontareLordo ?? 0)) {
            $this->formFilterError->sommeNonSoggetteCustomMessage = 'Non oltre l\'ammontare lordo.';
            $valido = false;
        }
        return $valido;
    }
}
