<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Enumerations\RuoloLegame;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

class LegameForm extends BaseForm
{

    private const SPUNTE = ['disabile', 'convivente', 'affidamento'];
    private const PERCENTUALI = ['100', '50', '0', 'C'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return Legame::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['quotaPartecipazione']) && is_string($this->request->input['quotaPartecipazione'])) {
            $this->addRequest('quotaPartecipazione', str_replace(',', '.', trim($this->request->input['quotaPartecipazione'])));
        }
        if (isset($this->request->input['percentualeDetrazione']) && is_string($this->request->input['percentualeDetrazione'])) {
            $this->addRequest('percentualeDetrazione', mb_strtoupper(trim($this->request->input['percentualeDetrazione'])));
        }
        foreach (self::SPUNTE as $campo) {
            $this->addRequest($campo, ($this->request->input[$campo] ?? '') === '1' ? '1' : '0');
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('soggettoCollegato', FilterType::isEntity)
                ->addFilterFieldMode('ruolo', FilterType::isEnumeration)
                ->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('quotaPartecipazione', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('disabile', FilterType::isBoolean)
                ->addFilterFieldMode('convivente', FilterType::isBoolean)
                ->addFilterFieldMode('percentualeDetrazione', FilterType::isMaxLimitString, [3], true)
                ->addFilterFieldMode('affidamento', FilterType::isBoolean);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $collegato = $this->entityData->soggettoCollegato;
        if ($collegato !== null) {
            if ($collegato->studio->id !== $this->entity->soggetto->studio->id) {
                $this->formFilterError->soggettoCollegatoCustomMessage = 'Soggetto non trovato.';
                $valido = false;
            } elseif ($collegato->id === $this->entity->soggetto->id) {
                $this->formFilterError->soggettoCollegatoCustomMessage = 'Un soggetto non può essere collegato a sé stesso.';
                $valido = false;
            }
        }
        if (($this->entityData->ruolo === RuoloLegame::collaboratoreFamiliare) && ($this->entity->soggetto->isPersonaFisica() === false)) {
            $this->formFilterError->ruoloCustomMessage = 'Il collaboratore familiare è previsto solo per l\'impresa familiare di una persona fisica.';
            $valido = false;
        }
        $valido = $this->controllaFamiliare() && $valido;
        $quota = $this->entityData->quotaPartecipazione;
        if ($quota !== null) {
            if (($this->entityData->ruolo !== null) && ($this->entityData->ruolo->prevedeQuota() === false)) {
                $this->formFilterError->quotaPartecipazioneCustomMessage = 'La quota è prevista solo per soci, eredi e collaboratori familiari.';
                $valido = false;
            } elseif (($quota <= 0) || ($quota > 100)) {
                $this->formFilterError->quotaPartecipazioneCustomMessage = 'La quota deve essere una percentuale maggiore di 0 e fino a 100.';
                $valido = false;
            }
        }
        return $valido;
    }

    /**
     * Legami familiari: tra persone fisiche; disabilità, percentuale e
     * affidamento solo per i figli (affidamento con la detrazione al 100%
     * o al primo figlio senza l'altro genitore); convivenza per ascendenti
     * e altri familiari.
     */
    private function controllaFamiliare(): bool
    {
        $dati = $this->entityData;
        $ruolo = $dati->ruolo instanceof RuoloLegame ? $dati->ruolo : null;
        $valido = true;
        $errore = function (string $campo, string $messaggio) use (&$valido): void {
            $this->formFilterError->{$campo . 'CustomMessage'} = $messaggio;
            $valido = false;
        };
        if (($ruolo !== null) && $ruolo->isFamiliare()) {
            if ($this->entity->soggetto->isPersonaFisica() === false) {
                $errore('ruolo', 'I legami familiari sono previsti tra persone fisiche.');
            } elseif (($dati->soggettoCollegato !== null) && ($dati->soggettoCollegato->isPersonaFisica() === false)) {
                $errore('soggettoCollegato', 'Il familiare deve essere una persona fisica.');
            }
        }
        $figlio = $ruolo === RuoloLegame::figlio;
        if ($dati->disabile && ($figlio === false)) {
            $errore('disabile', 'La disabilità si indica per i figli.');
        }
        if ($dati->convivente && (($ruolo === null) || ($ruolo->richiedeConvivenza() === false))) {
            $errore('convivente', 'La convivenza si indica per ascendenti e altri familiari.');
        }
        $percentuale = $dati->percentualeDetrazione;
        if (($percentuale !== null) && ($percentuale !== '')) {
            if ($figlio === false) {
                $errore('percentualeDetrazione', 'La percentuale della detrazione si indica per i figli.');
            } elseif (in_array($percentuale, self::PERCENTUALI, true) === false) {
                $errore('percentualeDetrazione', 'Indicare 100, 50, 0 oppure C.');
            }
        }
        if ($dati->affidamento && (($figlio === false) || in_array($percentuale, ['50', '0'], true))) {
            $errore('affidamento', 'L\'affidamento si indica per i figli con la detrazione al 100 per cento.');
        }
        return $valido;
    }
}
