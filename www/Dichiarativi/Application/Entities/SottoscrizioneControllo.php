<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use SismaFramework\Orm\BaseClasses\BaseEntity;

/**
 * Sottoscrizione della dichiarazione da parte dell'organo di controllo
 * contabile, in alternativa al visto di conformità (fino a cinque
 * componenti, istruzioni "Sottoscrizione organo di controllo"): codice 1
 * revisore contabile, 2 responsabile della revisione della società di
 * revisione, 3 società di revisione (senza firma), 4 membro del collegio
 * sindacale.
 */
class SottoscrizioneControllo extends BaseEntity
{

    public const MASSIMO = 5;
    public const SOGGETTI = [
        1 => 'Revisore contabile',
        2 => 'Responsabile della revisione (società di revisione)',
        3 => 'Società di revisione',
        4 => 'Membro del collegio sindacale',
    ];

    protected int $id;
    protected Dichiarazione $dichiarazione;
    protected int $ordine = 1;
    protected int $tipoSoggetto = 4;
    protected Soggetto $soggetto;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * La società di revisione (codice 3) si indica senza firma.
     */
    public function isFirmata(): bool
    {
        return $this->tipoSoggetto !== 3;
    }
}
