<?php

namespace Telonio\Application\Forms;

use Telonio\Application\Entities\CasellaPosta;
use SismaFramework\Core\BaseClasses\BaseForm;
use SismaFramework\Core\Enumerations\FilterType;

/**
 * Configurazione di invio di una casella dello studio. La password lasciata
 * vuota non cambia quella salvata (CasellaPostaController::update); senza
 * utente l'autenticazione SMTP non si usa e la password viene tolta.
 */
class CasellaPostaForm extends BaseForm
{

    #[\Override]
    protected static function getEntityName(): string
    {
        return CasellaPosta::class;
    }

    #[\Override]
    protected function injectRequest(): void
    {
        foreach (['host' => true, 'indirizzo' => true, 'utente' => false, 'nomeMittente' => false] as $campo => $minuscolo) {
            if (isset($this->request->input[$campo]) && is_string($this->request->input[$campo])) {
                $valore = trim(preg_replace('/\s+/', ' ', $this->request->input[$campo]));
                $this->addRequest($campo, $minuscolo ? strtolower($valore) : $valore);
            }
        }
        if (isset($this->request->input['porta']) && is_string($this->request->input['porta']) && (preg_match('/^\d{1,5}$/', trim($this->request->input['porta'])) === 1)) {
            $this->addRequest('porta', (int) trim($this->request->input['porta']));
        }
    }

    #[\Override]
    protected function setFilterFieldsMode(): void
    {
        $this->addFilterFieldMode('host', FilterType::isMaxLimitString, [255])
                ->addFilterFieldMode('porta', FilterType::isInteger)
                ->addFilterFieldMode('cifratura', FilterType::isEnumeration)
                ->addFilterFieldMode('utente', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('password', FilterType::isMaxLimitString, [255], true)
                ->addFilterFieldMode('indirizzo', FilterType::isMaxLimitString, [255])
                ->addFilterFieldMode('nomeMittente', FilterType::isMaxLimitString, [255], true);
    }

    #[\Override]
    protected function setEntityFromForm(): void
    {

    }

    #[\Override]
    protected function customFilter(): bool
    {
        $valido = true;
        $host = $this->entityData->host;
        if (($host !== null) && (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $host) !== 1)) {
            $this->formFilterError->hostCustomMessage = 'Indicare il nome del server SMTP (es. smtps.pec.aruba.it), senza protocollo né porta.';
            $valido = false;
        }
        $porta = $this->entityData->porta;
        if (($porta !== null) && (($porta < 1) || ($porta > 65535))) {
            $this->formFilterError->portaCustomMessage = 'Porta non valida (di norma 465 per SSL/TLS, 587 per STARTTLS).';
            $valido = false;
        }
        $indirizzo = $this->entityData->indirizzo;
        if (($indirizzo !== null) && (filter_var($indirizzo, FILTER_VALIDATE_EMAIL) === false)) {
            $this->formFilterError->indirizzoCustomMessage = 'Indirizzo del mittente non valido.';
            $valido = false;
        }
        return $valido;
    }
}
