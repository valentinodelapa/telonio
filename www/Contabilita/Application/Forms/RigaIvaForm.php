<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\RigaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Deleghe\Application\HelperClasses\Importo;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Riga IVA della registrazione con gli importi come nel documento
 * (positivi anche nelle note di credito: il segno è applicato dal
 * controller secondo la causale).
 */
class RigaIvaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return RigaIva::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['imponibile', 'imposta', 'detraibilita', 'aliquotaTeorica'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, Importo::normalizza($this->request->input[$campo]));
            }
        }
        if (($this->request->input['detraibilita'] ?? '') === '') {
            $this->addRequest('detraibilita', '100');
        }
        if (($this->request->input['imposta'] ?? '') === '') {
            $this->addRequest('imposta', '0');
        }
        // rivendita: '1' beni delle categorie ammesse alla ventilazione, '2' altri prodotti
        $rivendita = (string) ($this->request->input['rivendita'] ?? '');
        $this->addRequest('rivendita', in_array($rivendita, ['1', '2'], true) ? '1' : '0');
        $this->addRequest('altriProdotti', $rivendita === '2' ? '1' : '0');
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codiceIva', FilterType::isEntity)
                ->addFilterFieldMode('imponibile', FilterType::isNumeric)
                ->addFilterFieldMode('imposta', FilterType::isNumeric)
                ->addFilterFieldMode('detraibilita', FilterType::isNumeric)
                ->addFilterFieldMode('rivendita', FilterType::isBoolean)
                ->addFilterFieldMode('altriProdotti', FilterType::isBoolean)
                ->addFilterFieldMode('naturaInversione', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('qualifica', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('aliquotaTeorica', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('conto', FilterType::isEntity, [], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $natura = $this->entityData->naturaInversione;
        if (($natura !== null) && (($natura->isInversioneContabile() === false) || ($this->entityData->codiceIva?->inversioneContabile !== true))) {
            $this->formFilterError->naturaInversioneCustomMessage = 'La tipologia N6 si indica solo per le integrazioni in inversione contabile.';
            return false;
        }
        $qualifica = $this->entityData->qualifica;
        if (($qualifica !== null) && ($qualifica->isAmmessa($this->entityData->codiceIva?->natura) === false)) {
            $this->formFilterError->qualificaCustomMessage = 'Qualifica non compatibile con il codice IVA (esenti: N4; fuori campo: N2.1).';
            return false;
        }
        $aliquota = $this->entityData->aliquotaTeorica;
        if (($aliquota !== null) && ((($aliquota <= 0) || ($aliquota >= 100)) || ($this->entityData->codiceIva?->natura !== NaturaIva::dichiarazioniIntento))) {
            $this->formFilterError->aliquotaTeoricaCustomMessage = 'L\'aliquota teorica si indica solo per gli acquisti con dichiarazione d\'intento (N3.5).';
            return false;
        }
        return true;
    }

    /**
     * La riga è vuota (non inviata o senza importi né codice).
     */
    public static function isVuota(array $input): bool
    {
        return (trim((string) ($input['imponibile'] ?? '')) === '') && (trim((string) ($input['imposta'] ?? '')) === '');
    }
}
