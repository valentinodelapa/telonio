<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\Possesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Versione di un possesso. Soggetto e immobile impostati dal controller non
 * sono modificabili dal form; quelli mancanti si scelgono nel form.
 */
class PossessoForm extends BaseForm
{

    private ?string $prefisso = null;

    #[\Override]
    protected static function getEntityName(): string
    {
        return Possesso::class;
    }

    /**
     * Nel form combinato di inserimento dell'immobile i campi del possesso
     * sono inviati come prefisso[campo], per non confondersi con quelli dei
     * dati catastali (es. validoDal).
     */
    public function conPrefisso(string $prefisso): self
    {
        $this->prefisso = $prefisso;
        return $this;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (($this->prefisso !== null) && is_array($this->request->input[$this->prefisso] ?? null)) {
            foreach ($this->request->input[$this->prefisso] as $campo => $valore) {
                $this->addRequest($campo, $valore);
            }
        }
        if (isset($this->request->input['quota']) && is_string($this->request->input['quota'])) {
            $this->addRequest('quota', str_replace(',', '.', trim($this->request->input['quota'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('titolo', FilterType::isEnumeration)
                ->addFilterFieldMode('quota', FilterType::isNumeric)
                ->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('utilizzo', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
        if (isset($this->entity->soggetto) === false) {
            $this->addFilterFieldMode('soggetto', FilterType::isEntity);
        }
        if (isset($this->entity->immobile) === false) {
            $this->addFilterFieldMode('immobile', FilterType::isEntity);
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
        $quota = $this->entityData->quota;
        if (($quota !== null) && (($quota <= 0) || ($quota > 100))) {
            $this->formFilterError->quotaCustomMessage = 'La quota deve essere una percentuale maggiore di 0 e fino a 100.';
            $valido = false;
        }
        $soggetto = isset($this->entity->soggetto) ? $this->entity->soggetto : $this->entityData->soggetto;
        $immobile = isset($this->entity->immobile) ? $this->entity->immobile : $this->entityData->immobile;
        if (($soggetto !== null) && ($immobile !== null) && ($soggetto->studio->id !== $immobile->studio->id)) {
            $campo = isset($this->entity->soggetto) ? 'immobile' : 'soggetto';
            $this->formFilterError->{$campo . 'CustomMessage'} = 'Elemento non trovato nell\'archivio dello studio.';
            $valido = false;
        }
        $utilizzo = $this->entityData->utilizzo;
        if (in_array($utilizzo, [UtilizzoImmobile::abitazionePrincipale, UtilizzoImmobile::pertinenzaAbitazionePrincipale], true) && ($soggetto !== null) && ($soggetto->isPersonaFisica() === false)) {
            $this->formFilterError->utilizzoCustomMessage = 'L\'abitazione principale è prevista solo per le persone fisiche.';
            $valido = false;
        }
        return $valido;
    }
}
