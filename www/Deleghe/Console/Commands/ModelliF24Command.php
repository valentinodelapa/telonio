<?php

namespace Deleghe\Console\Commands;

use Deleghe\Application\Services\Stampa\ModelliUfficialiF24;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Aggiorna gli sfondi della stampa e del PDF delle deleghe con i modelli F24
 * ufficiali dell'Agenzia delle Entrate (ordinario, semplificato, elementi
 * identificativi, accise), convertiti in SVG e riscritti in PDF senza
 * object stream.
 *
 * Uso:
 *   sisma modelli-f24               scarica i modelli e scrive SVG e PDF
 *   sisma modelli-f24 --pdf=DIR     usa i PDF già scaricati
 */
class ModelliF24Command extends BaseCommand
{

    private const CARTELLA_SVG = __DIR__ . '/../../Application/Assets/svg';
    private const CARTELLA_PDF = __DIR__ . '/../../Application/Assets/pdf';

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'modelli-f24';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma modelli-f24 [--pdf=CARTELLA]

Scarica i modelli F24 in PDF dalle schede "Modello e istruzioni"
dell'Agenzia delle Entrate, verifica che l'impaginazione sia quella per cui
sono rilevate le coordinate della stampa e converte ogni pagina in SVG
(pdftocairo) in Deleghe/Application/Assets/svg; il PDF del modello,
riscritto senza object stream (qpdf), va in Deleghe/Application/Assets/pdf
come sfondo del PDF delle deleghe.

Options:
  --pdf=CARTELLA  usa i PDF già scaricati (ordinario.pdf, semplificato.pdf,
                  elide.pdf, accise.pdf)
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            $this->output('Modelli F24 dell\'Agenzia delle Entrate...');
            $sfondi = (new ModelliUfficialiF24(self::CARTELLA_SVG, self::CARTELLA_PDF))->aggiorna($this->getOption('pdf'), fn(string $messaggio) => $this->output($messaggio));
            $this->output(count($sfondi) . ' sfondi scritti in Deleghe/Application/Assets/svg e Assets/pdf');
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }
}
