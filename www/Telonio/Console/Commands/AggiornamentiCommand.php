<?php

namespace Telonio\Console\Commands;

use Telonio\Application\Enumerations\CanaleAggiornamenti;
use Telonio\Application\Services\Aggiornamenti\ControlloAggiornamenti;
use Telonio\Application\Services\Aggiornamenti\Release;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Controlla su GitHub se è disponibile una versione più recente della suite
 * nel canale configurato. Con --formato=shell stampa variabili KEY=valore
 * per lo script di aggiornamento (aggiorna.sh).
 *
 * Uso:
 *   sisma aggiornamenti [--canale=alpha|beta|rc|stabile] [--formato=shell]
 */
class AggiornamentiCommand extends BaseCommand
{

    public function __construct(private ?ControlloAggiornamenti $controllo = null)
    {

    }

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'aggiornamenti';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma aggiornamenti [--canale=alpha|beta|rc|stabile] [--formato=shell]

Controlla su GitHub se è disponibile una versione più recente di quella
installata. Il canale indica la stabilità minima delle versioni proposte
(alpha riceve tutte le versioni, stabile solo le definitive); senza
--canale vale CANALE_AGGIORNAMENTI (.env) o, se assente, il canale della
versione installata. Per installare l'aggiornamento: sh aggiorna.sh

Options:
  --canale=CANALE   canale da controllare al posto di quello configurato
  --formato=shell   stampa VERSIONE_INSTALLATA, CANALE, VERSIONE, TAG,
                    ARCHIVIO, SHA256, ATTESTAZIONE e NOTE (VERSIONE vuota
                    se non ci sono aggiornamenti), per aggiorna.sh
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        try {
            if ($this->controllo === null) {
                // Errori di configurazione segnalati qui (la scrivania li ignora)
                ControlloAggiornamenti::leggiVersioneInstallata();
                ControlloAggiornamenti::leggiCanale();
            }
            $controllo = $this->controllo ?? new ControlloAggiornamenti();
            $canale = $this->getOption('canale') === null ? $controllo->getCanale() : CanaleAggiornamenti::tryFrom(strtolower($this->getOption('canale')))
                    ?? throw new \InvalidArgumentException('Canale non valido: ' . $this->getOption('canale') . ' (alpha, beta, rc o stabile)');
            $release = $controllo->verifica($canale);
            if ($this->getOption('formato') === 'shell') {
                $this->stampaVariabili($controllo, $canale, $release);
            } else {
                $this->stampaEsito($controllo, $canale, $release);
            }
            return true;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }

    private function stampaEsito(ControlloAggiornamenti $controllo, CanaleAggiornamenti $canale, ?Release $release): void
    {
        $this->output('Versione installata: ' . $controllo->getVersioneInstallata() . ' (canale ' . $canale->value . ')');
        if ($release === null) {
            $this->output('Nessun aggiornamento disponibile.');
            return;
        }
        $this->output('Disponibile: ' . $release->versione . ' del ' . $release->dataPubblicazione);
        $this->output('Note di rilascio: ' . $release->urlNote);
        $this->output('Per installarla, dalla cartella dell\'installazione: sh aggiorna.sh');
    }

    /**
     * Valori controllati (versioni, URL https, impronte esadecimali): lo
     * script li legge riga per riga senza interpretarli.
     */
    private function stampaVariabili(ControlloAggiornamenti $controllo, CanaleAggiornamenti $canale, ?Release $release): void
    {
        $variabili = [
            'VERSIONE_INSTALLATA' => (string) $controllo->getVersioneInstallata(),
            'CANALE' => $canale->value,
            'VERSIONE' => $release === null ? '' : (string) $release->versione,
            'TAG' => $release?->tag ?? '',
            'ARCHIVIO' => $release?->urlArchivio ?? '',
            'SHA256' => $release?->sha256 ?? '',
            'ATTESTAZIONE' => $release?->urlAttestazione ?? '',
            'NOTE' => $release?->urlNote ?? '',
        ];
        foreach ($variabili as $nome => $valore) {
            if (preg_match('#^[A-Za-z0-9._:/?=&%+-]*$#', $valore) !== 1) {
                throw new \UnexpectedValueException('Valore non ammesso per ' . $nome . ': ' . $valore);
            }
            $this->output($nome . '=' . $valore);
        }
    }
}
