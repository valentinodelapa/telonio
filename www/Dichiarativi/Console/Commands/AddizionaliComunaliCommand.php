<?php

namespace Dichiarativi\Console\Commands;

use Dichiarativi\Application\Services\TabelleRiferimento\AddizionaliComunali;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Riscrive la sorgente delle aliquote dell'addizionale comunale dall'elenco
 * ufficiale pubblicato dall'Agenzia delle Entrate.
 *
 * Uso:
 *   sisma addizionali-comunali
 *   sisma addizionali-comunali --anno=2025
 */
class AddizionaliComunaliCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'addizionali-comunali';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma addizionali-comunali [--anno=AAAA]

Scarica l'elenco delle aliquote dell'addizionale comunale all'IRPEF
indicato in Dichiarativi/Database/sorgenti/addizionali.csv, ne legge
aliquote, scaglioni, soglie di esenzione e casi particolari per codice
catastale e riscrive addizionali-comunali-<anno>.csv. Richiede pdftotext
(poppler-utils). Per importare le aliquote nel database eseguire poi
"sisma tabelle-dichiarativi".

Options:
  --anno=AAAA   solo l'anno d'imposta indicato
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $anno = $this->getOption('anno');
            foreach ((new AddizionaliComunali())->aggiornaAnni($anno === null ? null : (int) $anno) as $annoImposta => $comuni) {
                $this->output(sprintf('  %d: %d comuni', $annoImposta, $comuni));
            }
            $this->output('Sorgenti aggiornate: importarle con "sisma tabelle-dichiarativi".');
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }
}
