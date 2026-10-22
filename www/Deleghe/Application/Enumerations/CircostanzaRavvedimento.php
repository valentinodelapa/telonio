<?php

namespace Deleghe\Application\Enumerations;

/**
 * Momento della regolarizzazione rispetto ai controlli (art. 13, c. 1,
 * D.Lgs. 472/1997): le lettere b-ter, b-quater e b-quinquies fissano la
 * riduzione indipendentemente dal tempo trascorso.
 *
 * Per le violazioni fino al 31/08/2024 vale solo la riduzione a 1/5 dopo il
 * verbale di constatazione (lett. b-quater del testo previgente); lo schema
 * d'atto (art. 6-bis L. 212/2000) è stato introdotto con il D.Lgs. 87/2024.
 */
enum CircostanzaRavvedimento: string
{

    case nessuna = 'N';
    case schemaAtto = 'S';
    case verbale = 'V';
    case schemaAttoDopoVerbale = 'D';

    public function getLabel(): string
    {
        return match ($this) {
            self::nessuna => 'Prima di controlli e contestazioni',
            self::schemaAtto => 'Dopo lo schema d\'atto (senza verbale di constatazione) - 1/6',
            self::verbale => 'Dopo il verbale di constatazione, prima dello schema d\'atto - 1/5',
            self::schemaAttoDopoVerbale => 'Dopo lo schema d\'atto seguito al verbale - 1/4',
        };
    }

    /**
     * Denominatore della riduzione; null se la riduzione dipende solo dal
     * tempo trascorso.
     */
    public function getRiduzione(bool $riforma): ?int
    {
        return match ($this) {
            self::nessuna => null,
            self::schemaAtto => $riforma ? 6 : throw new \InvalidArgumentException('Lo schema d\'atto riguarda le violazioni commesse dal 1° settembre 2024.'),
            self::verbale => 5,
            self::schemaAttoDopoVerbale => $riforma ? 4 : throw new \InvalidArgumentException('Lo schema d\'atto riguarda le violazioni commesse dal 1° settembre 2024.'),
        };
    }
}
