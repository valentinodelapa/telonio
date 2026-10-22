<?php

namespace Imu\Application\Forms;

use Deleghe\Application\HelperClasses\Importo;
use Imu\Application\Entities\AccontoVersatoImu;
use Imu\Application\Enumerations\FattispecieImu;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Acconto versato fuori dalla procedura; soggetto e anno sono impostati dal
 * controller.
 */
class AccontoVersatoImuForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return AccontoVersatoImu::class;
    }

    /**
     * @return string[] codici tributo IMU ammessi
     */
    public static function getCodiciTributo(): array
    {
        $codici = [];
        foreach (FattispecieImu::cases() as $fattispecie) {
            $codici[] = $fattispecie->getCodiceTributo();
            if ($fattispecie->getCodiceTributoStato() !== null) {
                $codici[] = $fattispecie->getCodiceTributoStato();
            }
        }
        sort($codici);
        return $codici;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['importo']) && is_string($this->request->input['importo'])) {
            $this->addRequest('importo', Importo::normalizza($this->request->input['importo']));
        }
        if (isset($this->request->input['codiceComune']) && is_string($this->request->input['codiceComune'])) {
            $this->addRequest('codiceComune', strtoupper(trim($this->request->input['codiceComune'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codiceComune', FilterType::isMaxLimitString, [4])
                ->addFilterFieldMode('codiceTributo', FilterType::isMaxLimitString, [4])
                ->addFilterFieldMode('importo', FilterType::isNumeric)
                ->addFilterFieldMode('dataVersamento', FilterType::isDate, [], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        if (preg_match('/^[A-Z]\d{3}$/', (string) $this->entityData->codiceComune) !== 1) {
            $this->formFilterError->codiceComuneCustomMessage = 'Codice catastale del comune (es. H501).';
            $valido = false;
        }
        if (in_array($this->entityData->codiceTributo, self::getCodiciTributo(), true) === false) {
            $this->formFilterError->codiceTributoCustomMessage = 'Codice tributo IMU non valido.';
            $valido = false;
        }
        if ($this->entityData->importo < 0) {
            $this->formFilterError->importoCustomMessage = 'L\'importo non può essere negativo.';
            $valido = false;
        }
        return $valido;
    }
}
