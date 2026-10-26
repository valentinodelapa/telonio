<?php

namespace Successioni\Application\Forms;

use Telonio\Application\HelperClasses\CodiceFiscale;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use SismaFramework\Orm\ExtendedClasses\StandardEntity;
use Successioni\Application\Entities\BeneSuccessione;
use Successioni\Application\Enumerations\QuadroBene;

/**
 * Bene dell'attivo: i campi dipendono dal quadro (impostato dal controller
 * con la dichiarazione e l'ordine). Per terreni e fabbricati l'immobile è
 * uno di quelli dell'Anagrafica.
 */
class BeneSuccessioneForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return BeneSuccessione::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['valore', 'valoreEsente', 'valorePrecedente', 'impostaEstero', 'quantita', 'dimensione'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, preg_replace('/[.\s€]/u', '', $this->request->input[$campo]));
            }
        }
        if (isset($this->request->input['quotaNumeratore']) && is_string($this->request->input['quotaNumeratore'])) {
            $this->addRequest('quotaNumeratore', str_replace(',', '.', trim($this->request->input['quotaNumeratore'])));
        }
        foreach (['codiceFiscaleEmittente', 'codiceTitolo', 'natura', 'ufficioIscrizione', 'numeroIscrizione', 'codiceStatoEstero', 'localitaEstero', 'indirizzoEstero'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, strtoupper(trim($this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $quadro = $this->entity->quadro;
        $this->addFilterFieldMode('codiceDiritto', FilterType::isEnumeration)
                ->addFilterFieldMode('quotaNumeratore', FilterType::isFloat)
                ->addFilterFieldMode('quotaDenominatore', FilterType::isInteger)
                ->addFilterFieldMode('valore', FilterType::isInteger, [], true)
                ->addFilterFieldMode('valorePrecedente', FilterType::isInteger, [], true)
                ->addFilterFieldMode('impostaEstero', FilterType::isInteger, [], true)
                ->addFilterFieldMode('beneEstero', FilterType::isBoolean);
        if ($quadro->isImmobile()) {
            // terreni e fabbricati all'estero non hanno l'immobile dell'Anagrafica (controllo in customFilter)
            $this->addFilterFieldMode('immobile', FilterType::isEntity, [], $quadro->isTavolare() === false)
                    ->addFilterFieldMode('azienda', FilterType::isEntity, [], true);
        } elseif (in_array($quadro, [QuadroBene::aeromobili, QuadroBene::navi], true) === false) {
            $this->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [$quadro === QuadroBene::aziende ? 150 : 250]);
        }
        if (in_array($quadro, [QuadroBene::terreni, QuadroBene::fabbricati], true)) {
            $this->addFilterFieldMode('discordanzaIntestatario', FilterType::isBoolean)
                    ->addFilterFieldMode('passaggiSenzaAtti', FilterType::isBoolean)
                    ->addFilterFieldMode('statoEstero', FilterType::isEntity, [], true)
                    ->addFilterFieldMode('codiceStatoEstero', FilterType::isMaxLimitString, [3], true)
                    ->addFilterFieldMode('localitaEstero', FilterType::isMaxLimitString, [50], true)
                    ->addFilterFieldMode('indirizzoEstero', FilterType::isMaxLimitString, [80], true);
        }
        match ($quadro) {
            QuadroBene::terreni, QuadroBene::terreniTavolare => $this->addFilterFieldMode('tipologiaTerreno', FilterType::isEnumeration)
                    ->addFilterFieldMode('natura', FilterType::isMaxLimitString, [2], true),
            QuadroBene::fabbricati, QuadroBene::fabbricatiTavolare => $this->addFilterFieldMode('dirittoAbitazione', FilterType::isEnumeration, [], true),
            QuadroBene::aziende => $this->addFilterFieldMode('codiceFiscaleEmittente', FilterType::isMaxLimitString, [16], true)
                    ->addFilterFieldMode('dataInventario', FilterType::isDate, [], true)
                    ->addFilterFieldMode('dataDeposito', FilterType::isDate, [], true)
                    ->addFilterFieldMode('cameraCommercio', FilterType::isMaxLimitString, [35], true),
            QuadroBene::aeromobili => $this->addFilterFieldMode('marcaModello', FilterType::isMaxLimitString, [45])
                    ->addFilterFieldMode('annoCostruzione', FilterType::isInteger)
                    ->addFilterFieldMode('nazionalita', FilterType::isMaxLimitString, [20])
                    ->addFilterFieldMode('annoIscrizione', FilterType::isInteger)
                    ->addFilterFieldMode('numeroIscrizione', FilterType::isMaxLimitString, [10]),
            QuadroBene::navi => $this->addFilterFieldMode('tipoUnita', FilterType::isEnumeration)
                    ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [250], true)
                    ->addFilterFieldMode('nazionalita', FilterType::isMaxLimitString, [20])
                    ->addFilterFieldMode('ufficioIscrizione', FilterType::isMaxLimitString, [6])
                    ->addFilterFieldMode('annoIscrizione', FilterType::isInteger)
                    ->addFilterFieldMode('numeroIscrizione', FilterType::isMaxLimitString, [10])
                    ->addFilterFieldMode('annoCostruzione', FilterType::isInteger)
                    ->addFilterFieldMode('dimensione', FilterType::isInteger),
            QuadroBene::titoli => $this->addFilterFieldMode('tipoTitolo', FilterType::isEnumeration)
                    ->addFilterFieldMode('valoreEsente', FilterType::isInteger, [], true)
                    ->addFilterFieldMode('codiceFiscaleEmittente', FilterType::isMaxLimitString, [16], true)
                    ->addFilterFieldMode('quantita', FilterType::isInteger, [], true)
                    ->addFilterFieldMode('codiceTitolo', FilterType::isMaxLimitString, [12], true)
                    ->addFilterFieldMode('tipoDocumento', FilterType::isEnumeration, [], true)
                    ->addFilterFieldMode('dataDocumento', FilterType::isDate, [], true),
            QuadroBene::altriBeni => $this->addFilterFieldMode('tipoCespite', FilterType::isEnumeration),
        };
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        $quadro = $this->entity->quadro;
        $valido = true;
        if (($dati->codiceDiritto !== null) && (in_array($dati->codiceDiritto, $quadro->getDirittiDefunto(), true) === false)) {
            $this->formFilterError->codiceDirittoCustomMessage = 'Diritto del defunto non ammesso in questo quadro.';
            $valido = false;
        }
        if (($dati->quotaNumeratore !== null) && ($dati->quotaDenominatore !== null) && (($dati->quotaNumeratore <= 0) || ($dati->quotaDenominatore <= 0) || ($dati->quotaNumeratore > $dati->quotaDenominatore))) {
            $this->formFilterError->quotaNumeratoreCustomMessage = 'Quota non valida: il numeratore non può superare il denominatore.';
            $valido = false;
        }
        if (($quadro === QuadroBene::titoli) && ($dati->tipoTitolo !== null)) {
            if ($dati->tipoTitolo->isEsente() && (($dati->valoreEsente === null) || ($dati->valore !== null))) {
                $this->formFilterError->valoreEsenteCustomMessage = 'Per i titoli esenti indicare solo il valore esente.';
                $valido = false;
            } elseif (($dati->tipoTitolo->isEsente() === false) && ($dati->valoreEsente !== null)) {
                $this->formFilterError->valoreEsenteCustomMessage = 'Il valore esente si indica solo per i titoli esenti.';
                $valido = false;
            }
        }
        if (($dati->azienda ?? null) !== null) {
            if (($dati->azienda->quadro !== QuadroBene::aziende) || ($dati->azienda->dichiarazioneSuccessione->id !== $this->entity->dichiarazioneSuccessione->id)) {
                $this->formFilterError->aziendaCustomMessage = 'Scegliere un\'azienda del quadro EN della dichiarazione.';
                $valido = false;
            }
        }
        if (in_array($quadro, [QuadroBene::aeromobili, QuadroBene::navi], true) && (($dati->annoCostruzione ?? null) !== null) && (($dati->annoIscrizione ?? null) !== null) && ($dati->annoIscrizione < $dati->annoCostruzione)) {
            $this->formFilterError->annoIscrizioneCustomMessage = 'L\'anno di iscrizione non può precedere quello di costruzione.';
            $valido = false;
        }
        if (in_array($quadro, [QuadroBene::titoli, QuadroBene::aziende], true) && ($dati->codiceFiscaleEmittente !== null) && (CodiceFiscale::isValidoNumerico($dati->codiceFiscaleEmittente) === false) && (CodiceFiscale::isValidoPersonaFisica($dati->codiceFiscaleEmittente) === false)) {
            $this->formFilterError->codiceFiscaleEmittenteCustomMessage = 'Codice fiscale dell\'emittente non valido.';
            $valido = false;
        }
        if (in_array($quadro, [QuadroBene::terreni, QuadroBene::fabbricati], true) && ($this->verificaEstero($dati) === false)) {
            $valido = false;
        }
        if (($dati->impostaEstero !== null) && ($dati->beneEstero === false) && ($quadro->isImmobile() === false)) {
            $this->formFilterError->impostaEsteroCustomMessage = 'L\'imposta estera si indica per i beni situati all\'estero.';
            $valido = false;
        }
        return $valido;
    }

    /**
     * Terreni e fabbricati: immobile dell'Anagrafica oppure, se all'estero,
     * Stato, codice dello Stato (elenco dei Paesi esteri delle istruzioni) e
     * indirizzo.
     */
    private function verificaEstero(StandardEntity $dati): bool
    {
        $valido = true;
        if ($dati->beneEstero === false) {
            if ($dati->immobile === null) {
                $this->formFilterError->immobileCustomMessage = 'Scegliere l\'immobile dell\'Anagrafica (o indicare che è all\'estero).';
                $valido = false;
            }
            return $valido;
        }
        if ($dati->immobile !== null) {
            $this->formFilterError->immobileCustomMessage = 'Per l\'immobile all\'estero non si collega l\'immobile dell\'Anagrafica.';
            $valido = false;
        }
        if ($dati->statoEstero === null) {
            $this->formFilterError->statoEsteroCustomMessage = 'Indicare lo Stato estero.';
            $valido = false;
        }
        if (($dati->codiceStatoEstero === null) || (preg_match('/^\d{3}$/', $dati->codiceStatoEstero) !== 1)) {
            $this->formFilterError->codiceStatoEsteroCustomMessage = 'Codice dello Stato estero di tre cifre (elenco dei Paesi esteri delle istruzioni).';
            $valido = false;
        }
        if ($dati->indirizzoEstero === null) {
            $this->formFilterError->indirizzoEsteroCustomMessage = 'Indicare l\'indirizzo dell\'immobile.';
            $valido = false;
        }
        if ($dati->discordanzaIntestatario || $dati->passaggiSenzaAtti) {
            $this->formFilterError->discordanzaIntestatarioCustomMessage = 'Non previsto per gli immobili all\'estero.';
            $valido = false;
        }
        return $valido;
    }
}
