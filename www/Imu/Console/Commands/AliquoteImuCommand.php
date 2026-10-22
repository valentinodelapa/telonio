<?php

namespace Imu\Console\Commands;

use Imu\Application\Services\TabelleRiferimento\ImportazioneProspetti;
use Imu\Application\Services\TabelleRiferimento\ProspettiMef;
use SismaFramework\Console\BaseClasses\BaseCommand;

/**
 * Importa o aggiorna i prospetti delle aliquote IMU pubblicati dal
 * Dipartimento delle Finanze per i comuni degli immobili in archivio (o
 * per quelli indicati).
 *
 * Uso:
 *   sisma aliquote-imu --anno=2026
 *   sisma aliquote-imu --anno=2026 --comuni=F205,H501 --copia=/tmp/prospetti
 */
class AliquoteImuCommand extends BaseCommand
{

    #[\Override]
    public function checkCompatibility(string $command): bool
    {
        return $command === 'aliquote-imu';
    }

    #[\Override]
    protected function help(): string
    {
        return <<<OUTPUT
Usage: sisma aliquote-imu [--anno=AAAA] [--comuni=CODICI] [--copia=CARTELLA]

Importa i prospetti delle aliquote IMU pubblicati dal Dipartimento delle
Finanze (dal 2025, art. 1, comma 757, L. 160/2019): elenco dei prospetti
dell'anno (CSV), PDF del prospetto di ogni comune, testo estratto con
pdftotext. Si importano le aliquote di base, l'assimilazione per anziani e
disabili, le aliquote differenziate (con la descrizione delle condizioni) e
l'elenco delle esenzioni del comune. I prospetti già importati si
aggiornano; il comando non elimina nulla.

Options:
  --anno=AAAA        anno dei prospetti (default: l'anno in corso)
  --comuni=CODICI    codici catastali separati da virgola (default: i
                     comuni degli immobili di tutti gli studi)
  --copia=CARTELLA   salva i file scaricati e li rilegge da lì
OUTPUT;
    }

    #[\Override]
    protected function execute(): bool
    {
        $anno = (int) ($this->getOption('anno') ?? date('Y'));
        try {
            $importazione = new ImportazioneProspetti(fonte: new ProspettiMef($this->getOption('copia')));
            $comuni = $this->getOption('comuni');
            $codici = $comuni === null ? $importazione->getComuniImmobili() : array_values(array_filter(array_map(fn(string $codice) => strtoupper(trim($codice)), explode(',', $comuni))));
            $this->output(sprintf('Prospetti delle aliquote IMU %d: %d comuni', $anno, count($codici)));
            $esito = $importazione->importa($anno, $codici, fn(string $messaggio) => $this->output($messaggio));
            $this->output(sprintf('Importati %d prospetti, %d comuni senza prospetto, %d errori.', $esito['importati'], count($esito['non_pubblicati']), count($esito['errori'])));
            return count($esito['errori']) === 0;
        } catch (\Throwable $errore) {
            $this->output('Errore: ' . $errore->getMessage());
            return false;
        }
    }
}
