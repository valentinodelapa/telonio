<?php

namespace Deleghe\Application\Forms;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Services\ControlloDelega;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Versamento del contribuente: soggetto, sezione ed eventuale delega sono
 * impostati dal controller, i campi filtrati sono quelli della sezione
 * (SezioneF24::getCampi) più descrizione e importi; la data di versamento si
 * indica solo per i versamenti dell'archivio (in delega è quella della
 * delega). Le regole di compilazione sono in ControlloDelega, condivise con
 * i moduli che generano versamenti.
 */
class VersamentoForm extends BaseForm
{

    private const FILTRI = [
        'codiceTributo' => [FilterType::isEntity, []],
        'codiceEnte' => [FilterType::isMaxLimitString, [5]],
        'codiceSede' => [FilterType::isMaxLimitString, [5]],
        'causale' => [FilterType::isMaxLimitString, [4]],
        'posizione' => [FilterType::isMaxLimitString, [17]],
        'cifraControllo' => [FilterType::isMaxLimitString, [2]],
        'numeroRiferimento' => [FilterType::isMaxLimitString, [6]],
        'rateazione' => [FilterType::isMaxLimitString, [4]],
        'annoRiferimento' => [FilterType::isInteger, []],
        'periodoDa' => [FilterType::isMaxLimitString, [7]],
        'periodoA' => [FilterType::isMaxLimitString, [7]],
        'ravvedimento' => [FilterType::isBoolean, []],
        'immobiliVariati' => [FilterType::isBoolean, []],
        'acconto' => [FilterType::isBoolean, []],
        'saldo' => [FilterType::isBoolean, []],
        'numeroImmobili' => [FilterType::isInteger, []],
        'tipoElide' => [FilterType::isMaxLimitString, [1]],
        'elementiIdentificativi' => [FilterType::isMaxLimitString, [17]],
        'provincia' => [FilterType::isMaxLimitString, [2]],
        'codiceIdentificativoEnte' => [FilterType::isMaxLimitString, [14]],
        'meseRiferimento' => [FilterType::isInteger, []],
        'numeroCertificazione' => [FilterType::isMaxLimitString, [16]],
    ];
    private const CAMPI_TESTO = ['codiceEnte', 'codiceSede', 'causale', 'posizione', 'cifraControllo', 'numeroRiferimento', 'rateazione', 'periodoDa', 'periodoA', 'tipoElide', 'elementiIdentificativi', 'provincia', 'codiceIdentificativoEnte', 'numeroCertificazione'];
    private const CAMPI_BOOLEANI = ['ravvedimento', 'immobiliVariati', 'acconto', 'saldo'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return Versamento::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::CAMPI_TESTO as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(str_replace(' ', '', $this->request->input[$campo])));
            }
        }
        foreach (['importoDebito', 'importoCredito'] as $campo) {
            $valore = Importo::normalizza(is_string($this->request->input[$campo] ?? null) ? $this->request->input[$campo] : '');
            $this->addRequest($campo, $valore === '' ? '0' : $valore);
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $sezione = $this->entity->sezione;
        foreach ($sezione->getCampi() as $campo) {
            [$filtro, $parametri] = self::FILTRI[$campo];
            $this->addFilterFieldMode($campo, $filtro, $parametri, in_array($campo, self::CAMPI_BOOLEANI, true) === false);
        }
        if ($this->entity->delega === null) {
            $this->addFilterFieldMode('scadenza', FilterType::isDate);
        }
        $this->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('importoDebito', FilterType::isNumeric)
                ->addFilterFieldMode('importoCredito', FilterType::isNumeric);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $scadenza = $this->entity->delega?->scadenza ?? $this->entityData->scadenza;
        if ($scadenza === null) {
            return false;
        }
        $errori = (new ControlloDelega())->verificaDatiRiga($this->entityData, $this->entity->sezione, $scadenza);
        $codiceTributo = $this->entityData->codiceTributo;
        if (($codiceTributo !== null) && ($codiceTributo->inUso === false) && (isset($errori['codiceTributo']) === false)) {
            $errori['codiceTributo'] = 'Il codice ' . $codiceTributo->codice . ' non è più in uso.';
        }
        foreach ($errori as $campo => $errore) {
            $this->formFilterError->{$campo . 'CustomMessage'} = $errore;
        }
        return count($errori) === 0;
    }
}
