<?php

namespace Dichiarativi\Console\Commands;

use Dichiarativi\Application\Services\Stampa\ModelliUfficialiDichiarativi;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Aggiorna sfondi e posizioni dei campi della stampa delle dichiarazioni
 * sul modello ufficiale.
 *
 * Uso:
 *   sisma modelli-dichiarativi
 *   sisma modelli-dichiarativi --modello=RPF --anno=2026 [--pdf=CARTELLA] [--dettaglio]
 */
class ModelliDichiarativiCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'modelli-dichiarativi';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma modelli-dichiarativi [--modello=RPF] [--anno=AAAA] [--pdf=CARTELLA] [--dettaglio]

Scarica i fascicoli dei modelli in PDF elencati in
Dichiarativi/Database/sorgenti/modelli.csv, verifica il numero di pagine, li
riscrive in Dichiarativi/Application/Assets/modelli (sfondi della stampa
sul modello ufficiale) e ne legge le posizioni dei campi del catalogo, con
le correzioni di sorgenti/posizioni-<modello>-<anno>.csv, in
Assets/modelli/<modello>-<anno>.csv. Richiede poppler-utils e qpdf.

Options:
  --modello=SIGLA   solo il modello indicato
  --anno=AAAA       solo l'anno del modello indicato
  --pdf=CARTELLA    usa i fascicoli già scaricati (<modello>-<anno>-<fascicolo>.pdf)
  --dettaglio       elenca i campi senza posizione e le caselle non attribuite
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $anno = $this->getOption('anno');
            $esiti = (new ModelliUfficialiDichiarativi())->aggiorna($this->getOption('modello'), $anno === null ? null : (int) $anno, $this->getOption('pdf'), fn(string $messaggio) => $this->output($messaggio));
            foreach ($esiti as $modello => $esito) {
                $this->output(sprintf('  %s: %d posizioni, %d campi del catalogo collocati, %d senza posizione (nella stampa in un elenco a parte)', $modello, $esito['posizioni'], $esito['campi'], count($esito['mancanti'])));
                if ($this->getOption('dettaglio') !== null) {
                    $this->output('    senza posizione: ' . implode(' ', $esito['mancanti']));
                    foreach ($esito['scarti'] as $scarto) {
                        $this->output('    ' . $scarto);
                    }
                }
            }
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }
}
