<?php

namespace Contabilita\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Corrispettivi di una giornata per codice IVA, comprensivi dell'imposta,
 * nella registrazione mensile dei corrispettivi: il registro riporta
 * l'ammontare globale giornaliero distinto per aliquota (art. 24 D.P.R.
 * 633/1972), mentre le righe IVA della registrazione hanno i totali del
 * mese con l'imposta scorporata (art. 27, c. 4).
 */
class CorrispettivoGiornaliero extends BaseEntity
{

    protected int $id;
    protected RegistrazioneContabile $registrazioneContabile;
    protected Soggetto $soggetto;
    protected SismaDate $data;
    protected CodiceIva $codiceIva;
    protected float $importo = 0.0;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }
}
