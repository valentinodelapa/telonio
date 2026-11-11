<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\InterventoEdilizio;
use Anagrafica\Application\Enumerations\TipoDetrazioneEdilizia as Tipo;
use Anagrafica\Application\Services\TabellaDetrazioniEdilizie as Tabella;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use Telonio\Application\HelperClasses\CodiceFiscale;

/**
 * Intervento edilizio dell'immobile (impostato dal controller). Controlli
 * dei campi dei righi del quadro RP secondo le specifiche tecniche del
 * modello Redditi PF 2026 (par. 13.1.1, 14.1, 15.2-15.9 e rigo RP57).
 * Percentuale di detrazione e numero delle rate, se non indicati, sono
 * proposti dalle tabelle delle specifiche (TabellaDetrazioniEdilizie); se
 * indicati devono essere tra quelli ammessi.
 */
class InterventoEdilizioForm extends BaseForm
{

    private const SPUNTE = ['acquistoEreditaDonazione', 'maggiorazioneSisma', 'opzione2023', 'condominio'];
    private const IMPORTI = ['spesa', 'percentualeDetrazione'];
    /** Tipologie del recupero edilizio con il codice fiscale del condominio obbligatorio (col. 3) */
    private const CONDOMINIO_OBBLIGATORIO = [8, 9, 13, 18, 19, 22];

    #[\Override]
    protected static function getEntityName(): string
    {
        return InterventoEdilizio::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['codiceFiscaleCondominio']) && is_string($this->request->input['codiceFiscaleCondominio'])) {
            $this->addRequest('codiceFiscaleCondominio', mb_strtoupper(trim($this->request->input['codiceFiscaleCondominio'])));
        }
        foreach (self::IMPORTI as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $valore = trim($this->request->input[$campo]);
                $this->addRequest($campo, str_contains($valore, ',') ? str_replace(',', '.', str_replace('.', '', $valore)) : $valore);
            }
        }
        foreach (self::SPUNTE as $campo) {
            $this->addRequest($campo, ($this->request->input[$campo] ?? '') === '1' ? '1' : '0');
        }
        $this->proponiDallaTabella();
    }

    /**
     * Percentuale e rateazione dalla tabella se il campo è vuoto.
     */
    private function proponiDallaTabella(): void
    {
        $input = $this->request->input;
        $tipo = Tipo::tryFrom((string) ($input['tipo'] ?? ''));
        $anno = filter_var($input['anno'] ?? null, FILTER_VALIDATE_INT);
        if (($tipo === null) || ($anno === false)) {
            return;
        }
        $intero = fn(string $campo) => ($valore = filter_var($input[$campo] ?? null, FILTER_VALIDATE_INT)) === false ? null : $valore;
        $tipologia = $tipo->haTipologia() ? $intero('tipologia') : null;
        $codice = $tipo->haCodicePercentuale() ? $intero('codicePercentuale') : null;
        if (trim((string) ($input['percentualeDetrazione'] ?? '')) === '') {
            $percentuale = Tabella::percentuale($tipo, $anno, $tipologia, $codice);
            if ($percentuale !== null) {
                $this->addRequest('percentualeDetrazione', (string) $percentuale);
            }
        }
        if (trim((string) ($input['rateazione'] ?? '')) === '') {
            $this->addRequest('rateazione', (string) Tabella::rateazioni($tipo, $anno, $tipologia, $codice, ($input['opzione2023'] ?? '') === '1')[0]);
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('tipo', FilterType::isEnumeration)
                ->addFilterFieldMode('anno', FilterType::isInteger)
                ->addFilterFieldMode('tipologia', FilterType::isInteger, [], true)
                ->addFilterFieldMode('codiceFiscaleCondominio', FilterType::isMaxLimitString, [16], true)
                ->addFilterFieldMode('casiParticolari', FilterType::isInteger, [], true)
                ->addFilterFieldMode('acquistoEreditaDonazione', FilterType::isBoolean)
                ->addFilterFieldMode('maggiorazioneSisma', FilterType::isBoolean)
                ->addFilterFieldMode('codicePercentuale', FilterType::isInteger, [], true)
                ->addFilterFieldMode('opzione2023', FilterType::isBoolean)
                ->addFilterFieldMode('condominio', FilterType::isBoolean)
                ->addFilterFieldMode('spesa', FilterType::isNumeric)
                ->addFilterFieldMode('percentualeDetrazione', FilterType::isNumeric)
                ->addFilterFieldMode('rateazione', FilterType::isInteger)
                ->addFilterFieldMode('descrizione', FilterType::isMaxLimitString, [255], true);
        if (isset($this->entity->soggetto) === false) {
            $this->addFilterFieldMode('soggetto', FilterType::isEntity);
        }
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
        $soggetto = isset($this->entity->soggetto) ? $this->entity->soggetto : $dati->soggetto;
        if ($soggetto !== null) {
            if (isset($this->entity->immobile) && ($soggetto->studio->id !== $this->entity->immobile->studio->id)) {
                $errore('soggetto', 'Elemento non trovato nell\'archivio dello studio.');
            } elseif ($soggetto->isPersonaFisica() === false) {
                $errore('soggetto', 'Le detrazioni del quadro RP riguardano le persone fisiche.');
            }
        }
        $tipo = $dati->tipo;
        $anno = $dati->anno;
        if (($tipo instanceof Tipo) === false || ($anno === null)) {
            return $valido;
        }
        $minimo = $tipo === Tipo::colonnine ? 2019 : Tabella::ANNO_MINIMO;
        if (($anno < $minimo) || ($anno > (int) date('Y'))) {
            $errore('anno', 'Anno della spesa dal ' . $minimo . ' all\'anno in corso: le rate delle spese precedenti sono finite.');
            return false;
        }
        $tipologia = $tipo->haTipologia() ? $dati->tipologia : null;
        $codice = $tipo->haCodicePercentuale() ? $dati->codicePercentuale : null;
        if (($dati->tipologia !== null) && ($tipo->haTipologia() === false)) {
            $errore('tipologia', 'Il bonus mobili non ha il codice dell\'intervento.');
        } elseif (($tipologia !== null) && (in_array($tipologia, Tabella::tipologie($tipo, $anno), true) === false)) {
            $ammesse = Tabella::tipologie($tipo, $anno);
            $errore('tipologia', 'Codice non ammesso per le spese del ' . $anno . (count($ammesse) > 0 ? ' (ammessi: ' . implode(', ', $ammesse) . ').' : '.'));
        } elseif (($tipologia === null) && Tabella::tipologiaObbligatoria($tipo)) {
            $errore('tipologia', 'Indica il codice dell\'intervento.');
        }
        if (($dati->codicePercentuale !== null) && ($tipo->haCodicePercentuale() === false)) {
            $errore('codicePercentuale', 'Il bonus mobili non ha la casella "Percentuale".');
        } elseif (($codice !== null) && (in_array($codice, Tabella::codiciPercentuale($tipo, $anno, $tipologia), true) === false)) {
            $ammessi = Tabella::codiciPercentuale($tipo, $anno, $tipologia);
            $errore('codicePercentuale', count($ammessi) > 0 ? 'Codici ammessi per l\'anno e il codice dell\'intervento: ' . implode(', ', $ammessi) . '.' : 'Per l\'anno e il codice dell\'intervento la casella "Percentuale" non si compila.');
        } elseif (($codice === null) && Tabella::codicePercentualeObbligatorio($tipo, $anno, $tipologia)) {
            $errore('codicePercentuale', 'Per l\'anno e il codice dell\'intervento la casella "Percentuale" è obbligatoria.');
        }
        $this->controllaCaselle($tipo, $anno, $tipologia, $codice, $errore);
        $this->controllaImporti($tipo, $anno, $tipologia, $codice, $errore);
        return $valido;
    }

    private function controllaCaselle(Tipo $tipo, int $anno, ?int $tipologia, ?int $codice, \Closure $errore): void
    {
        $dati = $this->entityData;
        $codiceFiscale = $dati->codiceFiscaleCondominio;
        if ($codiceFiscale !== null) {
            if ($tipo !== Tipo::ristrutturazione) {
                $errore('codiceFiscaleCondominio', 'Il codice fiscale del condominio si indica per il recupero edilizio (RP41-RP47, col. 3).');
            } elseif (((strlen($codiceFiscale) === 11) && CodiceFiscale::isValidoNumerico($codiceFiscale)) === false && ((strlen($codiceFiscale) === 16) && CodiceFiscale::isValidoPersonaFisica($codiceFiscale)) === false) {
                $errore('codiceFiscaleCondominio', 'Codice fiscale non valido (11 cifre o 16 caratteri, con il carattere di controllo).');
            }
        } elseif (($tipo === Tipo::ristrutturazione) && in_array($tipologia, self::CONDOMINIO_OBBLIGATORIO, true)) {
            $errore('codiceFiscaleCondominio', 'Con il codice ' . $tipologia . ' il codice fiscale del condominio è obbligatorio.');
        }
        $casi = $dati->casiParticolari;
        if ($casi !== null) {
            $ammessi = match ($tipo) {
                Tipo::ristrutturazione => [1, 4],
                Tipo::risparmioEnergetico => array_values(array_filter([1, 2, 3], fn(int $caso) => ($caso === 1) || (($caso === 2) && ($anno !== 2025)) || (($caso === 3) && ($anno !== 2024)))),
                default => [],
            };
            if (in_array($casi, $ammessi, true) === false) {
                $errore('casiParticolari', count($ammessi) > 0 ? 'Valori ammessi: ' . implode(', ', $ammessi) . '.' : 'Casella non prevista per il tipo di detrazione.');
            }
        }
        if ($dati->acquistoEreditaDonazione && (($tipo !== Tipo::ristrutturazione) || ($anno >= 2025))) {
            $errore('acquistoEreditaDonazione', 'Riguarda il recupero edilizio delle spese degli anni precedenti (RP41-RP47, col. 5).');
        }
        if ($dati->maggiorazioneSisma) {
            $ammessa = match ($tipo) {
                Tipo::ristrutturazione => ($anno >= 2021) && in_array($tipologia, range(5, 11), true) && in_array($codice, [1, 2], true)
                        && (($anno <= 2022) || in_array($tipologia, range(5, 9), true)),
                Tipo::risparmioEnergetico => ($anno >= 2021) && (in_array($tipologia, [2, 3, 4, 5, 6, 12, 13, 14, 16, 30, 31, 32, 33], true) || (($tipologia === 7) && ($anno <= 2022)))
                        && (($tipologia >= 30) || ($anno <= 2024 ? $codice !== null : in_array($codice, [1, 2], true))),
                default => false,
            };
            if ($ammessa === false) {
                $errore('maggiorazioneSisma', 'La maggiorazione sisma riguarda il superbonus dal 2021 (tipologie da 5 a 11 del recupero edilizio, interventi trainati del risparmio energetico) con la casella "Percentuale".');
            }
        }
        if ($dati->opzione2023 && (($anno !== 2023) || ($tipo === Tipo::arredo) || ($codice === null && $tipologia < 30))) {
            $errore('opzione2023', 'L\'opzione per le 10 rate riguarda le spese del superbonus sostenute nel 2023.');
        }
        if ($dati->condominio && ($tipo !== Tipo::ristrutturazione)) {
            $errore('condominio', 'La casella "Condominio" dei dati catastali riguarda il recupero edilizio (RP51-RP53).');
        }
    }

    private function controllaImporti(Tipo $tipo, int $anno, ?int $tipologia, ?int $codice, \Closure $errore): void
    {
        $dati = $this->entityData;
        if (($dati->spesa !== null) && ($dati->spesa <= 0)) {
            $errore('spesa', 'Indica la spesa detraibile.');
        }
        $limite = Tabella::limiteSpesa($tipo, $anno, $tipologia, (bool) $dati->maggiorazioneSisma);
        if (($limite !== null) && ($dati->spesa !== null) && ($dati->spesa > $limite)) {
            $errore('spesa', 'La spesa supera il limite di ' . number_format($limite, 0, ',', '.') . ' euro per unità immobiliare.');
        }
        $percentuale = Tabella::percentuale($tipo, $anno, $tipologia, $codice);
        if ($dati->percentualeDetrazione === null) {
            $errore('percentualeDetrazione', 'Indica la percentuale di detrazione.');
        } elseif (($percentuale !== null) && (abs($dati->percentualeDetrazione - $percentuale) > 0.001)) {
            $errore('percentualeDetrazione', 'Per l\'anno, il codice e la casella "Percentuale" la detrazione è del ' . rtrim(rtrim(number_format($percentuale, 2, ',', ''), '0'), ',') . '%.');
        }
        $rateazioni = Tabella::rateazioni($tipo, $anno, $tipologia, $codice, (bool) $dati->opzione2023);
        if (($dati->rateazione !== null) && (in_array($dati->rateazione, $rateazioni, true) === false)) {
            $errore('rateazione', 'Numero di rate ammesso: ' . implode(' o ', $rateazioni) . '.');
        }
    }
}
