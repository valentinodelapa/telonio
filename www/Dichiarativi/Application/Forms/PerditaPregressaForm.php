<?php

namespace Dichiarativi\Application\Forms;

use Dichiarativi\Application\Entities\PerditaPregressa;
use Dichiarativi\Application\Enumerations\TipoPerdita;
use Dichiarativi\Application\Models\PerditaPregressaModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Perdite residue di un periodo d'imposta: studio e contribuente sono
 * impostati dal controller; un solo archivio per anno e tipo.
 */
class PerditaPregressaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return PerditaPregressa::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['limitata', 'piena'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $valore = str_replace(['.', ' '], '', trim($this->request->input[$campo]));
                $this->addRequest($campo, $valore === '' ? '0' : $valore);
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('anno', FilterType::isInteger)
                ->addFilterFieldMode('tipo', FilterType::isEnumeration)
                ->addFilterFieldMode('limitata', FilterType::isInteger)
                ->addFilterFieldMode('piena', FilterType::isInteger)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        if (((int) $dati->anno < 2000) || ((int) $dati->anno > 2100)) {
            $this->formFilterError->annoCustomMessage = 'Anno non valido.';
            return false;
        }
        if (((int) $dati->limitata < 0) || ((int) $dati->piena < 0)) {
            $this->formFilterError->limitataCustomMessage = 'Le perdite si indicano senza segno.';
            return false;
        }
        $tipo = $dati->tipo instanceof TipoPerdita ? $dati->tipo : TipoPerdita::from((string) $dati->tipo);
        $esistente = (new PerditaPregressaModel())->getPerAnno($this->entity->soggetto, (int) $dati->anno, $tipo);
        if (($esistente !== null) && ((isset($this->entity->id) ? $this->entity->id : null) !== $esistente->id)) {
            $this->formFilterError->annoCustomMessage = 'Per l\'anno e il tipo c\'è già un archivio delle perdite: modificalo.';
            return false;
        }
        return true;
    }
}
