<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\Versamento;

/**
 * Esito di ArchivioVersamenti::registra: versamenti registrati e differenze
 * rispetto ai versamenti già in deleghe non più modificabili (da gestire a
 * mano, es. con un versamento integrativo o riportando la delega in bozza).
 */
class EsitoRegistrazione
{

    /** @var Versamento[] */
    public array $versamenti = [];

    /** @var string[] */
    public array $differenze = [];
}
