<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\DatiFiscaliModel;
use CommSuite\Application\HelperClasses\PartitaIva;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Versione dei dati fiscali. Il soggetto viene impostato dal controller.
 */
class DatiFiscaliForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return DatiFiscali::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['partitaIva']) && is_string($this->request->input['partitaIva']) && ($this->request->input['partitaIva'] !== '')) {
            $this->addRequest('partitaIva', PartitaIva::normalizza($this->request->input['partitaIva']));
        }
        foreach (['denominazione', 'descrizioneAttivita'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim(preg_replace('/\s+/', ' ', $this->request->input[$campo])));
            }
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $personaFisica = $this->entity->soggetto->isPersonaFisica();
        $this->addFilterFieldMode('validoDal', FilterType::isDate)
                ->addFilterFieldMode('validoAl', FilterType::isDate, [], true)
                ->addFilterFieldMode('denominazione', FilterType::isMaxLimitString, [255], $personaFisica)
                ->addFilterFieldMode('partitaIva', FilterType::isString, [], true)
                ->addFilterFieldMode('codiceAteco', FilterType::isEntity, [], true)
                ->addFilterFieldMode('descrizioneAttivita', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('regimeFiscale', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('periodicitaIva', FilterType::isEnumeration, [], true)
                ->addFilterFieldMode('soggettoIsa', FilterType::isBoolean)
                ->addFilterFieldMode('sostitutoImposta', FilterType::isBoolean);
        if ($this->entity->soggetto->tipoSoggetto === TipoSoggetto::societaCapitali) {
            $this->addFilterFieldMode('trasparenzaFiscale', FilterType::isBoolean);
        }
        if ($personaFisica) {
            $this->addFilterFieldMode('tipoAttivita', FilterType::isEnumeration, [], true);
        } else {
            $this->addFilterFieldMode('naturaGiuridica', FilterType::isEnumeration)
                    ->addFilterFieldMode('enteNonCommerciale', FilterType::isBoolean);
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
        if (($this->entityData->partitaIva !== null) && (PartitaIva::isValida($this->entityData->partitaIva) === false)) {
            $this->formFilterError->partitaIvaCustomMessage = 'Partita IVA non valida (11 cifre con carattere di controllo).';
            $valido = false;
        } elseif (($this->entityData->partitaIva !== null) && $this->isPartitaIvaDiAltroSoggetto($this->entityData->partitaIva)) {
            $this->formFilterError->partitaIvaCustomMessage = 'Partita IVA già attribuita a un altro soggetto dello studio.';
            $valido = false;
        }
        $naturaGiuridica = $this->entityData->naturaGiuridica;
        if (($naturaGiuridica !== null) && ($naturaGiuridica->getTipoSoggetto() !== $this->entity->soggetto->tipoSoggetto)) {
            $this->formFilterError->naturaGiuridicaCustomMessage = 'La natura giuridica non è compatibile con il tipo di soggetto.';
            $valido = false;
        }
        if ($this->entity->soggetto->isPersonaFisica()) {
            $valido = $this->verificaAttivitaPersonaFisica() && $valido;
        }
        $valido = $this->verificaRegime() && $valido;
        return $this->verificaCodiceAteco() && $valido;
    }

    /**
     * Con la partita IVA si indica il regime ammesso per il soggetto e, se il
     * regime prevede le liquidazioni dell'IVA, la periodicità; senza partita
     * IVA regime, periodicità, ISA e trasparenza non sono previsti.
     */
    private function verificaRegime(): bool
    {
        $dati = $this->entityData;
        $regime = $dati->regimeFiscale;
        if ($dati->partitaIva === null) {
            $valido = true;
            foreach (['regimeFiscale' => $regime !== null, 'periodicitaIva' => $dati->periodicitaIva !== null, 'soggettoIsa' => (bool) $dati->soggettoIsa, 'trasparenzaFiscale' => (bool) ($dati->trasparenzaFiscale ?? false)] as $campo => $indicato) {
                if ($indicato) {
                    $this->formFilterError->{$campo . 'CustomMessage'} = 'Previsto solo con la partita IVA.';
                    $valido = false;
                }
            }
            return $valido;
        }
        $soggetto = $this->entity->soggetto;
        if ($regime === null) {
            $this->formFilterError->regimeFiscaleCustomMessage = 'Indicare il regime fiscale.';
            return false;
        }
        if ($regime->isApplicabile($soggetto->tipoSoggetto, $dati->tipoAttivita ?? null, (bool) ($dati->enteNonCommerciale ?? false)) === false) {
            $this->formFilterError->regimeFiscaleCustomMessage = 'Regime non previsto per questo soggetto.';
            return false;
        }
        if (($regime->getDecorrenzaMinima() !== null) && ($dati->validoDal !== null) && ($dati->validoDal->format('Y-m-d') < $regime->getDecorrenzaMinima())) {
            $this->formFilterError->regimeFiscaleCustomMessage = 'Regime previsto dal ' . (new \DateTimeImmutable($regime->getDecorrenzaMinima()))->format('d/m/Y') . '.';
            return false;
        }
        $valido = true;
        if ($regime->isLiquidazioneIva() === false) {
            if ($dati->periodicitaIva !== null) {
                $this->formFilterError->periodicitaIvaCustomMessage = 'Il regime non prevede le liquidazioni periodiche dell\'IVA.';
                $valido = false;
            }
            if ($dati->soggettoIsa) {
                $this->formFilterError->soggettoIsaCustomMessage = 'I contribuenti in questo regime sono esclusi dagli ISA.';
                $valido = false;
            }
        } elseif ($dati->periodicitaIva === null) {
            $this->formFilterError->periodicitaIvaCustomMessage = 'Indicare la periodicità delle liquidazioni IVA.';
            $valido = false;
        } elseif (($regime->getPeriodicitaObbligata() !== null) && ($dati->periodicitaIva !== $regime->getPeriodicitaObbligata())) {
            $this->formFilterError->periodicitaIvaCustomMessage = 'Il regime prevede la liquidazione ' . mb_strtolower($regime->getPeriodicitaObbligata()->getLabel()) . '.';
            $valido = false;
        }
        return $valido;
    }

    /**
     * Si attribuiscono solo sottocategorie, della classificazione in vigore
     * alla decorrenza della versione (ATECO 2025 dal 01/04/2025).
     */
    private function verificaCodiceAteco(): bool
    {
        $codiceAteco = $this->entityData->codiceAteco;
        if (($codiceAteco === null) || ($this->entityData->validoDal === null)) {
            return true;
        }
        $classificazione = ClassificazioneAteco::vigenteAl($this->entityData->validoDal);
        if ($codiceAteco->isSottocategoria() === false) {
            $this->formFilterError->codiceAtecoCustomMessage = 'Indicare una sottocategoria (codice a 6 cifre).';
            return false;
        } elseif ($codiceAteco->classificazione !== $classificazione) {
            $this->formFilterError->codiceAtecoCustomMessage = 'Alla decorrenza indicata è in vigore la classificazione ' . $classificazione->getLabel() . ': il codice ' . $codiceAteco->codice . ' appartiene alla ' . $codiceAteco->classificazione->getLabel() . '.';
            return false;
        }
        return true;
    }

    /**
     * La partita IVA identifica un solo soggetto: si verifica tutto lo
     * storico, perché una partita IVA cessata non viene riattribuita.
     */
    private function isPartitaIvaDiAltroSoggetto(string $partitaIva): bool
    {
        $soggetto = $this->entity->soggetto;
        foreach ((new DatiFiscaliModel())->getByPartitaIva($partitaIva) as $datiFiscali) {
            $titolare = $datiFiscali->soggetto;
            if (($titolare->studio->id === $soggetto->studio->id) && ((isset($soggetto->id) === false) || ($titolare->id !== $soggetto->id))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Partita IVA e tipo di attività vanno indicati insieme: senza attività
     * la persona fisica è un privato e non ha ditta, partita IVA né ATECO.
     */
    private function verificaAttivitaPersonaFisica(): bool
    {
        if ($this->entityData->tipoAttivita !== null) {
            if ($this->entityData->partitaIva === null) {
                $this->formFilterError->partitaIvaCustomMessage = 'Per l\'esercizio di un\'attività indicare la partita IVA.';
                return false;
            }
            return true;
        }
        $valido = true;
        if ($this->entityData->partitaIva !== null) {
            $this->formFilterError->tipoAttivitaCustomMessage = 'Indicare il tipo di attività esercitata con questa partita IVA.';
            $valido = false;
        }
        foreach (['denominazione', 'codiceAteco', 'descrizioneAttivita'] as $campo) {
            if ($this->entityData->$campo !== null) {
                $this->formFilterError->{$campo . 'CustomMessage'} = 'Previsto solo per chi esercita un\'attività.';
                $valido = false;
            }
        }
        return $valido;
    }
}
