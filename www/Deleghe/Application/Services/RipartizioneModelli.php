<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\ModelloF24;
use Deleghe\Application\Enumerations\SezioneF24;

/**
 * Ripartizione dei righi della delega sui modelli F24 necessari, secondo i
 * vincoli del modello e delle specifiche tecniche:
 * - righi per sezione (ordinario), 10 righi complessivi (semplificato),
 *   28 righi (ELIDE);
 * - un solo codice ente per la sezione altri enti previdenziali;
 * - saldo di ogni modello non negativo: i crediti si collocano dopo i
 *   debiti, nei modelli che hanno saldo sufficiente, dividendoli se serve;
 *   collocando i debiti si lasciano liberi in ogni modello tanti righi
 *   quanti sono i crediti della sezione (del modello, nel semplificato);
 * - un credito IMU richiede, nello stesso modello, debiti IMU dello stesso
 *   comune almeno pari al credito; un credito verso la PA (PPAA) debiti
 *   pagabili con esso (elenco del D.M. 14/1/2014);
 * - la detrazione per l'abitazione principale si indica una sola volta,
 *   nel primo modello con la sezione IMU.
 */
class RipartizioneModelli
{

    /** @var string[] */
    private array $errori = [];
    /** @var callable(Versamento): bool */
    private $compensabileCreditiPa;

    /**
     * @param callable(Versamento): bool|null $compensabileCreditiPa debiti
     *        pagabili con i crediti verso la PA (default: nessuno)
     */
    public function __construct(?callable $compensabileCreditiPa = null)
    {
        $this->compensabileCreditiPa = $compensabileCreditiPa ?? fn() => false;
    }

    /** @var array<string, int> righi da lasciare liberi per i crediti, per sezione ('' = semplificato) */
    private array $riservati = [];

    /**
     * @param Versamento[] $righe
     * @return ModelloCompilato[]
     */
    public function ripartisci(ModelloF24 $modello, array $righe): array
    {
        $this->errori = [];
        $modelli = [];
        $crediti = array_values(array_filter($righe, fn(Versamento $riga) => $riga->importoCredito > 0));
        $this->riservati = [];
        foreach ($crediti as $credito) {
            $chiave = $modello === ModelloF24::semplificato ? '' : $credito->sezione->value;
            $this->riservati[$chiave] = ($this->riservati[$chiave] ?? 0) + 1;
        }
        foreach ($righe as $riga) {
            $debito = EsitoControllo::centesimi($riga->importoDebito);
            if ($debito > 0) {
                $this->collocaDebito($modelli, $modello, $riga, $debito);
            }
        }
        usort($crediti, fn(Versamento $a, Versamento $b) => $b->importoCredito <=> $a->importoCredito);
        foreach ($crediti as $riga) {
            $this->collocaCredito($modelli, $modello, $riga, EsitoControllo::centesimi($riga->importoCredito));
        }
        foreach ($modelli as $compilato) {
            if ($compilato->contaRighe(SezioneF24::tributiLocali) > 0) {
                $compilato->conDetrazioneImu = true;
                break;
            }
        }
        return $modelli;
    }

    /**
     * @return string[] crediti che non è stato possibile collocare
     */
    public function getErrori(): array
    {
        return $this->errori;
    }

    private function collocaDebito(array &$modelli, ModelloF24 $tipo, Versamento $riga, int $debito): void
    {
        foreach ($modelli as $compilato) {
            if ($this->haSpazio($compilato, $tipo, $riga, true)) {
                $compilato->aggiungi($riga, $debito, 0);
                return;
            }
        }
        $nuovo = new ModelloCompilato();
        $nuovo->aggiungi($riga, $debito, 0);
        $modelli[] = $nuovo;
    }

    private function collocaCredito(array $modelli, ModelloF24 $tipo, Versamento $riga, int $credito): void
    {
        $residuo = $credito;
        foreach ($modelli as $compilato) {
            if (($residuo === 0) || ($this->haSpazio($compilato, $tipo, $riga) === false)) {
                continue;
            }
            $disponibile = $compilato->getSaldo();
            if (($riga->sezione === SezioneF24::tributiLocali) && ($riga->codiceTributo?->isImu() === true)) {
                $disponibile = min($disponibile, $this->margineImuComune($compilato, $riga->codiceEnte));
            } elseif ($riga->codiceTributo?->isCreditoPa() === true) {
                $disponibile = min($disponibile, $this->margineCreditiPa($compilato));
            }
            $quota = min($residuo, $disponibile);
            if ($quota > 0) {
                $compilato->aggiungi($riga, 0, $quota);
                $residuo -= $quota;
            }
        }
        if ($residuo > 0) {
            $this->errori[] = sprintf('Il credito %s %s di € %s non trova capienza nei modelli (%s di € %s non collocati).',
                    $riga->sezione->getLabel(), $riga->getCodice(), EsitoControllo::euro($credito), $residuo === $credito ? 'importo intero' : 'quota', EsitoControllo::euro($residuo));
        }
    }

    /**
     * Debiti IMU del comune nel modello non ancora coperti da crediti IMU
     * dello stesso comune.
     */
    private function margineImuComune(ModelloCompilato $compilato, ?string $comune): int
    {
        $margine = 0;
        foreach ($compilato->righe[SezioneF24::tributiLocali->value] as $voce) {
            if (($voce['riga']->codiceEnte === $comune) && ($voce['riga']->codiceTributo?->isImu() === true)) {
                $margine += $voce['debito'] - $voce['credito'];
            }
        }
        return max(0, $margine);
    }

    /**
     * @param bool $debito per i debiti si escludono i righi riservati ai crediti
     */
    /**
     * Debiti del modello pagabili con i crediti verso la PA non ancora
     * coperti da crediti PPAA.
     */
    private function margineCreditiPa(ModelloCompilato $compilato): int
    {
        $margine = 0;
        foreach ($compilato->righe as $voci) {
            foreach ($voci as $voce) {
                if (($this->compensabileCreditiPa)($voce['riga'])) {
                    $margine += $voce['debito'];
                } elseif ($voce['riga']->codiceTributo?->isCreditoPa() === true) {
                    $margine -= $voce['credito'];
                }
            }
        }
        return max(0, $margine);
    }

    private function haSpazio(ModelloCompilato $compilato, ModelloF24 $tipo, Versamento $riga, bool $debito = false): bool
    {
        if (($riga->sezione === SezioneF24::altriEnti) && ($compilato->getCodiceAltroEnte() !== null) && ($compilato->getCodiceAltroEnte() !== $riga->codiceEnte)) {
            return false;
        }
        return match ($tipo) {
            ModelloF24::semplificato => $compilato->contaRighe() < ModelloF24::RIGHI_SEMPLIFICATO - ($debito ? $this->riserva('', ModelloF24::RIGHI_SEMPLIFICATO) : 0),
            default => $compilato->contaRighe($riga->sezione) < $riga->sezione->getRigheModello() - ($debito ? $this->riserva($riga->sezione->value, $riga->sezione->getRigheModello()) : 0),
        };
    }

    /**
     * Righi riservati ai crediti, lasciandone almeno uno per i debiti.
     */
    private function riserva(string $chiave, int $righi): int
    {
        return min($this->riservati[$chiave] ?? 0, $righi - 1);
    }
}
