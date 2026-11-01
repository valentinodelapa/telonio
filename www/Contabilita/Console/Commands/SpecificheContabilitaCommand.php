<?php

namespace Contabilita\Console\Commands;

use Contabilita\Application\Services\TabelleRiferimento\SpecificheContabilita;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Aggiorna gli schemi XSD ufficiali del modulo (comunicazione delle
 * liquidazioni periodiche IVA e fattura elettronica).
 *
 * Uso:
 *   sisma specifiche-contabilita
 *   sisma specifiche-contabilita --fatturapa=URL   schema della fattura di una nuova versione
 */
class SpecificheContabilitaCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'specifiche-contabilita';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma specifiche-contabilita [--fatturapa=URL]

Scarica e installa in Contabilita/Application/Assets/xsd gli schemi ufficiali:
  - comunicazione delle liquidazioni periodiche IVA (fornitura IVP18),
    dalla pagina delle specifiche tecniche dell'Agenzia delle Entrate;
  - fattura elettronica (schema VFPR12);
  - firme XML (W3C xmldsig), importato da entrambi.
I riferimenti tra gli schemi sono resi locali e ogni schema è verificato.

Options:
  --fatturapa=URL   schema VFPR12 di una nuova versione delle specifiche
                    (di default quello della versione 1.9.1)
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            foreach ((new SpecificheContabilita())->aggiorna($this->getOption('fatturapa')) as $file) {
                $this->output('  ' . $file);
            }
            $this->output('Schemi aggiornati e verificati.');
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }
}
