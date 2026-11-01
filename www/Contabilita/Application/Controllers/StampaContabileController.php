<?php

namespace Contabilita\Application\Controllers;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\BaseClasses\ContabilitaController;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Services\SaldiContabili;
use SismaFramework\Core\HttpClasses\Response;

/**
 * Stampe contabili dell'esercizio: libro giornale, scheda contabile del
 * conto (anche dei clienti e fornitori) e bilancio di verifica, per il
 * periodo indicato (di default l'intero esercizio).
 */
class StampaContabileController extends ContabilitaController
{

    public function giornale(Soggetto $soggetto, int $anno = 0, string $dal = '', string $al = '', bool $stampa = false): Response
    {
        if (($periodo = $this->preparaPeriodo($soggetto, $anno, $dal, $al)) === null) {
            return $this->vaiAllAzienda($soggetto);
        }
        $this->vars['giornale'] = (new SaldiContabili($this->dataMapper))->giornale($soggetto, ...$periodo);
        $this->vars['sezioneAzienda'] = 'giornale';
        return $this->mostra('Libro giornale', $stampa ? 'stampaContabile/giornaleStampa' : 'stampaContabile/giornale');
    }

    public function bilancio(Soggetto $soggetto, int $anno = 0, string $dal = '', string $al = '', bool $stampa = false): Response
    {
        if (($periodo = $this->preparaPeriodo($soggetto, $anno, $dal, $al)) === null) {
            return $this->vaiAllAzienda($soggetto);
        }
        $this->vars['bilancio'] = (new SaldiContabili($this->dataMapper))->bilancioVerifica($soggetto, $this->piano, ...$periodo);
        $this->vars['nature'] = NaturaConto::cases();
        $this->vars['sezioneAzienda'] = 'bilancio';
        return $this->mostra('Bilancio di verifica', $stampa ? 'stampaContabile/bilancioStampa' : 'stampaContabile/bilancio');
    }

    public function scheda(Soggetto $soggetto, Conto $conto, int $anno = 0, string $dal = '', string $al = '', bool $stampa = false): Response
    {
        if (($periodo = $this->preparaPeriodo($soggetto, $anno, $dal, $al)) === null) {
            return $this->vaiAllAzienda($soggetto);
        }
        if ($conto->pianoConti->id !== $this->piano->id) {
            $this->aggiungiMessaggio('Conto di un altro piano dei conti.', 'danger');
            return $this->vaiAllAzienda($soggetto);
        }
        $this->vars['conto'] = $conto;
        $this->vars['scheda'] = (new SaldiContabili($this->dataMapper))->schedaConto($soggetto, $conto, $this->vars['esercizio']->dataInizio, ...$periodo);
        $this->vars['sezioneAzienda'] = 'bilancio';
        return $this->mostra('Scheda ' . $conto->getEtichetta(), $stampa ? 'stampaContabile/schedaStampa' : 'stampaContabile/scheda');
    }

    /**
     * Esercizio e periodo (dentro l'esercizio) della stampa.
     *
     * @return array{0: \SismaFramework\Orm\CustomTypes\SismaDate, 1: \SismaFramework\Orm\CustomTypes\SismaDate}|null
     */
    private function preparaPeriodo(Soggetto $soggetto, int $anno, string $dal, string $al): ?array
    {
        if ($this->caricaAzienda($soggetto) === null) {
            $this->aggiungiMessaggio('La contabilità non è aperta.', 'warning');
            return null;
        }
        $esercizio = $this->getEsercizio($soggetto, $anno);
        if ($esercizio === null) {
            return null;
        }
        $inizio = self::data($dal, $esercizio->dataInizio->format('Y-m-d'));
        $fine = self::data($al, $esercizio->dataFine->format('Y-m-d'));
        if (($esercizio->comprende($inizio) === false) || ($esercizio->comprende($fine) === false) || ($fine->format('Y-m-d') < $inizio->format('Y-m-d'))) {
            $inizio = $esercizio->dataInizio;
            $fine = $esercizio->dataFine;
        }
        $this->vars['dal'] = $inizio;
        $this->vars['al'] = $fine;
        return [$inizio, $fine];
    }

    private function mostra(string $titolo, string $vista): Response
    {
        $soggetto = $this->vars['soggetto'];
        $this->vars['pageTitle'] = $soggetto->nominativo . ' · ' . $titolo;
        $this->vars['titoloStampa'] = $titolo;
        $this->impostaBriciole($soggetto, $this->vars['esercizio']->anno, $titolo);
        return $this->render->generateView($vista, $this->vars);
    }
}
