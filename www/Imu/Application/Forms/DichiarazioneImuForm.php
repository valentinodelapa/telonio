<?php

namespace Imu\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\DichiarazioneImu;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Frontespizio della dichiarazione IMU e, per gli enti non commerciali,
 * determinazione dell'imposta (quadri C e D). Contribuente, anno e comune
 * sono impostati dal controller.
 */
class DichiarazioneImuForm extends BaseForm
{

    public const IMPORTI_ENC = ['impostaDovuta', 'eccedenzaPrecedente', 'eccedenzaCompensata', 'rateVersate', 'creditoRimborso', 'creditoCompensazione'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return DichiarazioneImu::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::IMPORTI_ENC as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza($this->request->input[$campo]));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipologia', FilterType::isEnumeration)
                ->addFilterFieldMode('dichiarante', FilterType::isEntity, [], true)
                ->addFilterFieldMode('codiceCarica', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('intermediario', FilterType::isEntity, [], true)
                ->addFilterFieldMode('dataImpegno', FilterType::isDate, [], true)
                ->addFilterFieldMode('dataDichiarazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('annotazioni', FilterType::isMaxLimitString, [500], true);
        if ($this->entity->isEnc()) {
            foreach (self::IMPORTI_ENC as $campo) {
                $this->addFilterFieldMode($campo, FilterType::isNumeric, [], true);
            }
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
        if (($dati->dichiarante !== null) && ($dati->codiceCarica === null)) {
            $this->formFilterError->codiceCaricaCustomMessage = 'Indicare il codice di carica del dichiarante.';
            return false;
        }
        if (($dati->dichiarante !== null) && ($dati->dichiarante->isPersonaFisica() === false)) {
            $this->formFilterError->dichiaranteCustomMessage = 'Il dichiarante deve essere una persona fisica.';
            return false;
        }
        if (($dati->intermediario !== null) && ($dati->dataImpegno === null)) {
            $this->formFilterError->dataImpegnoCustomMessage = 'Indicare la data dell\'impegno a trasmettere.';
            return false;
        }
        if ($this->entity->isEnc()) {
            $credito = max(0.0, -round((float) $dati->impostaDovuta - (float) $dati->eccedenzaPrecedente + (float) $dati->eccedenzaCompensata - (float) $dati->rateVersate, 2));
            if (round((float) $dati->creditoRimborso + (float) $dati->creditoCompensazione, 2) > $credito) {
                $this->formFilterError->creditoRimborsoCustomMessage = 'Rimborso e compensazione non possono superare il credito risultante (€ ' . number_format($credito, 2, ',', '.') . ').';
                return false;
            }
        }
        return true;
    }
}
