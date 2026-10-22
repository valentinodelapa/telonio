<?php

namespace Deleghe\Application\Exceptions;

use Deleghe\Application\Entities\Delega;

/**
 * Un modulo ha ricalcolato versamenti già inclusi in una delega confermata,
 * trasmessa o quietanzata: gli importi non si possono più sostituire, la
 * differenza va versata con una nuova delega (eventualmente con
 * ravvedimento).
 */
class DelegaNonModificabileException extends \RuntimeException
{

    public function __construct(public readonly Delega $delega)
    {
        parent::__construct(sprintf('La delega del %s di %s è %s: i versamenti non possono essere aggiornati.',
                        $delega->scadenza->format('d/m/Y'), $delega->soggetto->nominativo, mb_strtolower($delega->stato->getLabel())));
    }
}
