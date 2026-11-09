<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\ExtendedClasses\ReferencedEntity;
use Telonio\Application\Entities\Studio;

/**
 * Certificazione Unica (lavoro dipendente, pensione e assimilati) ricevuta
 * dal contribuente per l'anno d'imposta: i dati sono nei punti della
 * certificazione (ValoreCertificazioneUnica, vedi
 * Services\Redditi\PuntiCertificazioneUnica) e alimentano il quadro RC, le
 * ritenute del rigo RN33 e le addizionali trattenute del quadro RV.
 *
 * Una certificazione "conguagliata" è compresa in quella del sostituto
 * che ha effettuato il conguaglio e non si riporta nella dichiarazione.
 */
class CertificazioneUnica extends ReferencedEntity
{

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $annoImposta;
    protected string $codiceFiscaleSostituto;
    protected string $denominazioneSostituto;
    protected bool $conguagliata = false;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
