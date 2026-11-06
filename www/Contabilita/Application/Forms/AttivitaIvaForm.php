<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\AttivitaIva;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Attività con contabilità separata; studio, soggetto e codice ATECO
 * (dal codice digitato) sono impostati dal controller.
 */
class AttivitaIvaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return AttivitaIva::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {

    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('numero', FilterType::isInteger)
                ->addFilterFieldMode('descrizione', FilterType::isLimitString, [2, 100])
                ->addFilterFieldMode('periodicita', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('regimeDetrazioneIva', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('opzioneOrdinaria', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('opzioneDal', FilterType::isInteger, [], true)
                ->addFilterFieldMode('opzioneAl', FilterType::isInteger, [], true)
                ->addFilterFieldMode('dalAnno', FilterType::isInteger)
                ->addFilterFieldMode('alAnno', FilterType::isInteger, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        if (($this->entityData->numero < 1) || ($this->entityData->numero > 99)) {
            $this->formFilterError->numeroCustomMessage = 'Da 1 a 99: è l\'ordine dei moduli della dichiarazione IVA.';
            return false;
        }
        if (($this->entityData->alAnno !== null) && ($this->entityData->alAnno < $this->entityData->dalAnno)) {
            $this->formFilterError->alAnnoCustomMessage = 'L\'ultimo anno non può precedere il primo.';
            return false;
        }
        return $this->verificaOpzione();
    }

    /**
     * Opzione per i modi ordinari: regime e primo anno vanno indicati insieme.
     */
    private function verificaOpzione(): bool
    {
        $opzione = $this->entityData->opzioneOrdinaria;
        $dal = $this->entityData->opzioneDal;
        $al = $this->entityData->opzioneAl;
        if (($opzione === null) && (($dal !== null) || ($al !== null))) {
            $this->formFilterError->opzioneOrdinariaCustomMessage = 'Indicare il regime a cui si è rinunciato.';
            return false;
        }
        if ($opzione === null) {
            return true;
        }
        if ($opzione === $this->entityData->regimeDetrazioneIva) {
            $this->formFilterError->opzioneOrdinariaCustomMessage = 'Il regime speciale dell\'attività non può coincidere con l\'opzione per i modi ordinari.';
            return false;
        }
        if ($dal === null) {
            $this->formFilterError->opzioneDalCustomMessage = 'Indicare il primo anno dell\'opzione.';
            return false;
        }
        if (($al !== null) && ($al < $dal)) {
            $this->formFilterError->opzioneAlCustomMessage = 'L\'ultimo anno non può precedere il primo.';
            return false;
        }
        return true;
    }
}
