<?php

namespace Contabilita\Application\Forms;

use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Enumerations\LivelloConto;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use Telonio\Application\HelperClasses\CodiceFiscale;
use Telonio\Application\HelperClasses\PartitaIva;

/**
 * Cliente o fornitore dell'azienda; studio e soggetto sono impostati dal
 * controller. La partita IVA italiana e il codice fiscale si controllano
 * con i caratteri di controllo; per i soggetti esteri si indica il codice
 * identificativo del paese senza controlli.
 */
class ControparteForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return Controparte::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['denominazione', 'cognome', 'nome', 'indirizzo', 'comune', 'pec'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim($this->request->input[$campo]));
            }
        }
        foreach (['codiceFiscale', 'partitaIva', 'provincia', 'codiceDestinatario', 'cap'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, strtoupper(preg_replace('/\s+/', '', $this->request->input[$campo])));
            }
        }
        foreach (['cliente', 'fornitore', 'pubblicaAmministrazione', 'agricoltoreEsonerato'] as $campo) {
            $this->addRequest($campo, isset($this->request->input[$campo]) ? '1' : '0');
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('denominazione', FilterType::isLimitString, [2, 255])
                ->addFilterFieldMode('cognome', FilterType::isMaxLimitString, [60], true)
                ->addFilterFieldMode('nome', FilterType::isMaxLimitString, [60], true)
                ->addFilterFieldMode('codiceFiscale', FilterType::isMaxLimitString, [16], true)
                ->addFilterFieldMode('partitaIva', FilterType::isMaxLimitString, [28], true)
                ->addFilterFieldMode('nazione', FilterType::isEntity, [], true)
                ->addFilterFieldMode('indirizzo', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('cap', FilterType::isMaxLimitString, [10], true)
                ->addFilterFieldMode('comune', FilterType::isMaxLimitString, [60], true)
                ->addFilterFieldMode('provincia', FilterType::isMaxLimitString, [2], true)
                ->addFilterFieldMode('pec', FilterType::isEmail, [], true)
                ->addFilterFieldMode('codiceDestinatario', FilterType::isMaxLimitString, [7], true)
                ->addFilterFieldMode('cliente', FilterType::isBoolean)
                ->addFilterFieldMode('fornitore', FilterType::isBoolean)
                ->addFilterFieldMode('agricoltoreEsonerato', FilterType::isBoolean)
                ->addFilterFieldMode('pubblicaAmministrazione', FilterType::isBoolean)
                ->addFilterFieldMode('contoContropartita', FilterType::isEntity, [], true)
                ->addFilterFieldMode('codiceIva', FilterType::isEntity, [], true)
                ->addFilterFieldMode('note', FilterType::isMaxLimitString, [2000], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $dati = $this->entityData;
        $estera = (($dati->nazione ?? null) !== null) && ($dati->nazione->codiceIso !== 'IT');
        if (($dati->cliente === false) && ($dati->fornitore === false)) {
            $this->formFilterError->clienteCustomMessage = 'Indicare se è cliente, fornitore o entrambi.';
            $valido = false;
        }
        if (($estera === false) && (($dati->partitaIva ?? null) !== null) && (PartitaIva::isValida($dati->partitaIva) === false)) {
            $this->formFilterError->partitaIvaCustomMessage = 'Partita IVA non valida.';
            $valido = false;
        }
        $codiceFiscale = $dati->codiceFiscale ?? null;
        if (($estera === false) && ($codiceFiscale !== null) && (CodiceFiscale::isValidoPersonaFisica($codiceFiscale) === false) && (CodiceFiscale::isValidoNumerico($codiceFiscale) === false)) {
            $this->formFilterError->codiceFiscaleCustomMessage = 'Codice fiscale non valido.';
            $valido = false;
        }
        if ((($dati->provincia ?? null) !== null) && (preg_match('/^[A-Z]{2}$/', $dati->provincia) !== 1)) {
            $this->formFilterError->provinciaCustomMessage = 'Sigla di due lettere.';
            $valido = false;
        }
        if ((($dati->codiceDestinatario ?? null) !== null) && (preg_match('/^[A-Z0-9]{6,7}$/', $dati->codiceDestinatario) !== 1)) {
            $this->formFilterError->codiceDestinatarioCustomMessage = 'Codice di 7 caratteri (6 per la pubblica amministrazione).';
            $valido = false;
        }
        $contropartita = $dati->contoContropartita ?? null;
        if (($contropartita !== null) && (($contropartita->livello !== LivelloConto::sottoconto) || ($contropartita->pianoConti->soggetto?->id !== $this->entity->soggetto->id) || ($contropartita->natura->isEconomico() === false))) {
            $this->formFilterError->contoContropartitaCustomMessage = 'Scegliere un sottoconto di costo o di ricavo del piano dell\'azienda.';
            $valido = false;
        }
        $codiceIva = $dati->codiceIva ?? null;
        if (($codiceIva !== null) && ($codiceIva->studio !== null) && ($codiceIva->studio->id !== $this->entity->studio->id)) {
            $this->formFilterError->codiceIvaCustomMessage = 'Codice IVA non disponibile.';
            $valido = false;
        }
        return $valido;
    }
}
