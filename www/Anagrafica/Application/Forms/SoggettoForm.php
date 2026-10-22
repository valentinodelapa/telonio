<?php

namespace Anagrafica\Application\Forms;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\ComuneModel;
use Anagrafica\Application\Models\NazioneModel;
use Anagrafica\Application\Models\SoggettoModel;
use CommSuite\Application\HelperClasses\CodiceFiscale;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Dati identificativi del soggetto. Studio e tipo soggetto vengono
 * impostati dal controller sull'entità e non sono modificabili dal form.
 */
class SoggettoForm extends BaseForm
{

    private bool $codiceFiscaleCalcolato = false;

    #[\Override]
    protected static function getEntityName(): string
    {
        return Soggetto::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        $this->normalizzaTesto('codice', false);
        $this->normalizzaTesto('codiceFiscale', true);
        if ($this->entity->isPersonaFisica()) {
            $this->normalizzaTesto('cognome', false);
            $this->normalizzaTesto('nome', false);
            $this->addRequest('nominativo', mb_strtoupper(trim(($this->request->input['cognome'] ?? '') . ' ' . ($this->request->input['nome'] ?? ''))));
            $this->allineaComuneNascita();
            if (($this->request->input['codiceFiscale'] ?? '') === '') {
                $this->calcolaCodiceFiscale();
            }
        }
    }

    /**
     * L'utente sceglie il comune per nome; il soggetto viene collegato alla
     * versione dello stesso comune (codice catastale) in vigore alla data di
     * nascita. Se a quella data il comune non esisteva la scelta resta
     * invariata e la verifica in customFilter segnala l'errore.
     */
    private function allineaComuneNascita(): void
    {
        $input = $this->request->input;
        $dataNascita = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($input['dataNascita'] ?? ''));
        if (empty($input['comuneNascita']) || ($dataNascita === false)) {
            return;
        }
        $comuneModel = new ComuneModel();
        $comuneScelto = $comuneModel->getEntityById((int) $input['comuneNascita']);
        $comuneAllaNascita = $comuneScelto === null ? null : $comuneModel->getByCodiceAl($comuneScelto->codiceCatastale, $dataNascita);
        if ($comuneAllaNascita !== null) {
            $this->addRequest('comuneNascita', $comuneAllaNascita->id);
        }
    }

    private function normalizzaTesto(string $campo, bool $maiuscolo): void
    {
        if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
            $valore = trim(preg_replace('/\s+/', ' ', $this->request->input[$campo]));
            $this->addRequest($campo, $maiuscolo ? strtoupper(str_replace(' ', '', $valore)) : $valore);
        }
    }

    private function calcolaCodiceFiscale(): void
    {
        $input = $this->request->input;
        foreach (['cognome', 'nome', 'sesso', 'dataNascita'] as $campo) {
            if (empty($input[$campo])) {
                return;
            }
        }
        $codiceCatastale = null;
        if (empty($input['comuneNascita']) === false) {
            $codiceCatastale = (new ComuneModel())->getEntityById((int) $input['comuneNascita'])?->codiceCatastale;
        } elseif (empty($input['nazioneNascita']) === false) {
            $codiceCatastale = (new NazioneModel())->getEntityById((int) $input['nazioneNascita'])?->codiceCatastale;
        }
        $dataNascita = \DateTimeImmutable::createFromFormat('!Y-m-d', $input['dataNascita']);
        if (($codiceCatastale !== null) && ($dataNascita !== false)) {
            $this->addRequest('codiceFiscale', CodiceFiscale::calcola($input['cognome'], $input['nome'], $input['sesso'], $dataNascita, $codiceCatastale));
            $this->codiceFiscaleCalcolato = true;
        }
    }

    public function isCodiceFiscaleCalcolato(): bool
    {
        return $this->codiceFiscaleCalcolato;
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('codice', FilterType::isMaxLimitString, [20], true)
                ->addFilterFieldMode('codiceFiscale', FilterType::isString)
                ->addFilterFieldMode('clienteStudio', FilterType::isBoolean)
                ->addFilterFieldMode('dataCessazione', FilterType::isDate, [], true)
                ->addFilterFieldMode('note', FilterType::isString, [], true);
        if ($this->entity->isPersonaFisica()) {
            $this->addFilterFieldMode('nominativo', FilterType::isString)
                    ->addFilterFieldMode('cognome', FilterType::isMaxLimitString, [100])
                    ->addFilterFieldMode('nome', FilterType::isMaxLimitString, [100])
                    ->addFilterFieldMode('sesso', FilterType::isEnumeration)
                    ->addFilterFieldMode('dataNascita', FilterType::isDate)
                    ->addFilterFieldMode('comuneNascita', FilterType::isEntity, [], true)
                    ->addFilterFieldMode('nazioneNascita', FilterType::isEntity, [], true)
                    ->addFilterFieldMode('cittadinanza', FilterType::isEntity, [], true)
                    ->addFilterFieldMode('statoCivile', FilterType::isEnumeration, [], true)
                    ->addFilterFieldMode('dataDecesso', FilterType::isDate, [], true);
        } else {
            $this->addFilterFieldMode('dataCostituzione', FilterType::isDate, [], true);
        }
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $codiceFiscale = $this->entityData->codiceFiscale;
        if ($codiceFiscale === null) {
            return false;
        }
        $valido = $this->entity->isPersonaFisica() ? $this->verificaPersonaFisica($codiceFiscale) : $this->verificaCodiceFiscaleNumerico($codiceFiscale);
        if ($valido && (new SoggettoModel())->esisteCodiceFiscale($this->entity->studio, $codiceFiscale, $this->entity)) {
            $this->formFilterError->codiceFiscaleCustomMessage = 'Esiste già un soggetto con questo codice fiscale.';
            $valido = false;
        }
        $dataInizio = $this->entity->isPersonaFisica() ? $this->entityData->dataNascita : $this->entityData->dataCostituzione;
        if (($this->entityData->dataCessazione !== null) && ($dataInizio !== null) && ($this->entityData->dataCessazione < $dataInizio)) {
            $this->formFilterError->dataCessazioneCustomMessage = 'La data di cessazione non può precedere la data di inizio.';
            $valido = false;
        }
        if ($this->entity->isPersonaFisica() && ($this->entityData->dataDecesso !== null)) {
            if (($dataInizio !== null) && ($this->entityData->dataDecesso < $dataInizio)) {
                $this->formFilterError->dataDecessoCustomMessage = 'La data di decesso non può precedere la data di nascita.';
                $valido = false;
            } elseif ($this->entityData->dataDecesso->format('Y-m-d') > date('Y-m-d')) {
                $this->formFilterError->dataDecessoCustomMessage = 'La data di decesso non può essere futura.';
                $valido = false;
            }
        }
        return $valido;
    }

    private function verificaCodiceFiscaleNumerico(string $codiceFiscale): bool
    {
        if (CodiceFiscale::isValidoNumerico($codiceFiscale) === false) {
            $this->formFilterError->codiceFiscaleCustomMessage = 'Il codice fiscale di società ed enti è composto da 11 cifre: quello indicato non è valido.';
            return false;
        }
        return true;
    }

    private function verificaPersonaFisica(string $codiceFiscale): bool
    {
        if (CodiceFiscale::isValidoPersonaFisica($codiceFiscale) === false) {
            $this->formFilterError->codiceFiscaleCustomMessage = 'Codice fiscale non valido (formato o carattere di controllo errato).';
            return false;
        }
        $valido = true;
        if (($this->entityData->comuneNascita === null) && ($this->entityData->nazioneNascita === null)) {
            $this->formFilterError->comuneNascitaCustomMessage = 'Indicare il comune di nascita o lo stato estero di nascita.';
            $valido = false;
        }
        if (($this->entityData->sesso !== null) && (CodiceFiscale::estraiSesso($codiceFiscale) !== $this->entityData->sesso->value)) {
            $this->formFilterError->codiceFiscaleCustomMessage = 'Il codice fiscale non corrisponde al sesso indicato.';
            $valido = false;
        }
        if (($this->entityData->dataNascita !== null) && (CodiceFiscale::estraiDataNascita($codiceFiscale)->format('ymd') !== $this->entityData->dataNascita->format('ymd'))) {
            $this->formFilterError->codiceFiscaleCustomMessage = 'Il codice fiscale non corrisponde alla data di nascita indicata.';
            $valido = false;
        }
        $luogoNascita = $this->entityData->comuneNascita ?? $this->entityData->nazioneNascita;
        if (($luogoNascita !== null) && ($this->entityData->dataNascita !== null) && ($luogoNascita->isVigenteAl($this->entityData->dataNascita) === false)) {
            $campo = $this->entityData->comuneNascita !== null ? 'comuneNascita' : 'nazioneNascita';
            $this->formFilterError->{$campo . 'CustomMessage'} = $luogoNascita->denominazione . ' non risulta esistente alla data di nascita.';
            $valido = false;
        }
        if (($luogoNascita !== null) && ($luogoNascita->codiceCatastale !== null) && (CodiceFiscale::estraiCodiceCatastale($codiceFiscale) !== $luogoNascita->codiceCatastale)) {
            $this->formFilterError->codiceFiscaleCustomMessage = 'Il codice fiscale non corrisponde al luogo di nascita indicato.';
            $valido = false;
        }
        return $valido;
    }
}
