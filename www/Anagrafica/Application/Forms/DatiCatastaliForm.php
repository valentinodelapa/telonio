<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Enumerations\CausaleClassamento;
use Anagrafica\Application\Enumerations\TipoCatasto;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Versione dei dati di classamento. L'immobile è impostato dal controller:
 * i campi dipendono dal tipo di catasto (fabbricati o terreni).
 */
class DatiCatastaliForm extends BaseForm
{

    private const CAMPI_NUMERICI = ['consistenza', 'superficie', 'rendita', 'redditoDominicale', 'redditoAgrario'];

    private ?string $prefissoPossesso = null;

    /**
     * Nel form combinato di inserimento dell'immobile con il possesso la
     * decorrenza lasciata vuota è quella del possesso (campi inviati come
     * prefisso[campo], vedi PossessoForm::conPrefisso): così il classamento
     * copre tutto il possesso e non parte dal giorno dell'inserimento.
     */
    public function conDecorrenzaDelPossesso(string $prefisso): self
    {
        $this->prefissoPossesso = $prefisso;
        return $this;
    }

    #[\Override]
    protected static function getEntityName(): string
    {
        return DatiCatastali::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        $possesso = $this->prefissoPossesso === null ? null : ($this->request->input[$this->prefissoPossesso] ?? null);
        if (is_array($possesso) && is_string($possesso['validoDal'] ?? null) && (trim((string) ($this->request->input['validoDal'] ?? '')) === '')) {
            $this->addRequest('validoDal', $possesso['validoDal']);
        }
        foreach (self::CAMPI_NUMERICI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, self::normalizzaNumero($this->request->input[$campo]));
            }
        }
        foreach (['classe', 'qualitaColtura'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(trim($this->request->input[$campo])));
            }
        }
    }

    /**
     * Accetta sia "1.050,25" sia "1050.25".
     */
    private static function normalizzaNumero(string $valore): string
    {
        $valore = str_replace(' ', '', trim($valore));
        return str_contains($valore, ',') ? str_replace(['.', ','], ['', '.'], $valore) : $valore;
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('causale', FilterType::isEnumeration)
                ->addFilterFieldMode('classe', FilterType::isMaxLimitString, [10], true)
                ->addFilterFieldMode('superficie', FilterType::isNumeric, [], true);
        if ($this->entity->immobile->tipoCatasto === TipoCatasto::fabbricati) {
            $this->addFilterFieldMode('categoria', FilterType::isEnumeration)
                    ->addFilterFieldMode('consistenza', FilterType::isNumeric, [], true)
                    ->addFilterFieldMode('rendita', FilterType::isNumeric, [], true);
        } else {
            $this->addFilterFieldMode('qualitaColtura', FilterType::isMaxLimitString, [50])
                    ->addFilterFieldMode('redditoDominicale', FilterType::isNumeric)
                    ->addFilterFieldMode('redditoAgrario', FilterType::isNumeric);
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
        foreach (self::CAMPI_NUMERICI as $campo) {
            if (($this->entityData->$campo !== null) && ($this->entityData->$campo < 0)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Il valore non può essere negativo.';
                $valido = false;
            }
        }
        $causale = $this->entityData->causale;
        if (($causale !== null) && (in_array($causale, CausaleClassamento::getAmmesse($this->entity->immobile->tipoCatasto), true) === false)) {
            $this->formFilterError->causaleCustomMessage = 'Causale non ammessa per i terreni.';
            $valido = false;
        }
        $categoria = $this->entityData->categoria;
        if ($categoria !== null) {
            if ($categoria->haRendita() && ($this->entityData->rendita === null)) {
                $this->formFilterError->renditaCustomMessage = 'Indicare la rendita catastale.';
                $valido = false;
            } elseif (($categoria->haRendita() === false) && ($this->entityData->rendita !== null)) {
                $this->formFilterError->renditaCustomMessage = 'Le unità della categoria ' . $categoria->value . ' non hanno rendita.';
                $valido = false;
            }
        }
        return $valido;
    }
}
