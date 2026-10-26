<?php

namespace Deleghe\Application\Services;

use Deleghe\Application\Entities\Delega;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\Periodicita;
use CommSuite\Application\HelperClasses\Calendario;
use Deleghe\Application\Models\VersamentoModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Copie di versamenti nell'archivio del contribuente, per i versamenti che
 * si ripetono nel tempo (IMU caricata a mano, ritenute, contributi fissi,
 * rate). La copia è un versamento inserito a mano, da versare, indipendente
 * dall'originale (senza delega, credito né origine).
 *
 * Spostando la data di N mesi si aggiornano i riferimenti secondo il
 * formato della rateazione del codice tributo:
 * - mese di riferimento (00MM): avanza il mese e, a fine anno, l'anno di
 *   riferimento;
 * - rata su numero di rate (NNRR): avanza la rata di una per ogni copia,
 *   fino all'ultima;
 * - periodo MM/AAAA (INPS e altri enti) e mese delle accise: avanzano di N
 *   mesi;
 * - negli altri casi l'anno di riferimento avanza degli anni interi (es.
 *   IMU da un anno all'altro).
 *
 * La nuova data si calcola dalla scadenza nominale (un 17-19 del mese
 * preceduto solo da giorni non lavorativi dal 16 è il 16 slittato) ed è
 * spostata al primo giorno lavorativo successivo.
 */
class CopiaVersamenti
{

    private const CAMPI = ['descrizione', 'sezione', 'codiceTributo', 'codiceEnte', 'codiceSede', 'causale', 'posizione', 'cifraControllo', 'numeroRiferimento', 'rateazione',
        'annoRiferimento', 'periodoDa', 'periodoA', 'ravvedimento', 'immobiliVariati', 'acconto', 'saldo', 'numeroImmobili', 'tipoElide', 'elementiIdentificativi', 'provincia',
        'codiceIdentificativoEnte', 'meseRiferimento', 'numeroCertificazione', 'importoDebito', 'importoCredito'];
    public const MASSIMO_RIPETIZIONI = 36;

    /**
     * Giorno della scadenza ordinaria dei versamenti unitari.
     */
    private const GIORNO_UNIFICATO = 16;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Copia non salvata, spostata di $mesi mesi (e di $passi rate per la
     * rateazione NNRR); null se la rata supererebbe il numero di rate.
     */
    public function copia(Versamento $originale, int $mesi, int $passi = 1): ?Versamento
    {
        $copia = new Versamento($this->dataMapper);
        $copia->studio = $originale->studio;
        $copia->soggetto = $originale->soggetto;
        foreach (self::CAMPI as $campo) {
            $copia->$campo = $originale->$campo;
        }
        $copia->scadenza = self::spostaData($originale->scadenza, $mesi);
        return $this->avanzaRiferimenti($copia, $mesi, $passi) ? $copia : null;
    }

    /**
     * Copie a cadenza regolare dopo l'originale (non salvate); si fermano
     * all'ultima rata per la rateazione NNRR.
     *
     * @return Versamento[]
     */
    public function ripeti(Versamento $originale, Periodicita $periodicita, int $volte): array
    {
        $copie = [];
        for ($numero = 1; $numero <= min($volte, self::MASSIMO_RIPETIZIONI); $numero++) {
            $copia = $this->copia($originale, $numero * $periodicita->getMesi(), $numero);
            if ($copia === null) {
                break;
            }
            $copie[] = $copia;
        }
        return $copie;
    }

    /**
     * Errori di compilazione delle copie alla loro data, per copia.
     *
     * @param Versamento[] $copie
     * @return array<int, string[]>
     */
    public function verifica(array $copie): array
    {
        $controllo = new ControlloDelega($this->dataMapper);
        $errori = [];
        foreach ($copie as $indice => $copia) {
            $erroriCopia = $controllo->verificaDatiRiga($copia, $copia->sezione, $copia->scadenza);
            if (count($erroriCopia) > 0) {
                $errori[$indice] = array_values($erroriCopia);
            }
        }
        return $errori;
    }

    /**
     * @param Versamento[] $copie
     * @throws \InvalidArgumentException se una copia non è compilata correttamente
     */
    public function salva(array $copie): void
    {
        if (count($this->verifica($copie)) > 0) {
            throw new \InvalidArgumentException('Alcune copie non sono compilate correttamente.');
        }
        $primaTransazione = $this->dataMapper->startTransaction();
        foreach ($copie as $copia) {
            $this->dataMapper->save($copia);
        }
        $this->dataMapper->commitTransaction($primaTransazione);
    }

    /**
     * Versamenti della delega da riportare in una nuova data: quelli
     * inseriti a mano, esclusi gli utilizzi dei crediti (si compensano di
     * nuovo alla generazione) e i versamenti generati dagli altri moduli.
     *
     * @return Versamento[]
     */
    public function copiaDelega(Delega $delega, SismaDate $scadenza): array
    {
        $mesi = self::mesiTra($delega->scadenza, $scadenza);
        $copie = [];
        foreach ((new VersamentoModel($this->dataMapper))->getElenco($delega) as $versamento) {
            if (($versamento->isGenerata() === false) && ($versamento->credito === null)) {
                $copia = $this->copia($versamento, $mesi, $mesi === 0 ? 0 : 1);
                if ($copia !== null) {
                    $copia->scadenza = $scadenza;
                    $copie[] = $copia;
                }
            }
        }
        return $copie;
    }

    public static function mesiTra(\DateTimeInterface $da, \DateTimeInterface $a): int
    {
        return (((int) $a->format('Y') - (int) $da->format('Y')) * 12) + (int) $a->format('n') - (int) $da->format('n');
    }

    public static function spostaData(\DateTimeInterface $data, int $mesi): SismaDate
    {
        $nominale = new \DateTimeImmutable($data->format('Y-m-d'));
        $giornoOriginale = (int) $nominale->format('j');
        if (($giornoOriginale > self::GIORNO_UNIFICATO) && ($giornoOriginale <= self::GIORNO_UNIFICATO + 3)) {
            $sedici = $nominale->setDate((int) $nominale->format('Y'), (int) $nominale->format('n'), self::GIORNO_UNIFICATO);
            $slittato = true;
            for ($giorno = $sedici; $giorno < $nominale; $giorno = $giorno->modify('+1 day')) {
                $slittato = $slittato && (Calendario::isLavorativo($giorno) === false);
            }
            $nominale = $slittato ? $sedici : $nominale;
        }
        $primoDelMese = $nominale->modify('first day of this month')->modify(($mesi >= 0 ? '+' : '') . $mesi . ' months');
        $giorno = min((int) $nominale->format('j'), (int) $primoDelMese->format('t'));
        return Calendario::primoGiornoLavorativo($primoDelMese->setDate((int) $primoDelMese->format('Y'), (int) $primoDelMese->format('n'), $giorno));
    }

    private function avanzaRiferimenti(Versamento $copia, int $mesi, int $passi): bool
    {
        $formato = $copia->codiceTributo?->formatoRateazione;
        $rateazione = $copia->rateazione;
        $annoAvanzato = false;
        if (($formato === '00MM') && ($rateazione !== null) && (preg_match('/^00(\d{2})$/', $rateazione, $parti) === 1)) {
            [$mese, $anniInPiu] = self::avanzaMese((int) $parti[1], $mesi);
            $copia->rateazione = sprintf('00%02d', $mese);
            if ($copia->annoRiferimento !== null) {
                $copia->annoRiferimento += $anniInPiu;
            }
            $annoAvanzato = true;
        } elseif (($formato === 'NNRR') && ($rateazione !== null) && (preg_match('/^(\d{2})(\d{2})$/', $rateazione, $parti) === 1)) {
            $rata = (int) $parti[1] + $passi;
            if ($rata > (int) $parti[2]) {
                return false;
            }
            $copia->rateazione = sprintf('%02d%02d', $rata, (int) $parti[2]);
            $annoAvanzato = true;
        }
        foreach (['periodoDa', 'periodoA'] as $campo) {
            if (($copia->$campo !== null) && (preg_match('/^(\d{2})\/(\d{4})$/', $copia->$campo, $parti) === 1)) {
                [$mese, $anniInPiu] = self::avanzaMese((int) $parti[1], $mesi);
                $copia->$campo = sprintf('%02d/%04d', $mese, (int) $parti[2] + $anniInPiu);
                $annoAvanzato = true;
            }
        }
        if ($copia->meseRiferimento !== null) {
            [$mese, $anniInPiu] = self::avanzaMese($copia->meseRiferimento, $mesi);
            $copia->meseRiferimento = $mese;
            if ($copia->annoRiferimento !== null) {
                $copia->annoRiferimento += $anniInPiu;
            }
            $annoAvanzato = true;
        }
        if (($annoAvanzato === false) && ($copia->annoRiferimento !== null)) {
            $copia->annoRiferimento += intdiv($mesi, 12);
        }
        return true;
    }

    /**
     * @return array{0: int, 1: int} mese (1-12) e anni da aggiungere
     */
    private static function avanzaMese(int $mese, int $mesi): array
    {
        $indice = $mese - 1 + $mesi;
        $anni = (int) floor($indice / 12);
        return [$indice - ($anni * 12) + 1, $anni];
    }
}
