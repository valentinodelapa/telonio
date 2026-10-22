<?php

namespace Imu\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\RigoDichiarazioneImu;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Quadro descrittivo di un immobile della dichiarazione; dichiarazione e
 * numerazione sono impostate dal controller.
 */
class RigoDichiarazioneImuForm extends BaseForm
{

    private const IMPORTI = ['valore', 'percentualePossesso', 'detrazione'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return RigoDichiarazioneImu::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::IMPORTI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza(str_replace('%', '', $this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('immobile', FilterType::isEntity)
                ->addFilterFieldMode('quadro', FilterType::isMaxLimitString, [1])
                ->addFilterFieldMode('caratteristica', FilterType::isEnumeration)
                ->addFilterFieldMode('valore', FilterType::isNumeric)
                ->addFilterFieldMode('percentualePossesso', FilterType::isNumeric)
                ->addFilterFieldMode('detrazione', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('equiparazione', FilterType::isMaxLimitString, [1], true)
                ->addFilterFieldMode('riduzione', FilterType::isMaxLimitString, [1])
                ->addFilterFieldMode('esenzione', FilterType::isMaxLimitString, [1])
                ->addFilterFieldMode('acquisto', FilterType::isBoolean)
                ->addFilterFieldMode('cessione', FilterType::isBoolean)
                ->addFilterFieldMode('altro', FilterType::isBoolean)
                ->addFilterFieldMode('altroDescrizione', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('dataVariazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('inizioTermine', FilterType::isMaxLimitString, [1], true)
                ->addFilterFieldMode('agenziaEntrate', FilterType::isMaxLimitString, [24], true)
                ->addFilterFieldMode('estremiTitolo', FilterType::isMaxLimitString, [24], true)
                ->addFilterFieldMode('nonUtilizzabileTipo', FilterType::isMaxLimitString, [1], true)
                ->addFilterFieldMode('nonUtilizzabileAutorita', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('nonUtilizzabileData', FilterType::isDate, [], true)
                ->addFilterFieldMode('motivo', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        $enc = $this->entity->dichiarazioneImu->isEnc();
        $controlli = [
            'quadro' => in_array($dati->quadro, $enc ? ['A', 'B'] : ['A'], true),
            'caratteristica' => ($enc === false) || $dati->caratteristica->isAmmessaEnc(),
            'riduzione' => isset(($enc ? RigoDichiarazioneImu::RIDUZIONI_ENC : RigoDichiarazioneImu::RIDUZIONI)[$dati->riduzione]),
            'esenzione' => isset(RigoDichiarazioneImu::ESENZIONI[$dati->esenzione]),
            'equiparazione' => ($dati->equiparazione === null) || isset(RigoDichiarazioneImu::EQUIPARAZIONI[$dati->equiparazione]),
            'inizioTermine' => in_array($dati->inizioTermine, [null, 'I', 'T'], true),
            'percentualePossesso' => ($dati->percentualePossesso > 0) && ($dati->percentualePossesso <= 100),
            'valore' => $dati->valore >= 0,
            'altroDescrizione' => ($dati->altro === false) || ($dati->altroDescrizione !== null),
            'dataVariazione' => (($dati->acquisto || $dati->cessione || $dati->altro || ($dati->inizioTermine !== null)) === false) || ($dati->dataVariazione !== null),
            'nonUtilizzabileTipo' => ($dati->esenzione !== '1') || $enc || (in_array($dati->nonUtilizzabileTipo, ['1', '2'], true) && ($dati->nonUtilizzabileAutorita !== null) && ($dati->nonUtilizzabileData !== null)),
        ];
        $messaggi = [
            'quadro' => 'Quadro non valido.', 'caratteristica' => 'Caratteristica non ammessa nel modello ENC.', 'riduzione' => 'Riduzione non valida.', 'esenzione' => 'Esenzione non valida.',
            'equiparazione' => 'Equiparazione non valida.', 'inizioTermine' => 'Valore non valido.', 'percentualePossesso' => 'Percentuale da 0 a 100.', 'valore' => 'Il valore non può essere negativo.',
            'altroDescrizione' => 'Descrivere la vicenda.', 'dataVariazione' => 'Indicare la data della variazione.',
            'nonUtilizzabileTipo' => 'Per l\'esenzione degli immobili occupati indicare tipo, autorità e data della denuncia.',
        ];
        $valido = true;
        foreach ($controlli as $campo => $esito) {
            if ($esito === false) {
                $this->formFilterError->{$campo . 'CustomMessage'} = $messaggi[$campo];
                $valido = false;
            }
        }
        return $valido;
    }
}
