<?php

namespace Imu\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\UtilizzoMistoEnc;
use Imu\Application\Enumerations\AttivitaEnc;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Utilizzo dell'immobile dell'ente non commerciale per l'anno; soggetto,
 * immobile e anno sono impostati dal controller. Le attività arrivano come
 * elenco di caselle (attivita[]).
 */
class UtilizzoMistoEncForm extends BaseForm
{

    public const CAMPI_NUMERICI = [
        'didatticaCorrispettivoMedio', 'didatticaCostoMedioStudente', 'didatticaSuperficie', 'didatticaSuperficieGiorni', 'didatticaSoggetti', 'didatticaSoggettiGiorni', 'didatticaGiorni',
        'altreCorrispettivoMedio', 'altreCorrispettivoMercato', 'altreSuperficie', 'altreSuperficieGiorni', 'altreSoggetti', 'altreSoggettiGiorni', 'altreGiorni',
    ];

    #[\Override]
    protected static function getEntityName(): string
    {
        return UtilizzoMistoEnc::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        $attivita = $this->request->input['attivita'] ?? [];
        $codici = is_array($attivita) ? array_filter($attivita, fn($codice) => is_string($codice) && (AttivitaEnc::tryFrom($codice) !== null)) : [];
        $this->addRequest('attivita', implode(',', $codici));
        foreach (self::CAMPI_NUMERICI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza(str_replace('%', '', $this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('attivita', FilterType::isMaxLimitString, [40], true)
                ->addFilterFieldMode('esente', FilterType::isBoolean)
                ->addFilterFieldMode('tipoConcessione', FilterType::isMaxLimitString, [1], true)
                ->addFilterFieldMode('comodatario', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
        foreach (self::CAMPI_NUMERICI as $campo) {
            $this->addFilterFieldMode($campo, FilterType::isNumeric, [], true);
        }
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        if (((string) $dati->attivita) === '') {
            $this->formFilterError->attivitaCustomMessage = 'Indicare almeno un\'attività svolta nell\'immobile.';
            return false;
        }
        if (($dati->tipoConcessione !== null) && (in_array($dati->tipoConcessione, ['1', '2'], true) === false)) {
            $this->formFilterError->tipoConcessioneCustomMessage = 'Valore non valido.';
            return false;
        }
        foreach (self::CAMPI_NUMERICI as $campo) {
            if (($dati->$campo !== null) && ($dati->$campo < 0)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Il valore non può essere negativo.';
                return false;
            }
            if (($dati->$campo !== null) && (str_contains($campo, 'Corrispettivo') === false) && (str_contains($campo, 'Costo') === false) && ($dati->$campo > 100)) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Percentuale da 0 a 100.';
                return false;
            }
        }
        return true;
    }
}
