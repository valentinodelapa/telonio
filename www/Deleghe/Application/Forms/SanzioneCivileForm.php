<?php

namespace Deleghe\Application\Forms;

use Deleghe\Application\Entities\SanzioneCivile;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Services\ControlloDelega;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Contributi versati in ritardo per il calcolo delle sanzioni civili; delega
 * e sezione sono impostate dal controller. Con la causale (solo INPS) la
 * sanzione diventa un rigo della delega: sede, matricola e periodo seguono
 * le regole della causale.
 */
class SanzioneCivileForm extends BaseForm
{

    private const CAMPI_RIGO = ['codiceSede', 'causale', 'posizione', 'periodoDa', 'periodoA'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return SanzioneCivile::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::CAMPI_RIGO as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(str_replace(' ', '', $this->request->input[$campo])));
            }
        }
        if (isset($this->request->input['importoContributi']) && is_string($this->request->input['importoContributi'])) {
            $this->addRequest('importoContributi', Importo::normalizza($this->request->input['importoContributi']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('riferimento', FilterType::isMaxLimitString, [100])
                ->addFilterFieldMode('importoContributi', FilterType::isNumeric)
                ->addFilterFieldMode('scadenzaOriginale', FilterType::isDate)
                ->addFilterFieldMode('regime', FilterType::isEnumeration)
                ->addFilterFieldMode('spontaneo', FilterType::isBoolean)
                ->addFilterFieldMode('causale', FilterType::isMaxLimitString, [4], true)
                ->addFilterFieldMode('codiceSede', FilterType::isMaxLimitString, [5], true)
                ->addFilterFieldMode('posizione', FilterType::isMaxLimitString, [17], true)
                ->addFilterFieldMode('periodoDa', FilterType::isMaxLimitString, [7], true)
                ->addFilterFieldMode('periodoA', FilterType::isMaxLimitString, [7], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        $delega = $this->entity->delega;
        $errori = [];
        if (($dati->importoContributi !== null) && ($dati->importoContributi <= 0)) {
            $errori['importoContributi'] = 'Indicare l\'importo dei contributi versati in ritardo.';
        }
        if (($dati->scadenzaOriginale !== null) && ($dati->scadenzaOriginale->format('Y-m-d') >= $delega->scadenza->format('Y-m-d'))) {
            $errori['scadenzaOriginale'] = 'La scadenza originale deve precedere la data di versamento della delega (' . $delega->scadenza->format('d/m/Y') . ').';
        }
        if ($dati->causale !== null) {
            if ($this->entity->sezione !== SezioneF24::inps) {
                $errori['causale'] = 'Per l\'INAIL le sanzioni civili sono richieste dall\'istituto: non si versano con una causale.';
            } else {
                $rigo = (object) ['importoDebito' => 1, 'importoCredito' => 0];
                foreach (self::CAMPI_RIGO as $campo) {
                    $rigo->$campo = $dati->$campo;
                }
                $errori += array_intersect_key((new ControlloDelega())->verificaDatiRiga($rigo, SezioneF24::inps, $delega->scadenza), array_flip(self::CAMPI_RIGO));
            }
        }
        foreach ($errori as $campo => $errore) {
            $this->formFilterError->{$campo . 'CustomMessage'} = $errore;
        }
        return count($errori) === 0;
    }
}
