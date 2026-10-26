<?php

namespace GestioneStudio\Application\Services;

use GestioneStudio\Application\Entities\ProrogaAdempimento;
use GestioneStudio\Application\Entities\TermineAdempimento;
use SismaFramework\Orm\CustomTypes\SismaDate;

/**
 * Termine di un adempimento per un anno di riferimento: data effettiva,
 * termine ordinario ed eventuale proroga applicata.
 */
final class TermineEffettivo
{

    public function __construct(
            public readonly TermineAdempimento $termine,
            public readonly int $anno,
            public readonly SismaDate $data,
            public readonly SismaDate $ordinario,
            public readonly ?ProrogaAdempimento $proroga = null,
    ) {

    }

    public function isDifferito(): bool
    {
        return $this->data->format('Y-m-d') !== $this->ordinario->format('Y-m-d');
    }

    public function getPeriodo(): string
    {
        return $this->termine->periodo === 'anno' ? 'anno ' . $this->anno : $this->termine->periodo . ' ' . $this->anno;
    }

    /**
     * Riferimento stabile della scadenza del soggetto (anche se la data
     * cambia per una proroga).
     */
    public function getRiferimento(int $soggettoId): string
    {
        return 'T' . $this->termine->id . '/' . $this->anno . '/S' . $soggettoId;
    }
}
