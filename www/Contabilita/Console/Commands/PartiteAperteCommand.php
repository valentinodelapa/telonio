<?php

namespace Contabilita\Console\Commands;

use Contabilita\Application\Services\MigrazionePartite;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Crea le partite aperte e gli abbinamenti delle registrazioni che non li
 * hanno. All'aggiornamento alla versione 0.8.1 della contabilità lo
 * esegue già "sisma aggiorna-database" (Database/aggiornamento-0.8.1.php);
 * il comando resta per ripeterlo.
 *
 * Uso:
 *   sisma partite-aperte                  tutte le aziende
 *   sisma partite-aperte --soggetto=ID    la sola azienda indicata
 */
class PartiteAperteCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'partite-aperte';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma partite-aperte [--soggetto=ID]

Crea le partite del partitario clienti e fornitori per le registrazioni
che non le hanno ancora: in ordine di giornale le fatture aprono le
partite, incassi, pagamenti e note di credito le chiudono dalla più
vecchia e l'eccedenza diventa un acconto. Gli abbinamenti creati sono da
verificare nello scadenzario dell'azienda. Le registrazioni che hanno già
partite o abbinamenti non sono toccate: il comando si può ripetere.

Options:
  --soggetto=ID     solo l'azienda indicata
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $filtro = $this->getOption('soggetto');
            (new MigrazionePartite())->eseguiTutte(fn(string $messaggio) => $this->output('  ' . $messaggio), $filtro === null ? null : (int) $filtro);
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }
}
