<?php

namespace CommSuite\Application\Forms;

use CommSuite\Application\Entities\Utente;
use CommSuite\Application\Enumerations\RuoloUtente;
use CommSuite\Application\Models\UtenteModel;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Dati di un operatore dello studio gestiti dall'amministratore dello studio.
 */
class UtenteForm extends BaseForm
{

    public function __construct(
        ?BaseEntity $baseEntity = null,
        private ?Utente $utenteCorrente = null,
        DataMapper $dataMapper = new DataMapper(),
    ) {
        parent::__construct($baseEntity, $dataMapper);
    }

    #[\Override]
    protected static function getEntityName(): string
    {
        return Utente::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        if (isset($this->request->input['username']) && is_string($this->request->input['username'])) {
            $this->addRequest('username', strtolower(trim($this->request->input['username'])));
        }
        foreach (['cognome', 'nome'] as $campo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $this->addRequest($campo, trim(preg_replace('/\s+/', ' ', $this->request->input[$campo])));
            }
        }
        if (isset($this->request->input['email']) && is_string($this->request->input['email'])) {
            $this->addRequest('email', strtolower(trim($this->request->input['email'])));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('username', FilterType::isMaxLimitString, [50])
                ->addFilterFieldMode('cognome', FilterType::isMaxLimitString, [100])
                ->addFilterFieldMode('nome', FilterType::isMaxLimitString, [100])
                ->addFilterFieldMode('email', FilterType::isMaxLimitString, [255])
                ->addFilterFieldMode('ruolo', FilterType::isEnumeration)
                ->addFilterFieldMode('attivo', FilterType::isBoolean);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $utenteModel = new UtenteModel();
        $username = $this->entityData->username;
        if ($username !== null) {
            if (preg_match('/^[a-z0-9._-]{3,50}$/', $username) !== 1) {
                $this->formFilterError->usernameCustomMessage = 'Il nome utente deve contenere da 3 a 50 caratteri (lettere minuscole, cifre, punto, trattino o trattino basso).';
                $valido = false;
            } elseif ($utenteModel->esisteUsername($username, $this->entity)) {
                $this->formFilterError->usernameCustomMessage = 'Questo nome utente è già utilizzato.';
                $valido = false;
            }
        }
        if (($this->entityData->email !== null) && (filter_var($this->entityData->email, FILTER_VALIDATE_EMAIL) === false)) {
            $this->formFilterError->emailCustomMessage = 'Indirizzo e-mail non valido.';
            $valido = false;
        }
        if (isset($this->entity->id)) {
            $stessoUtente = ($this->utenteCorrente !== null) && ($this->entity->id === $this->utenteCorrente->id);
            if ($stessoUtente && ($this->entityData->attivo === false)) {
                $this->formFilterError->attivoCustomMessage = 'Non puoi disattivare il tuo stesso utente.';
                $valido = false;
            }
            if ($stessoUtente && ($this->entityData->ruolo !== RuoloUtente::amministratore)) {
                $this->formFilterError->ruoloCustomMessage = 'Non puoi revocare il ruolo di amministratore al tuo stesso utente.';
                $valido = false;
            }
            $restaAmministratoreAttivo = ($this->entityData->ruolo === RuoloUtente::amministratore) && ($this->entityData->attivo === true);
            // sul proprio utente vale già il messaggio specifico, che non va sovrascritto
            if (($stessoUtente === false) && ($restaAmministratoreAttivo === false) && ($utenteModel->contaAmministratoriAttivi($this->entity->studio, $this->entity) === 0)) {
                $this->formFilterError->ruoloCustomMessage = 'Deve rimanere almeno un amministratore attivo nello studio.';
                $valido = false;
            }
        }
        return $valido;
    }
}
