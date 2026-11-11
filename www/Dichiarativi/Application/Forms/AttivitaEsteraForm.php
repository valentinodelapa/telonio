<?php

namespace Dichiarativi\Application\Forms;

use Dichiarativi\Application\Entities\AttivitaEstera;
use Dichiarativi\Application\Enumerations\CodiceInvestimentoEstero;
use Dichiarativi\Application\Services\TabelleRiferimento\StatiEsteriRedditi;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use Telonio\Application\HelperClasses\CodiceFiscale;

/**
 * Attività estera del contribuente (soggetto e anno impostati dal
 * controller), con i controlli delle specifiche tecniche del quadro RW:
 * Stato estero obbligatorio tranne per le cripto-attività, quote tra 0 e
 * 100, date nell'anno d'imposta, codici delle colonne 1, 2, 6 e 14,
 * cointestatari con codice fiscale valido.
 */
class AttivitaEsteraForm extends BaseForm
{

    private const IMPORTI = ['quota', 'valoreIniziale', 'valoreFinale', 'valoreMassimo', 'creditoImposta', 'quotaPartecipazione', 'proventi', 'impostaEstera'];
    private const MAIUSCOLE = ['codiceFiscaleEntita', 'cointestatario1', 'cointestatario2'];

    #[\Override]
    protected static function getEntityName(): string
    {
        return AttivitaEstera::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (self::IMPORTI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $valore = trim($this->request->input[$campo]);
                $this->addRequest($campo, str_contains($valore, ',') ? str_replace(',', '.', str_replace('.', '', $valore)) : $valore);
            }
        }
        foreach (self::MAIUSCOLE as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, mb_strtoupper(trim($this->request->input[$campo])));
            }
        }
        foreach (['piuCointestatari', 'soloMonitoraggio', 'tassazioneOrdinaria'] as $campo) {
            $this->addRequest($campo, ($this->request->input[$campo] ?? '') === '1' ? '1' : '0');
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codiceInvestimento', FilterType::isEnumeration)
                ->addFilterFieldMode('codiceStatoEstero', FilterType::isMaxLimitString, [3], true)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [150])
                ->addFilterFieldMode('intermediario', FilterType::isMaxLimitString, [100], true)
                ->addFilterFieldMode('titoloPossesso', FilterType::isInteger)
                ->addFilterFieldMode('tipoContribuente', FilterType::isInteger, [], true)
                ->addFilterFieldMode('quota', FilterType::isNumeric)
                ->addFilterFieldMode('criterioValore', FilterType::isInteger)
                ->addFilterFieldMode('valoreIniziale', FilterType::isNumeric)
                ->addFilterFieldMode('valoreFinale', FilterType::isNumeric)
                ->addFilterFieldMode('valoreMassimo', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('dataInizio', FilterType::isDate, [], true)
                ->addFilterFieldMode('dataFine', FilterType::isDate, [], true)
                ->addFilterFieldMode('creditoImposta', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('mesiAbitazionePrincipale', FilterType::isInteger, [], true)
                ->addFilterFieldMode('codiceQuadri', FilterType::isInteger, [], true)
                ->addFilterFieldMode('quotaPartecipazione', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('codiceFiscaleEntita', FilterType::isMaxLimitString, [25], true)
                ->addFilterFieldMode('cointestatario1', FilterType::isMaxLimitString, [16], true)
                ->addFilterFieldMode('cointestatario2', FilterType::isMaxLimitString, [16], true)
                ->addFilterFieldMode('piuCointestatari', FilterType::isBoolean)
                ->addFilterFieldMode('soloMonitoraggio', FilterType::isBoolean)
                ->addFilterFieldMode('tipoProventi', FilterType::isMaxLimitString, [1], true)
                ->addFilterFieldMode('proventi', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('impostaEstera', FilterType::isNumeric, [], true)
                ->addFilterFieldMode('tassazioneOrdinaria', FilterType::isBoolean)
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
        $dati = $this->entityData;
        $errore = function (string $campo, string $messaggio) use (&$valido): void {
            $this->formFilterError->{$campo . 'CustomMessage'} = $messaggio;
            $valido = false;
        };
        $codice = $dati->codiceInvestimento;
        if (($codice instanceof CodiceInvestimentoEstero) && ($codice !== CodiceInvestimentoEstero::criptoAttivita) && (StatiEsteriRedditi::esiste($dati->codiceStatoEstero) === false)) {
            $errore('codiceStatoEstero', 'Indica lo Stato estero (non richiesto solo per le cripto-attività).');
        } elseif (($dati->codiceStatoEstero !== null) && (StatiEsteriRedditi::esiste($dati->codiceStatoEstero) === false)) {
            $errore('codiceStatoEstero', 'Codice non presente nell\'elenco dei Paesi e territori esteri.');
        }
        foreach (['titoloPossesso' => [1, 4], 'tipoContribuente' => [1, 2], 'criterioValore' => [1, 6], 'codiceQuadri' => [1, 5], 'mesiAbitazionePrincipale' => [1, 12]] as $campo => [$minimo, $massimo]) {
            if (($dati->$campo !== null) && (($dati->$campo < $minimo) || ($dati->$campo > $massimo))) {
                $errore($campo, 'Valore da ' . $minimo . ' a ' . $massimo . '.');
            }
        }
        foreach (['quota', 'quotaPartecipazione'] as $campo) {
            if (($dati->$campo !== null) && (($dati->$campo <= 0) || ($dati->$campo > 100))) {
                $errore($campo, 'Percentuale maggiore di zero e fino a 100.');
            }
        }
        foreach (['valoreIniziale', 'valoreFinale', 'valoreMassimo', 'creditoImposta', 'proventi', 'impostaEstera'] as $campo) {
            if (($dati->$campo !== null) && ($dati->$campo < 0)) {
                $errore($campo, 'Importo non negativo.');
            }
        }
        $anno = $this->entity->annoImposta;
        foreach (['dataInizio', 'dataFine'] as $campo) {
            if (($dati->$campo !== null) && ((int) $dati->$campo->format('Y') !== $anno)) {
                $errore($campo, 'Data nell\'anno d\'imposta ' . $anno . ' (vuota se la detenzione copre l\'inizio o la fine dell\'anno).');
            }
        }
        if (($dati->dataInizio !== null) && ($dati->dataFine !== null) && ($dati->dataFine->format('Y-m-d') < $dati->dataInizio->format('Y-m-d'))) {
            $errore('dataFine', 'La fine della detenzione non precede l\'inizio.');
        }
        foreach (['cointestatario1', 'cointestatario2'] as $campo) {
            if (($dati->$campo !== null) && ($dati->$campo !== '') && (CodiceFiscale::isValidoPersonaFisica($dati->$campo) === false) && (CodiceFiscale::isValidoNumerico($dati->$campo) === false)) {
                $errore($campo, 'Codice fiscale non valido.');
            }
        }
        if (($dati->tipoProventi !== null) && ($dati->tipoProventi !== '') && (isset(AttivitaEstera::PROVENTI[$dati->tipoProventi]) === false)) {
            $errore('tipoProventi', 'Tipo di proventi non previsto.');
        }
        if ((($dati->proventi ?? 0) > 0) && (($dati->tipoProventi ?? '') === '')) {
            $errore('tipoProventi', 'Indica il tipo dei proventi.');
        }
        if ($dati->tassazioneOrdinaria && in_array($dati->tipoProventi, ['D', null, ''], true)) {
            $errore('tassazioneOrdinaria', 'L\'opzione non è ammessa per i dividendi (tipo H del rigo RM31) né senza proventi.');
        }
        if ($dati->tassazioneOrdinaria && ($dati->tipoProventi === 'I') && in_array($dati->codiceInvestimento, [CodiceInvestimentoEstero::obbligazioni, CodiceInvestimentoEstero::titoliPubbliciItaliani, CodiceInvestimentoEstero::depositoTitoli], true)) {
            $errore('tassazioneOrdinaria', 'L\'opzione non è ammessa per gli interessi delle obbligazioni (tipo A del rigo RM31).');
        }
        return $valido;
    }
}
