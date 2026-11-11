<?php

namespace Contabilita\Console\Commands;

use Anagrafica\Application\Models\SoggettoModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Services\MigrazionePartite;
use SismaFramework\Console\BaseClasses\BaseCommand;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Crea le partite aperte e gli abbinamenti delle registrazioni già
 * presenti (anteriori alla versione 0.8 della contabilità).
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
            $dataMapper = new DataMapper();
            $migrazione = new MigrazionePartite($dataMapper);
            $filtro = $this->getOption('soggetto');
            $totale = 0;
            foreach ((new PianoContiModel($dataMapper))->getEntityCollection() as $piano) {
                $soggetto = $piano->soggetto;
                if (($soggetto === null) || (($filtro !== null) && ((int) $filtro !== $soggetto->id))) {
                    continue;
                }
                $create = $migrazione->esegui($soggetto);
                $totale += $create;
                $this->output(sprintf('  %s: %d registrazioni', $soggetto->nominativo, $create));
            }
            foreach ($migrazione->getAvvisi() as $avviso) {
                $this->output('  Attenzione: ' . $avviso);
            }
            $this->output(sprintf('Partite create per %d registrazioni: verificare gli abbinamenti nello scadenzario.', $totale));
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }
}
