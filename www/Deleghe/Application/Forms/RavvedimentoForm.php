<?php

namespace Deleghe\Application\Forms;

use Deleghe\Application\Entities\Ravvedimento;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Services\CalcoloRavvedimento;
use Deleghe\Application\Services\ControlloDelega;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Dati del versamento da regolarizzare con il ravvedimento: delega e
 * sezione sono impostate dal controller. I termini delle dichiarazioni e i
 * codici di sanzione e interessi, se non indicati, sono proposti dalla
 * regola del tributo (RegolaRavvedimento).
 */
class RavvedimentoForm extends BaseForm
{

    private const CAMPI_SEZIONE = ['codiceEnte', 'rateazione', 'annoRiferimento', 'acconto', 'saldo', 'numeroImmobili'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return Ravvedimento::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['codiceEnte', 'rateazione'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(str_replace(' ', '', $this->request->input[$campo])));
            }
        }
        if (isset($this->request->input['importoTributo']) && is_string($this->request->input['importoTributo'])) {
            $this->addRequest('importoTributo', Importo::normalizza($this->request->input['importoTributo']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $sezione = $this->entity->sezione;
        $this->addFilterFieldMode('codiceTributo', FilterType::isEntity)
                ->addFilterFieldMode('importoTributo', FilterType::isNumeric)
                ->addFilterFieldMode('scadenzaOriginale', FilterType::isDate)
                ->addFilterFieldMode('termineDichiarazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('termineDichiarazioneSuccessiva', FilterType::isDate, [], true)
                ->addFilterFieldMode('codiceSanzione', FilterType::isEntity, [], true)
                ->addFilterFieldMode('codiceInteressi', FilterType::isEntity, [], true)
                ->addFilterFieldMode('circostanza', FilterType::isEnumeration)
                ->addFilterFieldMode('rateazione', FilterType::isMaxLimitString, [4], true)
                ->addFilterFieldMode('annoRiferimento', FilterType::isInteger, [], true);
        if ($sezione->usaCampo('codiceEnte')) {
            $this->addFilterFieldMode('codiceEnte', FilterType::isMaxLimitString, [5], true);
        }
        if ($sezione->usaCampo('acconto')) {
            $this->addFilterFieldMode('acconto', FilterType::isBoolean)
                    ->addFilterFieldMode('saldo', FilterType::isBoolean)
                    ->addFilterFieldMode('numeroImmobili', FilterType::isInteger, [], true);
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
        $delega = $this->entity->delega;
        $errori = [];
        if (($dati->circostanza !== null) && ($dati->scadenzaOriginale !== null)) {
            try {
                $dati->circostanza->getRiduzione($dati->scadenzaOriginale->format('Y-m-d') >= CalcoloRavvedimento::DECORRENZA_RIFORMA);
            } catch (\InvalidArgumentException $errore) {
                $errori['circostanza'] = $errore->getMessage();
            }
        }
        if (($dati->importoTributo !== null) && ($dati->importoTributo <= 0)) {
            $errori['importoTributo'] = 'Indicare l\'importo non versato.';
        }
        if (($dati->scadenzaOriginale !== null) && ($dati->scadenzaOriginale->format('Y-m-d') >= $delega->scadenza->format('Y-m-d'))) {
            $errori['scadenzaOriginale'] = 'La scadenza originale deve precedere la data di versamento della delega (' . $delega->scadenza->format('d/m/Y') . ').';
        }
        $rigo = (object) ['codiceTributo' => $dati->codiceTributo, 'importoDebito' => $dati->importoTributo, 'importoCredito' => 0];
        foreach (self::CAMPI_SEZIONE as $campo) {
            $rigo->$campo = $dati->$campo;
        }
        foreach (['codiceSanzione', 'codiceInteressi'] as $campo) {
            if (($dati->$campo !== null) && ($dati->$campo->sezione !== $this->entity->sezione->getSezioneCodici())) {
                $errori[$campo] = 'Il codice ' . $dati->$campo->codice . ' non è della sezione ' . $this->entity->sezione->getLabel() . '.';
            }
        }
        $errori += array_intersect_key((new ControlloDelega())->verificaDatiRiga($rigo, $this->entity->sezione, $delega->scadenza), array_flip(['codiceTributo', 'codiceEnte', 'rateazione', 'annoRiferimento', 'numeroImmobili']));
        foreach ($errori as $campo => $errore) {
            $this->formFilterError->{$campo . 'CustomMessage'} = $errore;
        }
        return count($errori) === 0;
    }
}
