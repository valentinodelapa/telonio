<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Enumerations\LivelloConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Models\RigaContabileModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Elaborazioni sui movimenti in partita doppia: libro giornale, scheda
 * contabile del conto (mastrino, anche dei sottoconti del partitario) con
 * il saldo progressivo e bilancio di verifica con i saldi dei sottoconti
 * riportati su conti e mastri.
 */
class SaldiContabili
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Libro giornale: registrazioni del periodo in ordine di data e numero
     * con le loro righe.
     *
     * @return array{registrazioni: array<int, array{registrazione: \Contabilita\Application\Entities\RegistrazioneContabile, righe: \Contabilita\Application\Entities\RigaContabile[]}>, dare: float, avere: float}
     */
    public function giornale(Soggetto $soggetto, SismaDate $dal, SismaDate $al): array
    {
        $righe = [];
        foreach ((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, $dal, $al) as $riga) {
            $righe[$riga->registrazioneContabile->id][] = $riga;
        }
        $registrazioni = [];
        $dare = $avere = 0.0;
        foreach ((new RegistrazioneContabileModel($this->dataMapper))->getPerPeriodo($soggetto, $dal, $al) as $registrazione) {
            $righeRegistrazione = $righe[$registrazione->id] ?? [];
            usort($righeRegistrazione, fn($prima, $seconda) => $prima->ordine <=> $seconda->ordine);
            foreach ($righeRegistrazione as $riga) {
                $dare += $riga->dare;
                $avere += $riga->avere;
            }
            $registrazioni[] = ['registrazione' => $registrazione, 'righe' => $righeRegistrazione];
        }
        return ['registrazioni' => $registrazioni, 'dare' => round($dare, 2), 'avere' => round($avere, 2)];
    }

    /**
     * Scheda contabile del sottoconto nel periodo, con il saldo dei
     * movimenti dall'inizio dell'esercizio al giorno precedente.
     *
     * @return array{saldoIniziale: float, movimenti: array<int, array{riga: \Contabilita\Application\Entities\RigaContabile, saldo: float}>, dare: float, avere: float, saldoFinale: float}
     */
    public function schedaConto(Soggetto $soggetto, Conto $conto, SismaDate $inizioEsercizio, SismaDate $dal, SismaDate $al): array
    {
        $model = new RigaContabileModel($this->dataMapper);
        $saldo = 0.0;
        if ($dal->format('Y-m-d') > $inizioEsercizio->format('Y-m-d')) {
            $giornoPrima = new SismaDate((new \DateTimeImmutable($dal->format('Y-m-d')))->modify('-1 day')->format('Y-m-d'));
            foreach ($model->getMovimenti($soggetto, $conto, $inizioEsercizio, $giornoPrima) as $riga) {
                $saldo += $riga->dare - $riga->avere;
            }
        }
        $saldoIniziale = round($saldo, 2);
        $movimenti = [];
        $dare = $avere = 0.0;
        foreach ($model->getMovimenti($soggetto, $conto, $dal, $al) as $riga) {
            $saldo += $riga->dare - $riga->avere;
            $dare += $riga->dare;
            $avere += $riga->avere;
            $movimenti[] = ['riga' => $riga, 'saldo' => round($saldo, 2)];
        }
        return ['saldoIniziale' => $saldoIniziale, 'movimenti' => $movimenti, 'dare' => round($dare, 2), 'avere' => round($avere, 2), 'saldoFinale' => round($saldo, 2)];
    }

    /**
     * Bilancio di verifica del periodo: per ogni conto movimentato (e i
     * suoi conti superiori) totale dare, avere e saldo, in ordine di
     * codice; totali per natura e risultato economico (ricavi meno costi).
     *
     * @return array{righe: array<int, array{conto: Conto, dare: float, avere: float, saldo: float}>, nature: array<string, float>, dare: float, avere: float, risultato: float}
     */
    public function bilancioVerifica(Soggetto $soggetto, PianoConti $piano, SismaDate $dal, SismaDate $al): array
    {
        $totali = [];
        foreach ((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, $dal, $al) as $riga) {
            $codice = $riga->conto->codice;
            do {
                $totali[$codice] ??= ['dare' => 0.0, 'avere' => 0.0];
                $totali[$codice]['dare'] += $riga->dare;
                $totali[$codice]['avere'] += $riga->avere;
                $codice = LivelloConto::codicePadre($codice);
            } while ($codice !== null);
        }
        $righe = [];
        $nature = [];
        $dare = $avere = 0.0;
        foreach ((new ContoModel($this->dataMapper))->getElenco($piano) as $conto) {
            if (isset($totali[$conto->codice]) === false) {
                continue;
            }
            $voce = $totali[$conto->codice];
            $saldo = round($voce['dare'] - $voce['avere'], 2);
            $righe[] = ['conto' => $conto, 'dare' => round($voce['dare'], 2), 'avere' => round($voce['avere'], 2), 'saldo' => $saldo];
            if ($conto->livello === LivelloConto::sottoconto) {
                $nature[$conto->natura->value] = round(($nature[$conto->natura->value] ?? 0.0) + $saldo, 2);
                $dare += $voce['dare'];
                $avere += $voce['avere'];
            }
        }
        $risultato = round(-(($nature[NaturaConto::ricavo->value] ?? 0.0) + ($nature[NaturaConto::costo->value] ?? 0.0)), 2);
        return ['righe' => $righe, 'nature' => $nature, 'dare' => round($dare, 2), 'avere' => round($avere, 2), 'risultato' => $risultato];
    }
}
