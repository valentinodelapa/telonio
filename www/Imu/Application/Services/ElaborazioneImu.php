<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Deleghe\Application\Entities\Versamento;
use Deleghe\Application\Enumerations\SezioneCodiceTributo;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\HelperClasses\Calendario;
use Deleghe\Application\Models\CodiceTributoModel;
use Deleghe\Application\Models\VersamentoModel;
use Deleghe\Application\Services\ArchivioVersamenti;
use Imu\Application\Entities\CalcoloImu;
use Imu\Application\Models\AccontoVersatoImuModel;
use Imu\Application\Models\CalcoloImuModel;
use Imu\Application\Models\RigoCalcoloImuModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Elaborazione dell'IMU del contribuente per un anno: salva il prospetto
 * di calcolo (CalcoloImu con i righi) e registra i versamenti dovuti
 * nell'archivio di Deleghe (ArchivioVersamenti, origine "Imu", riferimento
 * "imu-<anno>"), un rigo F24 per comune e codice tributo:
 * - acconto il 16 giugno, saldo il 16 dicembre (comma 762), spostati al
 *   primo giorno lavorativo; in unica soluzione tutto il 16 giugno;
 * - importi arrotondati all'euro (art. 1, c. 166, L. 296/2006);
 * - saldo = imposta annua meno l'acconto: quello già in una delega
 *   confermata, trasmessa o quietanzata se c'è, altrimenti quello
 *   calcolato;
 * - nessun versamento per il comune se l'imposta annua non supera
 *   l'importo minimo (regolamento comunale, in mancanza 12 euro);
 * - acconto versato senza la procedura (AccontoVersatoImu, anche per il
 *   2020: metà di IMU e TASI 2019): non si registra e conta nel saldo;
 * - enti non commerciali (comma 763): tre rate, le prime due (16 giugno e
 *   16 dicembre) pari ciascuna al 50% dell'imposta corrisposta per l'anno
 *   precedente, la terza a conguaglio entro il 16 giugno dell'anno
 *   successivo.
 *
 * Una nuova elaborazione sostituisce la precedente: i versamenti da
 * versare o in bozza vengono ricalcolati, le differenze con quelli in
 * deleghe non più modificabili restano nel calcolo.
 */
class ElaborazioneImu
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public static function getScadenzaAcconto(int $anno): SismaDate
    {
        return Calendario::primoGiornoLavorativo(new \DateTimeImmutable($anno . '-06-16'));
    }

    public static function getScadenzaSaldo(int $anno): SismaDate
    {
        return Calendario::primoGiornoLavorativo(new \DateTimeImmutable($anno . '-12-16'));
    }

    /**
     * @throws \InvalidArgumentException se un versamento non supera i
     *         controlli di Deleghe (es. codice tributo non più in uso)
     */
    public function elabora(Soggetto $soggetto, int $anno, bool $unicaSoluzione): CalcoloImu
    {
        $risultato = (new CalcoloImposta($this->dataMapper))->calcola($soggetto, $anno);
        $calcoloModel = new CalcoloImuModel($this->dataMapper);
        $primaTransazione = $this->dataMapper->startTransaction();
        $calcolo = $calcoloModel->getPerAnno($soggetto, $anno) ?? new CalcoloImu($this->dataMapper);
        if (isset($calcolo->id)) {
            $this->eliminaRighi($calcolo);
        }
        $calcolo->studio = $soggetto->studio;
        $calcolo->soggetto = $soggetto;
        $calcolo->anno = $anno;
        $calcolo->unicaSoluzione = $unicaSoluzione;
        $calcolo->dataElaborazione = new SismaDateTime();
        $versamenti = $this->preparaVersamenti($soggetto, $anno, $unicaSoluzione, $risultato);
        $calcolo->impostaAnnua = round(array_sum($risultato->getTotaliComune()), 2);
        $calcolo->avvisi = count($risultato->avvisi) > 0 ? implode("\n", $risultato->avvisi) : null;
        $this->dataMapper->save($calcolo);
        foreach ($risultato->righi as $rigo) {
            $rigo->calcoloImu = $calcolo;
            $this->dataMapper->save($rigo);
        }
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($soggetto, CalcoloImu::ORIGINE, CalcoloImu::getRiferimento($anno), $versamenti);
        $calcolo->differenze = count($esito->differenze) > 0 ? implode("\n", $esito->differenze) : null;
        $this->aggiornaTotali($calcolo);
        $this->dataMapper->save($calcolo);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $calcolo;
    }

    /**
     * Elimina il calcolo e i versamenti ancora modificabili.
     *
     * @return string[] differenze con i versamenti in deleghe non modificabili
     */
    public function elimina(CalcoloImu $calcolo): array
    {
        $primaTransazione = $this->dataMapper->startTransaction();
        $esito = (new ArchivioVersamenti($this->dataMapper))->registra($calcolo->soggetto, CalcoloImu::ORIGINE, CalcoloImu::getRiferimento($calcolo->anno), []);
        $this->eliminaRighi($calcolo);
        $this->dataMapper->delete($calcolo);
        $this->dataMapper->commitTransaction($primaTransazione);
        return $esito->differenze;
    }

    private function eliminaRighi(CalcoloImu $calcolo): void
    {
        foreach ((new RigoCalcoloImuModel($this->dataMapper))->getRighi($calcolo) as $rigo) {
            $this->dataMapper->delete($rigo);
        }
    }

    /**
     * Totali di acconto e saldo dei versamenti dell'anno nell'archivio,
     * compresi quelli in deleghe non modificabili.
     */
    private function aggiornaTotali(CalcoloImu $calcolo): void
    {
        $calcolo->acconto = $calcolo->saldo = 0.0;
        $scadenzaAcconto = self::getScadenzaAcconto($calcolo->anno)->format('Y-m-d');
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($calcolo->soggetto, CalcoloImu::ORIGINE, CalcoloImu::getRiferimento($calcolo->anno)) as $versamento) {
            if ($versamento->scadenza->format('Y-m-d') <= $scadenzaAcconto) {
                $calcolo->acconto = round($calcolo->acconto + $versamento->importoDebito, 2);
            } else {
                $calcolo->saldo = round($calcolo->saldo + $versamento->importoDebito, 2);
            }
        }
    }

    /**
     * Versamenti di acconto e saldo per comune e codice tributo, nuovi. Per
     * un acconto già in una delega non modificabile il saldo si calcola su
     * quell'importo; l'acconto ricalcolato si passa comunque all'archivio,
     * che non lo registra ma ne riporta l'eventuale differenza.
     *
     * @return Versamento[]
     */
    private function preparaVersamenti(Soggetto $soggetto, int $anno, bool $unicaSoluzione, RisultatoCalcoloImu $risultato): array
    {
        $scadenzaAcconto = self::getScadenzaAcconto($anno);
        $scadenzaSaldo = self::getScadenzaSaldo($anno);
        $accontiBloccati = $this->getBloccati($soggetto, $anno, $scadenzaAcconto);
        $accontiVersati = (new AccontoVersatoImuModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        if (CalcoloImposta::isEnteNonCommerciale($soggetto, $anno, $this->dataMapper)) {
            if ($unicaSoluzione) {
                $risultato->avviso('Ente non commerciale: il versamento è in tre rate (comma 763), l\'unica soluzione non si applica.');
            }
            return $this->preparaRateEnc($soggetto, $anno, $risultato, $accontiVersati);
        }
        if (($anno === 2020) && (count($accontiVersati) === 0)) {
            $risultato->avviso('2020: indicare negli acconti versati la metà di IMU e TASI 2019 effettivamente versata, su cui si calcola il saldo.');
        }
        if ($unicaSoluzione && (count($accontiVersati) > 0)) {
            $risultato->avviso('Acconto già versato senza la procedura: il calcolo prosegue con il saldo.');
            $unicaSoluzione = false;
        }
        if ($unicaSoluzione && (count($accontiBloccati) > 0)) {
            $risultato->avviso('L\'acconto è già in una delega non modificabile: il calcolo prosegue con acconto e saldo.');
            $unicaSoluzione = false;
        }
        $totaliComune = $risultato->getTotaliComune();
        $aliquote = new AliquoteComune($soggetto->studio, $this->dataMapper);
        $versamenti = [];
        foreach ($risultato->getTributi() as $chiave => $tributo) {
            $comune = $tributo['comune'];
            $denominazione = $risultato->comuni[$comune] ?? $comune;
            $minimo = $aliquote->getImportoMinimo($comune, $anno);
            if ($totaliComune[$comune] <= $minimo) {
                if ($totaliComune[$comune] > 0) {
                    $risultato->avviso(sprintf('%s: imposta annua di € %s non superiore al minimo di € %s, nessun versamento.', $denominazione, number_format($totaliComune[$comune], 2, ',', '.'), number_format($minimo, 2, ',', '.')));
                }
                continue;
            }
            $annuo = round($tributo['annuo']);
            if ($unicaSoluzione) {
                if ($annuo > 0) {
                    $versamenti[] = $this->creaVersamento($tributo, $anno, $scadenzaAcconto, $annuo, true, true, $tributo['detrazioneAnnua'], 'IMU ' . $anno . ' unica soluzione - ' . $denominazione);
                }
                continue;
            }
            $accontoCalcolato = round($tributo['acconto']);
            $versatoFuori = $accontiVersati[$chiave] ?? null;
            if (($accontoCalcolato > 0) && ($versatoFuori === null)) {
                $versamenti[] = $this->creaVersamento($tributo, $anno, $scadenzaAcconto, $accontoCalcolato, true, false, $tributo['detrazioneAcconto'], 'IMU acconto ' . $anno . ' - ' . $denominazione);
            }
            $importoAcconto = match (true) {
                isset($accontiBloccati[$chiave]) => $accontiBloccati[$chiave]->importoDebito,
                $versatoFuori !== null => $versatoFuori->importo,
                default => $accontoCalcolato,
            };
            $saldo = round($annuo - $importoAcconto, 2);
            if ($saldo > 0) {
                $versamenti[] = $this->creaVersamento($tributo, $anno, $scadenzaSaldo, $saldo, false, true, round($tributo['detrazioneAnnua'] - $tributo['detrazioneAcconto'], 2), 'IMU saldo ' . $anno . ' - ' . $denominazione);
            } elseif ($saldo < 0) {
                $risultato->avviso(sprintf('%s, codice %s: acconto superiore all\'imposta annua di € %s, da chiedere a rimborso o compensare.', $denominazione, $tributo['codice'], number_format(-$saldo, 2, ',', '.')));
            }
        }
        foreach (array_diff_key($accontiVersati, $risultato->getTributi()) as $chiave => $versato) {
            $risultato->avviso(sprintf('Acconto versato di € %s per il codice %s del comune %s senza imposta annua: da chiedere a rimborso o compensare.', number_format($versato->importo, 2, ',', '.'), $versato->codiceTributo, $versato->codiceComune));
        }
        return $versamenti;
    }

    public static function getScadenzaConguaglioEnc(int $anno): SismaDate
    {
        return Calendario::primoGiornoLavorativo(new \DateTimeImmutable(($anno + 1) . '-06-16'));
    }

    /**
     * Tre rate dell'ente non commerciale per comune e codice tributo: le
     * prime due al 50% dell'imposta corrisposta per l'anno precedente,
     * la terza a conguaglio (comma 763).
     *
     * @param array<string, \Imu\Application\Entities\AccontoVersatoImu> $accontiVersati
     * @return Versamento[]
     */
    private function preparaRateEnc(Soggetto $soggetto, int $anno, RisultatoCalcoloImu $risultato, array $accontiVersati): array
    {
        $scadenze = [self::getScadenzaAcconto($anno), self::getScadenzaSaldo($anno), self::getScadenzaConguaglioEnc($anno)];
        $bloccate = [$this->getBloccati($soggetto, $anno, $scadenze[0]), $this->getBloccati($soggetto, $anno, $scadenze[1])];
        $precedente = $this->getCorrispostoAnnoPrecedente($soggetto, $anno, $risultato);
        $aliquote = new AliquoteComune($soggetto->studio, $this->dataMapper);
        $totaliComune = $risultato->getTotaliComune();
        $versamenti = [];
        foreach ($risultato->getTributi() as $chiave => $tributo) {
            $denominazione = $risultato->comuni[$tributo['comune']] ?? $tributo['comune'];
            if ($totaliComune[$tributo['comune']] <= $aliquote->getImportoMinimo($tributo['comune'], $anno)) {
                continue;
            }
            $rata = $precedente === null ? round($tributo['acconto']) : round(($precedente[$chiave] ?? 0) / 2);
            $versate = 0.0;
            foreach ([0, 1] as $indice) {
                if (($indice === 0) && isset($accontiVersati[$chiave])) {
                    $versate += $accontiVersati[$chiave]->importo;
                    continue;
                }
                if ($rata > 0) {
                    $versamenti[] = $this->creaVersamento($tributo, $anno, $scadenze[$indice], $rata, true, false, 0.0, 'IMU ENC ' . ($indice + 1) . 'ª rata ' . $anno . ' - ' . $denominazione);
                }
                $versate += isset($bloccate[$indice][$chiave]) ? $bloccate[$indice][$chiave]->importoDebito : $rata;
            }
            $conguaglio = round(round($tributo['annuo']) - $versate, 2);
            if ($conguaglio > 0) {
                $versamenti[] = $this->creaVersamento($tributo, $anno, $scadenze[2], $conguaglio, false, true, 0.0, 'IMU ENC conguaglio ' . $anno . ' - ' . $denominazione);
            } elseif ($conguaglio < 0) {
                $risultato->avviso(sprintf('%s, codice %s: rate superiori all\'imposta dovuta di € %s, credito da indicare nella dichiarazione ENC (rimborso o compensazione).', $denominazione, $tributo['codice'], number_format(-$conguaglio, 2, ',', '.')));
            }
        }
        return $versamenti;
    }

    /**
     * Imposta corrisposta per l'anno precedente, per comune e codice
     * tributo: versamenti registrati dalla procedura per quell'anno; in
     * mancanza l'imposta del calcolo salvato per quell'anno; senza nemmeno
     * quello null (rate pari all'imposta del primo semestre con le aliquote
     * dell'anno precedente, come per gli altri contribuenti). Con avviso.
     *
     * @return array<string, float>|null
     */
    private function getCorrispostoAnnoPrecedente(Soggetto $soggetto, int $anno, RisultatoCalcoloImu $risultato): ?array
    {
        $corrisposto = [];
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, CalcoloImu::ORIGINE, CalcoloImu::getRiferimento($anno - 1)) as $versamento) {
            $chiave = $versamento->codiceEnte . '|' . $versamento->getCodice();
            $corrisposto[$chiave] = round(($corrisposto[$chiave] ?? 0) + $versamento->importoDebito, 2);
        }
        if (count($corrisposto) > 0) {
            return $corrisposto;
        }
        if ($anno - 1 < AliquoteComune::PRIMO_ANNO_PROSPETTO - 5) {
            $risultato->avviso('Ente non commerciale: per il ' . $anno . ' le prime due rate sono pari alla metà di IMU e TASI corrisposte per l\'anno precedente, da indicare negli acconti versati.');
            return [];
        }
        $calcolo = (new CalcoloImuModel($this->dataMapper))->getPerAnno($soggetto, $anno - 1);
        if ($calcolo === null) {
            $risultato->avviso('Ente non commerciale: nessun versamento né calcolo ' . ($anno - 1) . ' nella procedura, prime due rate pari all\'imposta del primo semestre: indicare negli acconti versati quanto effettivamente corrisposto.');
            return null;
        }
        $risultato->avviso('Ente non commerciale: versamenti ' . ($anno - 1) . ' non registrati nella procedura, prime due rate calcolate sull\'imposta del calcolo ' . ($anno - 1) . '.');
        foreach ((new RigoCalcoloImuModel($this->dataMapper))->getRighi($calcolo) as $rigo) {
            if ($rigo->isImponibile()) {
                $chiave = $rigo->codiceComune . '|' . $rigo->fattispecie->getCodiceTributo();
                $corrisposto[$chiave] = round(($corrisposto[$chiave] ?? 0) + $rigo->impostaAnnua - $rigo->quotaStatoAnnua - $rigo->detrazioneAnnua, 2);
                if ($rigo->fattispecie->getCodiceTributoStato() !== null) {
                    $chiaveStato = $rigo->codiceComune . '|' . $rigo->fattispecie->getCodiceTributoStato();
                    $corrisposto[$chiaveStato] = round(($corrisposto[$chiaveStato] ?? 0) + $rigo->quotaStatoAnnua, 2);
                }
            }
        }
        return $corrisposto;
    }

    /**
     * Versamenti della data registrati in precedenza e ora in deleghe non
     * modificabili, per comune e codice tributo.
     *
     * @return array<string, Versamento>
     */
    private function getBloccati(Soggetto $soggetto, int $anno, SismaDate $scadenza): array
    {
        $bloccati = [];
        foreach ((new VersamentoModel($this->dataMapper))->getGenerati($soggetto, CalcoloImu::ORIGINE, CalcoloImu::getRiferimento($anno)) as $versamento) {
            if (($versamento->isModificabile() === false) && ($versamento->scadenza->format('Y-m-d') === $scadenza->format('Y-m-d'))) {
                $bloccati[$versamento->codiceEnte . '|' . $versamento->getCodice()] = $versamento;
            }
        }
        return $bloccati;
    }

    private function creaVersamento(array $tributo, int $anno, SismaDate $scadenza, float $importo, bool $acconto, bool $saldo, float $detrazione, string $descrizione): Versamento
    {
        $versamento = new Versamento($this->dataMapper);
        $versamento->scadenza = $scadenza;
        $versamento->descrizione = mb_substr($descrizione, 0, 100);
        $versamento->sezione = SezioneF24::tributiLocali;
        $versamento->codiceTributo = (new CodiceTributoModel($this->dataMapper))->getByCodice(SezioneCodiceTributo::tributiLocali, $tributo['codice']);
        $versamento->codiceEnte = $tributo['comune'];
        $versamento->acconto = $acconto;
        $versamento->saldo = $saldo;
        $versamento->numeroImmobili = min(999, count($tributo['immobili']));
        $versamento->annoRiferimento = $anno;
        $versamento->importoDebito = $importo;
        $versamento->detrazioneImu = $detrazione > 0 ? $detrazione : null;
        return $versamento;
    }
}
