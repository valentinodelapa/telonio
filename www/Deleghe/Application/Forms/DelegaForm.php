<?php

namespace Deleghe\Application\Forms;

use Deleghe\Application\Entities\Delega;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Services\ControlloDelega;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Testata della delega; contribuente, studio e stato sono impostati dal
 * controller.
 */
class DelegaForm extends BaseForm
{

    private const CAMPI_MAIUSCOLI = ['codiceFiscaleCoobbligato', 'codiceUfficio', 'codiceAtto', 'identificativoOperazione'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return Delega::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::CAMPI_MAIUSCOLI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(str_replace(' ', '', $this->request->input[$campo])));
            }
        }
        if (isset($this->request->input['detrazioneImu']) && is_string($this->request->input['detrazioneImu'])) {
            $this->addRequest('detrazioneImu', Importo::normalizza($this->request->input['detrazioneImu']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('modello', FilterType::isEnumeration)
                ->addFilterFieldMode('scadenza', FilterType::isDate)
                ->addFilterFieldMode('contoAddebito', FilterType::isEntity, [], true)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('codiceFiscaleCoobbligato', FilterType::isMaxLimitString, [16], true)
                ->addFilterFieldMode('codiceIdentificativo', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('codiceUfficio', FilterType::isMaxLimitString, [3], true)
                ->addFilterFieldMode('codiceAtto', FilterType::isMaxLimitString, [11], true)
                ->addFilterFieldMode('identificativoOperazione', FilterType::isMaxLimitString, [18], true)
                ->addFilterFieldMode('detrazioneImu', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('note', FilterType::isString, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $soggetto = $this->entity->soggetto;
        $conto = $this->entityData->contoAddebito;
        if (($conto !== null) && ($conto->soggetto->studio->id !== $this->entity->studio->id)) {
            $this->formFilterError->contoAddebitoCustomMessage = 'Conto non trovato.';
            return false;
        }
        $errori = (new ControlloDelega())->verificaTestata($this->entityData, $soggetto->codiceFiscale);
        foreach ($errori as $campo => $errore) {
            $this->formFilterError->{$campo . 'CustomMessage'} = $errore;
        }
        return count($errori) === 0;
    }
}
