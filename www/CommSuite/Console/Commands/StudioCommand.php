<?php

namespace CommSuite\Console\Commands;

use CommSuite\Application\Entities\Studio;
use CommSuite\Application\Entities\Utente;
use CommSuite\Application\Enumerations\RuoloUtente;
use CommSuite\Application\Forms\PasswordForm;
use CommSuite\Application\HelperClasses\CodiceFiscale;
use CommSuite\Application\HelperClasses\PartitaIva;
use CommSuite\Application\Models\PasswordModel;
use CommSuite\Application\Models\StudioModel;
use CommSuite\Application\Models\UtenteModel;
use SismaFramework\Console\BaseClasses\BaseCommand;
use SismaFramework\Console\Traits\InteractiveInputTrait;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Provisioning e gestione degli studi (tenant) e dei relativi amministratori.
 *
 * Uso:
 *   sisma studio                                elenca gli studi e gli utenti
 *   sisma studio crea [opzioni]                 crea uno studio e il primo amministratore
 *   sisma studio amministratore --studio=ID|CF  aggiunge o reimposta un amministratore
 *   sisma studio attiva --studio=ID|CF          riattiva uno studio
 *   sisma studio disattiva --studio=ID|CF       disattiva uno studio
 */
class StudioCommand extends BaseCommand
{
    use InteractiveInputTrait;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'studio';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage:
  sisma studio [elenco]
  sisma studio crea [--denominazione=TESTO] [--cf=CF] [--piva=PIVA]
                    [--username=USER] [--cognome=COGNOME] [--nome=NOME]
                    [--email=EMAIL] [--password=PASSWORD]
  sisma studio amministratore --studio=ID|CF [--username=USER] [--cognome=COGNOME]
                              [--nome=NOME] [--email=EMAIL] [--password=PASSWORD]
  sisma studio attiva --studio=ID|CF
  sisma studio disattiva --studio=ID|CF

Gestisce gli studi professionali (tenant) della suite e i loro amministratori:
  - elenco (default): mostra gli studi registrati e i relativi utenti
  - crea: registra un nuovo studio e il suo primo utente amministratore
    (se le opzioni sono omesse, i dati vengono richiesti in modo interattivo)
  - amministratore: crea un nuovo amministratore per uno studio esistente, o
    reimposta password, ruolo e stato attivo se lo username appartiene già allo studio
  - attiva / disattiva: abilita o sospende l'accesso per tutti gli utenti dello studio
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $azione = $this->getArgument('0') ?? 'elenco';
            return match ($azione) {
                'elenco' => $this->elencaStudi(),
                'crea' => $this->creaStudio(),
                'amministratore' => $this->gestisciAmministratore(),
                'attiva' => $this->cambiaStatoStudio(true),
                'disattiva' => $this->cambiaStatoStudio(false),
                default => throw new \InvalidArgumentException('Azione non riconosciuta: ' . $azione),
            };
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }

    private function elencaStudi(): bool
    {
        $studi = (new StudioModel($this->dataMapper))->getElenco();
        if (count($studi) === 0) {
            $this->output('Nessuno studio registrato. Usa "sisma studio crea" per creare il primo studio.');
            return true;
        }
        $utenteModel = new UtenteModel($this->dataMapper);
        foreach ($studi as $studio) {
            $stato = $studio->attivo ? 'attivo' : 'disattivato';
            $piva = $studio->partitaIva !== null ? ', P.IVA ' . $studio->partitaIva : '';
            $this->output(sprintf('[%d] %s (CF %s%s) - %s', $studio->id, $studio->denominazione, $studio->codiceFiscale, $piva, $stato));
            foreach ($utenteModel->getElenco($studio) as $utente) {
                $statoUtente = $utente->attivo ? 'attivo' : 'disattivato';
                $this->output(sprintf('    - %s (%s <%s>) [%s, %s]', $utente->username, $utente->getNomeCompleto(), $utente->email, $utente->ruolo->value, $statoUtente));
            }
        }
        return true;
    }

    private function creaStudio(): bool
    {
        $denominazione = trim($this->leggiValore('denominazione', 'Denominazione dello studio'));
        if (($denominazione === '') || (mb_strlen($denominazione) > 255)) {
            throw new \InvalidArgumentException('La denominazione dello studio è obbligatoria (max 255 caratteri).');
        }
        $codiceFiscale = CodiceFiscale::normalizza($this->leggiValore('cf', 'Codice fiscale dello studio (11 cifre o 16 caratteri)'));
        if ((CodiceFiscale::isValidoNumerico($codiceFiscale) === false) && (CodiceFiscale::isValidoPersonaFisica($codiceFiscale) === false)) {
            throw new \InvalidArgumentException('Codice fiscale dello studio non valido.');
        }
        $studioModel = new StudioModel($this->dataMapper);
        if ($studioModel->esisteCodiceFiscale($codiceFiscale)) {
            throw new \InvalidArgumentException('Esiste già uno studio con codice fiscale ' . $codiceFiscale . '.');
        }
        $pivaInput = $this->getOption('piva') ?? ($this->haOpzioniUtente() ? '' : $this->ask('Partita IVA dello studio (opzionale)', ''));
        $partitaIva = PartitaIva::normalizza((string) $pivaInput);
        if (($partitaIva !== '') && (PartitaIva::isValida($partitaIva) === false)) {
            throw new \InvalidArgumentException('Partita IVA non valida.');
        }

        [$username, $cognome, $nome, $email, $password] = $this->leggiDatiUtente(true);

        $primaTransazione = $this->dataMapper->startTransaction();
        $studio = new Studio($this->dataMapper);
        $studio->denominazione = $denominazione;
        $studio->codiceFiscale = $codiceFiscale;
        $studio->partitaIva = $partitaIva === '' ? null : $partitaIva;
        $studio->attivo = true;
        $this->dataMapper->save($studio);

        $utente = new Utente($this->dataMapper);
        $utente->studio = $studio;
        $utente->username = $username;
        $utente->cognome = $cognome;
        $utente->nome = $nome;
        $utente->email = $email;
        $utente->ruolo = RuoloUtente::amministratore;
        $utente->attivo = true;
        $this->dataMapper->save($utente);

        (new PasswordModel($this->dataMapper))->salvaPassword($utente, $password);
        $this->dataMapper->commitTransaction($primaTransazione);

        $this->output(sprintf('Studio "%s" (ID %d) creato con amministratore "%s".', $studio->denominazione, $studio->id, $utente->username));
        return true;
    }

    private function gestisciAmministratore(): bool
    {
        $studio = $this->trovaStudioRichiesto();
        $username = strtolower(trim($this->leggiValore('username', 'Username dell\'amministratore')));
        if (preg_match('/^[a-z0-9._-]{3,50}$/', $username) !== 1) {
            throw new \InvalidArgumentException('Username non valido (3-50 caratteri: lettere minuscole, cifre, punto, trattino o trattino basso).');
        }
        $utenteModel = new UtenteModel($this->dataMapper);
        $esistenti = $utenteModel->getByUsername($username);
        $utente = $esistenti[0] ?? null;
        if (($utente !== null) && ($utente->studio->id !== $studio->id)) {
            throw new \InvalidArgumentException('Lo username "' . $username . '" appartiene già a un altro studio.');
        }

        if ($utente === null) {
            $cognome = trim($this->leggiValore('cognome', 'Cognome'));
            $nome = trim($this->leggiValore('nome', 'Nome'));
            $email = strtolower(trim($this->leggiValore('email', 'Indirizzo e-mail')));
            $this->validaDatiAnagraficiUtente($cognome, $nome, $email);
            $password = $this->leggiPassword();

            $primaTransazione = $this->dataMapper->startTransaction();
            $utente = new Utente($this->dataMapper);
            $utente->studio = $studio;
            $utente->username = $username;
            $utente->cognome = $cognome;
            $utente->nome = $nome;
            $utente->email = $email;
            $utente->ruolo = RuoloUtente::amministratore;
            $utente->attivo = true;
            $this->dataMapper->save($utente);
            (new PasswordModel($this->dataMapper))->salvaPassword($utente, $password);
            $this->dataMapper->commitTransaction($primaTransazione);

            $this->output(sprintf('Amministratore "%s" creato per lo studio "%s".', $utente->username, $studio->denominazione));
            return true;
        }

        $password = $this->leggiPassword();
        $primaTransazione = $this->dataMapper->startTransaction();
        $utente->ruolo = RuoloUtente::amministratore;
        $utente->attivo = true;
        $this->dataMapper->save($utente);
        (new PasswordModel($this->dataMapper))->salvaPassword($utente, $password);
        $this->dataMapper->commitTransaction($primaTransazione);

        $this->output(sprintf('Utente "%s" impostato come amministratore attivo dello studio "%s" con nuova password.', $utente->username, $studio->denominazione));
        return true;
    }

    private function cambiaStatoStudio(bool $attivo): bool
    {
        $studio = $this->trovaStudioRichiesto();
        $studio->attivo = $attivo;
        $this->dataMapper->save($studio);
        $this->output(sprintf('Studio "%s" (ID %d) %s.', $studio->denominazione, $studio->id, $attivo ? 'attivato' : 'disattivato'));
        return true;
    }

    private function trovaStudioRichiesto(): Studio
    {
        $riferimento = trim((string) ($this->getOption('studio') ?? ''));
        if ($riferimento === '') {
            throw new \InvalidArgumentException('Specificare lo studio con --studio=ID|CF.');
        }
        $studioModel = new StudioModel($this->dataMapper);
        if (ctype_digit($riferimento)) {
            $studio = $studioModel->getEntityById((int) $riferimento);
            if ($studio instanceof Studio) {
                return $studio;
            }
        }
        $perCf = $studioModel->getByCodiceFiscale(CodiceFiscale::normalizza($riferimento));
        if (isset($perCf[0])) {
            return $perCf[0];
        }
        throw new \InvalidArgumentException('Studio non trovato: ' . $riferimento);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    private function leggiDatiUtente(bool $nuovo): array
    {
        $username = strtolower(trim($this->leggiValore('username', 'Username del primo amministratore')));
        if (preg_match('/^[a-z0-9._-]{3,50}$/', $username) !== 1) {
            throw new \InvalidArgumentException('Username non valido (3-50 caratteri: lettere minuscole, cifre, punto, trattino o trattino basso).');
        }
        if ($nuovo && (new UtenteModel($this->dataMapper))->esisteUsername($username)) {
            throw new \InvalidArgumentException('Lo username "' . $username . '" è già utilizzato.');
        }
        $cognome = trim($this->leggiValore('cognome', 'Cognome dell\'amministratore'));
        $nome = trim($this->leggiValore('nome', 'Nome dell\'amministratore'));
        $email = strtolower(trim($this->leggiValore('email', 'Indirizzo e-mail dell\'amministratore')));
        $this->validaDatiAnagraficiUtente($cognome, $nome, $email);
        $password = $this->leggiPassword();
        return [$username, $cognome, $nome, $email, $password];
    }

    private function validaDatiAnagraficiUtente(string $cognome, string $nome, string $email): void
    {
        if (($cognome === '') || (mb_strlen($cognome) > 100)) {
            throw new \InvalidArgumentException('Il cognome è obbligatorio (max 100 caratteri).');
        }
        if (($nome === '') || (mb_strlen($nome) > 100)) {
            throw new \InvalidArgumentException('Il nome è obbligatorio (max 100 caratteri).');
        }
        if (($email === '') || (mb_strlen($email) > 255) || (filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new \InvalidArgumentException('Indirizzo e-mail non valido.');
        }
    }

    private function leggiPassword(): string
    {
        $password = $this->getOption('password');
        if (!is_string($password) || ($password === '')) {
            $password = $this->askSecret('Password (min. ' . PasswordForm::LUNGHEZZA_MINIMA . ' caratteri)');
        }
        if (strlen($password) < PasswordForm::LUNGHEZZA_MINIMA) {
            throw new \InvalidArgumentException('La password deve contenere almeno ' . PasswordForm::LUNGHEZZA_MINIMA . ' caratteri.');
        }
        return $password;
    }

    private function leggiValore(string $opzione, string $domanda): string
    {
        $valore = $this->getOption($opzione);
        if (is_string($valore) && ($valore !== '')) {
            return $valore;
        }
        return $this->ask($domanda);
    }

    private function haOpzioniUtente(): bool
    {
        return ($this->getOption('denominazione') !== null)
                || ($this->getOption('cf') !== null)
                || ($this->getOption('username') !== null);
    }
}
