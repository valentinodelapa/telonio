<?php

namespace Deleghe\Application\Forms;

use Deleghe\Application\Entities\Credito;
use Deleghe\Application\HelperClasses\Importo;
use Deleghe\Application\Services\ControlloDelega;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Credito del contribuente: soggetto e sezione sono impostati dal
 * controller; i campi del rigo sono quelli della sezione che il credito
 * riporta nel modello (Credito::CAMPI_RIGO) e si verificano come un rigo a
 * credito alla data da cui è utilizzabile.
 */
class CreditoForm extends BaseForm
{

    private const FILTRI = [
        'codiceTributo' => [FilterType::isEntity, []],
        'codiceEnte' => [FilterType::isMaxLimitString, [5]],
        'codiceSede' => [FilterType::isMaxLimitString, [5]],
        'causale' => [FilterType::isMaxLimitString, [4]],
        'posizione' => [FilterType::isMaxLimitString, [17]],
        'cifraControllo' => [FilterType::isMaxLimitString, [2]],
        'numeroRiferimento' => [FilterType::isMaxLimitString, [6]],
        'rateazione' => [FilterType::isMaxLimitString, [4]],
        'annoRiferimento' => [FilterType::isInteger, []],
        'periodoDa' => [FilterType::isMaxLimitString, [7]],
        'periodoA' => [FilterType::isMaxLimitString, [7]],
    ];
    private const CAMPI_TESTO = ['codiceEnte', 'codiceSede', 'causale', 'posizione', 'cifraControllo', 'numeroRiferimento', 'rateazione', 'periodoDa', 'periodoA'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return Credito::class;
    }

    /**
     * @return string[] campi del rigo previsti dalla sezione del credito
     */
    public static function getCampiSezione(Credito $credito): array
    {
        return array_values(array_intersect($credito->sezione->getCampi(), Credito::CAMPI_RIGO));
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::CAMPI_TESTO as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(str_replace(' ', '', $this->request->input[$campo])));
            }
        }
        if (isset($this->request->input['importo']) && is_string($this->request->input['importo'])) {
            $this->addRequest('importo', Importo::normalizza($this->request->input['importo']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        foreach (self::getCampiSezione($this->entity) as $campo) {
            [$filtro, $parametri] = self::FILTRI[$campo];
            $this->addFilterFieldMode($campo, $filtro, $parametri, true);
        }
        $this->addFilterFieldMode('importo', FilterType::isNumeric)
                ->addFilterFieldMode('utilizzabileDal', FilterType::isDate, [], true)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('note', FilterType::isString, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $dati = $this->entityData;
        if (($dati->importo === null) || ($dati->importo <= 0)) {
            $this->formFilterError->importoCustomMessage = 'Indicare l\'importo del credito.';
            return false;
        }
        $rigo = (object) ['importoDebito' => 0.0, 'importoCredito' => (float) $dati->importo];
        foreach ($this->entity->sezione->getCampi() as $campo) {
            $rigo->$campo = in_array($campo, Credito::CAMPI_RIGO, true) ? $dati->$campo : null;
        }
        foreach (['ravvedimento', 'immobiliVariati', 'acconto', 'saldo'] as $campo) {
            $rigo->$campo = false;
        }
        $data = $dati->utilizzabileDal ?? new \DateTimeImmutable('today');
        $errori = array_intersect_key((new ControlloDelega())->verificaDatiRiga($rigo, $this->entity->sezione, $data), array_flip(self::getCampiSezione($this->entity)));
        foreach ($errori as $campo => $errore) {
            $this->formFilterError->{$campo . 'CustomMessage'} = $errore;
        }
        return count($errori) === 0;
    }
}
