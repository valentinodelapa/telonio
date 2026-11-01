<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\RegistrazioneContabile;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Testata della registrazione di prima nota; studio, soggetto, esercizio
 * e causale sono impostati dal controller. Le righe IVA e in partita
 * doppia sono form separati (RigaIvaForm, RigaContabileForm), uno per
 * riga; i controlli d'insieme sono in Services\ControlloRegistrazione.
 */
class RegistrazioneContabileForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return RegistrazioneContabile::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['descrizione', 'numeroDocumento'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim($this->request->input[$campo]));
            }
        }
        if (isset($this->request->input['totaleDocumento']) && is_string($this->request->input['totaleDocumento'])) {
            $this->addRequest('totaleDocumento', Importo::normalizza($this->request->input['totaleDocumento']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $iva = $this->entity->causale->isIva();
        $this->addFilterFieldMode('dataRegistrazione', FilterType::isDate)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('controparte', FilterType::isEntity, [], true)
                ->addFilterFieldMode('numeroDocumento', FilterType::isMaxLimitString, [30], true)
                ->addFilterFieldMode('dataDocumento', FilterType::isDate, [], true)
                ->addFilterFieldMode('totaleDocumento', FilterType::isNumeric, [], true);
        if ($iva) {
            $this->addFilterFieldMode('registroIva', FilterType::isEntity)
                    ->addFilterFieldMode('dataCompetenzaIva', FilterType::isDate)
                    ->addFilterFieldMode('esigibilita', FilterType::isEnumeration);
        }
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $controparte = $this->entityData->controparte ?? null;
        if (($controparte !== null) && ($controparte->soggetto->id !== $this->entity->soggetto->id)) {
            $this->formFilterError->controparteCustomMessage = 'Cliente o fornitore di un\'altra azienda.';
            return false;
        }
        return true;
    }
}
