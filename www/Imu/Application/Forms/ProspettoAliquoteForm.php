<?php

namespace Imu\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\ProspettoAliquote;
use Imu\Application\Enumerations\FattispecieImu;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Aliquote del comune inserite dallo studio; comune e anno sono impostati
 * dal controller. Le aliquote non indicate restano quelle di base di
 * legge.
 */
class ProspettoAliquoteForm extends BaseForm
{

    private const CAMPI_IMPORTO = ['detrazioneAbitazionePrincipale', 'importoMinimo'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return ProspettoAliquote::class;
    }

    /**
     * @return string[] proprietà delle aliquote previste nell'anno
     */
    public static function getCampiAliquota(int $anno): array
    {
        return array_map(fn(FattispecieImu $fattispecie) => $fattispecie->getProprietaProspetto(), FattispecieImu::perAnno($anno));
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (array_merge(self::getCampiAliquota($this->entity->anno), self::CAMPI_IMPORTO) as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza(str_replace('%', '', $this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        foreach (self::getCampiAliquota($this->entity->anno) as $campo) {
            $this->addFilterFieldMode($campo, FilterType::isNumeric, [], true);
        }
        $this->addFilterFieldMode('assimilazioneAnziani', FilterType::isBoolean)
                ->addFilterFieldMode('detrazioneAbitazionePrincipale', FilterType::isNumeric)
                ->addFilterFieldMode('importoMinimo', FilterType::isNumeric)
                ->addFilterFieldMode('numeroDelibera', FilterType::isMaxLimitString, [20], true)
                ->addFilterFieldMode('dataDelibera', FilterType::isDate, [], true)
                ->addFilterFieldMode('esenzioni', FilterType::isString, [], true)
                ->addFilterFieldMode('note', FilterType::isString, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        foreach (self::getCampiAliquota($this->entity->anno) as $campo) {
            $aliquota = $this->entityData->$campo;
            if (($aliquota !== null) && (($aliquota < 0) || ($aliquota > AliquotaApplicataForm::ALIQUOTA_MASSIMA))) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Aliquota da 0 a 1,14% (comma 755).';
                $valido = false;
            }
        }
        foreach (self::CAMPI_IMPORTO as $campo) {
            if ($this->entityData->$campo < 0) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'L\'importo non può essere negativo.';
                $valido = false;
            }
        }
        return $valido;
    }
}
