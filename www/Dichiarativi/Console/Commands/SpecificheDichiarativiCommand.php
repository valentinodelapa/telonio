<?php

namespace Dichiarativi\Console\Commands;

use Dichiarativi\Application\Services\TabelleRiferimento\SpecificheDichiarativi;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Riscrive le sorgenti del catalogo dei campi dei modelli dalle specifiche
 * tecniche ufficiali dell'Agenzia delle Entrate.
 *
 * Uso:
 *   sisma specifiche-dichiarativi
 *   sisma specifiche-dichiarativi --modello=IVA --anno=2026
 */
class SpecificheDichiarativiCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'specifiche-dichiarativi';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma specifiche-dichiarativi [--modello=IVA|RPF] [--anno=AAAA]

Scarica i PDF delle specifiche tecniche elencati in
Dichiarativi/Database/sorgenti/specifiche.csv, ne legge l'elenco dei campi
non posizionali (codice, formato, descrizione) e le denominazioni dei
quadri e riscrive campi-<modello>-<anno>.csv e quadri-<modello>-<anno>.csv.
Richiede pdftotext (poppler-utils). Per importare il catalogo nel database
eseguire poi "sisma tabelle-dichiarativi".

Options:
  --modello=SIGLA   solo il modello indicato (IVA, RPF)
  --anno=AAAA       solo l'anno del modello indicato
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $anno = $this->getOption('anno');
            foreach ((new SpecificheDichiarativi())->aggiorna($this->getOption('modello'), $anno === null ? null : (int) $anno) as $specifica => $esito) {
                $this->output(sprintf('  %s: %d campi, %d quadri', $specifica, $esito['campi'], $esito['quadri']));
                if (count($esito['senzaFormato']) > 0) {
                    $this->output('    senza formato nelle specifiche (non trasmessi): ' . implode(', ', $esito['senzaFormato']));
                }
            }
            $this->output('Sorgenti aggiornate: importarle con "sisma tabelle-dichiarativi".');
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }
}
