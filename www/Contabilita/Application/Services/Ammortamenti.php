<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Models\StampaDefinitivaModel;
use Contabilita\Application\Models\PianoContiModel;
use Contabilita\Application\Enumerations\TipoStampaDefinitiva;
use Contabilita\Application\Enumerations\FunzioneConto;
use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Cespite;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\EsercizioContabile;
use Contabilita\Application\Entities\PianoConti;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Models\CespiteModel;
use Contabilita\Application\Models\ContoModel;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\RegistrazioneContabileModel;
use Contabilita\Application\Services\StampaDefinitiva\StampeDefinitive;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Ammortamenti dei cespiti per esercizio (art. 2426, c. 1, n. 2, c.c.;
 * art. 102 TUIR, dal 2027 art. 111 D.Lgs. 117/2026; beni immateriali art.
 * 103, dal 2027 art. 112):
 *
 * - dall'esercizio in cui il bene entra in funzione (o è acquistato), con
 *   il coefficiente annuo sul costo, ridotto alla metà nel primo esercizio
 *   se indicato (art. 102, c. 2; art. 111, c. 8);
 * - beni di costo unitario fino a 516,46 euro con la deduzione integrale:
 *   l'intero costo nel primo esercizio (art. 102, c. 5; art. 111, c. 10);
 * - fino al costo, dopo gli ammortamenti già effettuati prima della
 *   contabilità (fondo iniziale alla fine dell'esercizio indicato);
 * - nessuna quota nell'esercizio della dismissione, con la plusvalenza o
 *   minusvalenza tra il prezzo e il costo residuo.
 *
 * La scrittura dell'esercizio (ultimo giorno, origine "Ammortamenti")
 * addebita le quote ai conti di ammortamento e le accredita ai fondi; si
 * sostituisce rielaborando, salvo esercizio chiuso, giornale o registro dei
 * beni ammortizzabili stampati in definitiva. Per i beni dismessi
 * nell'esercizio registra anche l'eliminazione alla data della
 * dismissione (la fattura di vendita accredita il prezzo al conto del
 * bene): il fondo stornato dal conto del bene e il saldo residuo alla
 * plusvalenza o alla minusvalenza.
 *
 * Parte fiscale: la quota deducibile non supera il coefficiente fiscale
 * (se diverso da quello civilistico) né gli ammortamenti stanziati; le
 * quote eccedenti si recuperano negli esercizi successivi entro il
 * coefficiente; sul deducibile si applicano il limite del costo e la
 * percentuale deducibile (autovetture e telefonia). Le differenze sono le
 * variazioni del quadro RF (RF18 per i mezzi di trasporto, RF31 codice 99
 * in aumento, RF55 codice 99 in diminuzione) e riducono i costi per cassa
 * dei quadri RG ed RE (ComponentiReddito).
 */
class Ammortamenti
{

    public const ORIGINE = 'Ammortamenti';
    public const SOGLIA_BENI_MINORI = 516.46;
    public const DEDUCIBILE_AUTOVETTURE = 20.0;
    public const LIMITE_AUTOVETTURE = 18075.99;
    /** righi del quadro RF delle variazioni */
    public const RIGO_AUMENTO = 'RF31:99';
    public const RIGO_MEZZI_TRASPORTO = 'RF18';
    public const RIGO_DIMINUZIONE = 'RF55:99';

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Piano di ammortamento del cespite negli esercizi dell'azienda.
     *
     * @param EsercizioContabile[] $esercizi in ordine di data
     * @return array<int, array{fondoInizio: float, quota: float, fondoFine: float, residuo: float, primo: bool, dismesso: bool, plusvalenza: ?float, deducibile: float, variazioneCoefficiente: float, variazioneLimiti: float}> per anno dell'esercizio
     */
    public static function piano(Cespite $cespite, array $esercizi): array
    {
        $inizio = $cespite->getDataInizioAmmortamento()->format('Y-m-d');
        $dismissione = $cespite->dataDismissione?->format('Y-m-d');
        $fondo = round($cespite->fondoIniziale, 2);
        $fondoFiscale = $fondo;
        $percentualeFiscale = $cespite->percentualeFiscale ?? $cespite->percentuale;
        $fattoreLimiti = ($cespite->limiteCostoFiscale !== null && $cespite->costo > 0 ? min(1.0, $cespite->limiteCostoFiscale / $cespite->costo) : 1.0) * $cespite->percentualeDeducibile / 100;
        $piano = [];
        foreach ($esercizi as $esercizio) {
            $dal = $esercizio->dataInizio->format('Y-m-d');
            $al = $esercizio->dataFine->format('Y-m-d');
            if (($al < $inizio) || (($cespite->esercizioFondoIniziale !== null) && ($esercizio->anno <= $cespite->esercizioFondoIniziale))) {
                continue;
            }
            if (($dismissione !== null) && ($dismissione < $dal)) {
                break;
            }
            $primo = ($dal <= $inizio) && ($inizio <= $al);
            $dismesso = ($dismissione !== null) && ($dismissione <= $al);
            $quota = 0.0;
            if ($dismesso === false) {
                $intera = $cespite->deduzioneIntegrale && $primo ? $cespite->costo : $cespite->costo * $cespite->percentuale / 100 * ($primo && $cespite->riduzionePrimoEsercizio ? 0.5 : 1);
                $quota = round(max(0.0, min($cespite->costo - $fondo, $intera)), 2);
            }
            $fondoFine = round($fondo + $quota, 2);
            // deducibile entro il coefficiente fiscale e gli ammortamenti stanziati (recupero delle quote eccedenti)
            $lordo = 0.0;
            if ($dismesso === false) {
                $massimo = $cespite->deduzioneIntegrale && $primo ? $cespite->costo : $cespite->costo * $percentualeFiscale / 100 * ($primo && $cespite->riduzionePrimoEsercizio ? 0.5 : 1);
                $lordo = round(max(0.0, min($massimo, $cespite->costo - $fondoFiscale, $fondoFine - $fondoFiscale)), 2);
            }
            $fondoFiscale = round($fondoFiscale + $lordo, 2);
            $deducibile = round($lordo * $fattoreLimiti, 2);
            $piano[$esercizio->anno] = ['fondoInizio' => $fondo, 'quota' => $quota, 'fondoFine' => $fondoFine, 'residuo' => round($cespite->costo - $fondoFine, 2),
                'primo' => $primo, 'dismesso' => $dismesso, 'plusvalenza' => $dismesso ? round(($cespite->prezzoDismissione ?? 0.0) - ($cespite->costo - $fondoFine), 2) : null,
                'deducibile' => $deducibile, 'variazioneCoefficiente' => round($quota - $lordo, 2), 'variazioneLimiti' => round($lordo - $deducibile, 2)];
            $fondo = $fondoFine;
            if ($dismesso) {
                break;
            }
        }
        return $piano;
    }

    /**
     * Quote dell'esercizio per cespite.
     *
     * @return array<int, array{cespite: Cespite, fondoInizio: float, quota: float, fondoFine: float, residuo: float, primo: bool, dismesso: bool, plusvalenza: ?float, deducibile: float, variazioneCoefficiente: float, variazioneLimiti: float}>
     */
    public function calcola(Soggetto $soggetto, EsercizioContabile $esercizio): array
    {
        $esercizi = $this->getEsercizi($soggetto);
        $quote = [];
        foreach ((new CespiteModel($this->dataMapper))->getElenco($soggetto) as $cespite) {
            $voce = self::piano($cespite, $esercizi)[$esercizio->anno] ?? null;
            if ($voce !== null) {
                $quote[] = ['cespite' => $cespite] + $voce;
            }
        }
        return $quote;
    }

    /**
     * Variazioni fiscali dell'esercizio per rigo del quadro RF (importi
     * positivi) e, per i quadri RG ed RE, la parte indeducibile delle quote
     * per conto dell'ammortamento (negativa se si recuperano quote).
     *
     * @return array{righi: array<string, float>, conti: array<int, array{conto: Conto, importo: float}>}
     */
    public function getVariazioni(Soggetto $soggetto, EsercizioContabile $esercizio): array
    {
        $righi = [];
        $conti = [];
        foreach ($this->calcola($soggetto, $esercizio) as $voce) {
            $cespite = $voce['cespite'];
            if ($voce['variazioneCoefficiente'] > 0) {
                $righi[self::RIGO_AUMENTO] = ($righi[self::RIGO_AUMENTO] ?? 0.0) + $voce['variazioneCoefficiente'];
            } elseif ($voce['variazioneCoefficiente'] < 0) {
                $righi[self::RIGO_DIMINUZIONE] = ($righi[self::RIGO_DIMINUZIONE] ?? 0.0) - $voce['variazioneCoefficiente'];
            }
            if ($voce['variazioneLimiti'] > 0) {
                $rigo = $cespite->limiteCostoFiscale !== null ? self::RIGO_MEZZI_TRASPORTO : self::RIGO_AUMENTO;
                $righi[$rigo] = ($righi[$rigo] ?? 0.0) + $voce['variazioneLimiti'];
            }
            $indeducibile = round($voce['quota'] - $voce['deducibile'], 2);
            if ($indeducibile != 0) {
                $id = $cespite->contoAmmortamento->id;
                $conti[$id] ??= ['conto' => $cespite->contoAmmortamento, 'importo' => 0.0];
                $conti[$id]['importo'] = round($conti[$id]['importo'] + $indeducibile, 2);
            }
        }
        return ['righi' => array_map(fn(float $importo) => round($importo, 2), $righi), 'conti' => $conti];
    }

    /**
     * Il registro dei beni ammortizzabili dell'esercizio è stampato in
     * definitiva.
     */
    public function isRegistroStampato(Soggetto $soggetto, EsercizioContabile $esercizio): bool
    {
        return (new StampaDefinitivaModel($this->dataMapper))->getUltima($soggetto, TipoStampaDefinitiva::registroBeni, null, $esercizio->anno) !== null;
    }

    public static function getRiferimentoDismissione(Cespite $cespite): string
    {
        return 'dismissione-' . $cespite->id;
    }

    /**
     * @return EsercizioContabile[]
     */
    public function getEsercizi(Soggetto $soggetto): array
    {
        $esercizi = iterator_to_array((new EsercizioContabileModel($this->dataMapper))->getElenco($soggetto), false);
        usort($esercizi, fn(EsercizioContabile $primo, EsercizioContabile $secondo) => $primo->dataInizio->format('Y-m-d') <=> $secondo->dataInizio->format('Y-m-d'));
        return $esercizi;
    }

    public static function getRiferimento(int $anno): string
    {
        return 'ammortamenti-' . $anno;
    }

    /**
     * Registra (o sostituisce) la scrittura degli ammortamenti
     * dell'esercizio; senza quote toglie quella registrata.
     *
     * @param string[] $avvisi
     * @throws \DomainException se l'esercizio è chiuso o la scrittura è in una stampa definitiva
     */
    public function registra(Soggetto $soggetto, EsercizioContabile $esercizio, array &$avvisi = []): ?RegistrazioneContabile
    {
        if ($this->isRegistroStampato($soggetto, $esercizio)) {
            throw new \DomainException('Il registro dei beni ammortizzabili dell\'esercizio ' . $esercizio->getEtichetta() . ' è stampato in definitiva: annullare la stampa per rielaborare.');
        }
        $quote = $this->calcola($soggetto, $esercizio);
        // tutte le scritture o nessuna: esercizio aperto e giornale non stampato alle date
        self::verificaScrivibile($this->dataMapper, $soggetto, $esercizio, $esercizio->dataFine);
        foreach ($quote as $voce) {
            if ($voce['dismesso']) {
                self::verificaScrivibile($this->dataMapper, $soggetto, $esercizio, $voce['cespite']->dataDismissione);
            }
        }
        $this->registraDismissioni($soggetto, $esercizio, $quote, $avvisi);
        $righe = [];
        $totale = 0.0;
        foreach ($quote as $voce) {
            if ($voce['quota'] <= 0) {
                continue;
            }
            $cespite = $voce['cespite'];
            $righe[] = self::riga($this->dataMapper, $cespite->contoAmmortamento, $voce['quota'], 0.0, 'Ammortamento ' . $cespite->getEtichetta());
            $righe[] = self::riga($this->dataMapper, $cespite->contoFondo, 0.0, $voce['quota'], 'Fondo ' . $cespite->getEtichetta());
            $totale += $voce['quota'];
        }
        return self::sostituisci($this->dataMapper, $soggetto, $esercizio, self::ORIGINE, self::getRiferimento($esercizio->anno), $esercizio->dataFine,
                        'Ammortamenti dell\'esercizio ' . $esercizio->getEtichetta(), $righe, $avvisi);
    }

    /**
     * Eliminazione dei beni dismessi nell'esercizio, una scrittura per bene
     * alla data della dismissione; toglie quelle dei beni non più dismessi.
     *
     * @param array<int, array<string, mixed>> $quote
     * @param string[] $avvisi
     */
    private function registraDismissioni(Soggetto $soggetto, EsercizioContabile $esercizio, array $quote, array &$avvisi): void
    {
        $piano = (new PianoContiModel($this->dataMapper))->getPerAzienda($soggetto);
        $conti = new ContoModel($this->dataMapper);
        foreach ($quote as $voce) {
            $cespite = $voce['cespite'];
            if ($voce['dismesso'] === false) {
                if ($cespite->dataDismissione === null) {
                    self::sostituisci($this->dataMapper, $soggetto, $esercizio, self::ORIGINE, self::getRiferimentoDismissione($cespite), $esercizio->dataFine, '', [], $avvisi);
                }
                continue;
            }
            $righe = [];
            if ($voce['fondoFine'] > 0) {
                $righe[] = self::riga($this->dataMapper, $cespite->contoFondo, $voce['fondoFine'], 0.0, 'Storno del fondo ' . $cespite->getEtichetta());
                $righe[] = self::riga($this->dataMapper, $cespite->contoCespite, 0.0, $voce['fondoFine'], 'Storno del fondo ' . $cespite->getEtichetta());
            }
            $differenza = $voce['plusvalenza'];
            if ($differenza != 0) {
                $funzione = $differenza > 0 ? FunzioneConto::plusvalenze : FunzioneConto::minusvalenze;
                $conto = $piano === null ? null : $conti->getPerFunzione($piano, $funzione);
                if ($conto === null) {
                    $avvisi[] = 'Dismissione di ' . $cespite->getEtichetta() . ' non registrata: nel piano dei conti manca il conto con funzione "' . $funzione->getLabel() . '".';
                    continue;
                }
                $importo = abs($differenza);
                $righe[] = self::riga($this->dataMapper, $differenza > 0 ? $cespite->contoCespite : $conto, $importo, 0.0, $funzione->getLabel() . ' ' . $cespite->getEtichetta());
                $righe[] = self::riga($this->dataMapper, $differenza > 0 ? $conto : $cespite->contoCespite, 0.0, $importo, $funzione->getLabel() . ' ' . $cespite->getEtichetta());
            }
            self::sostituisci($this->dataMapper, $soggetto, $esercizio, self::ORIGINE, self::getRiferimentoDismissione($cespite), $cespite->dataDismissione,
                    'Eliminazione del cespite ' . $cespite->getEtichetta(), $righe, $avvisi);
        }
    }

    /**
     * Conti del fondo e dell'ammortamento proposti per il conto
     * dell'immobilizzazione: il fondo "F.do amm." con la stessa
     * descrizione o, altrimenti, con la stessa voce di bilancio; il costo
     * dell'ammortamento delle immobilizzazioni immateriali o materiali.
     *
     * @return array{0: ?Conto, 1: ?Conto}
     */
    public function proponiConti(PianoConti $piano, Conto $contoCespite): array
    {
        $fondo = $ammortamento = null;
        $voce = $contoCespite->voceCee?->value;
        $immateriale = ($voce !== null) && str_starts_with($voce, 'A.B.I.');
        foreach ((new ContoModel($this->dataMapper))->getMovimentabili($piano) as $conto) {
            $descrizione = mb_strtolower($conto->descrizione);
            if (($conto->natura === NaturaConto::attivita) && str_starts_with($descrizione, 'f.do amm')) {
                if (str_ends_with($descrizione, mb_strtolower($contoCespite->descrizione))) {
                    $fondo = $conto;
                } elseif (($fondo === null) && ($voce !== null) && ($conto->voceCee?->value === $voce)) {
                    $fondo = $conto;
                }
            }
            if (($conto->natura === NaturaConto::costo) && ($conto->voceCee?->value === ($immateriale ? 'E.B.10.a' : 'E.B.10.b'))) {
                $ammortamento ??= $conto;
            }
        }
        return [$fondo, $ammortamento];
    }

    public static function riga(DataMapper $dataMapper, Conto $conto, float $dare, float $avere, ?string $descrizione = null): RigaContabile
    {
        $riga = new RigaContabile($dataMapper);
        $riga->conto = $conto;
        $riga->dare = round($dare, 2);
        $riga->avere = round($avere, 2);
        $riga->descrizione = $descrizione === null ? null : mb_substr($descrizione, 0, 255);
        return $riga;
    }

    /**
     * Verifica, prima di scrivere, che nell'esercizio si possa registrare
     * alla data: esercizio aperto e giornale non stampato in definitiva.
     *
     * @throws \DomainException
     */
    public static function verificaScrivibile(DataMapper $dataMapper, Soggetto $soggetto, EsercizioContabile $esercizio, \DateTimeInterface $data): void
    {
        if ($esercizio->chiuso) {
            throw new \DomainException('L\'esercizio ' . $esercizio->getEtichetta() . ' è chiuso.');
        }
        $prova = new RegistrazioneContabile($dataMapper);
        $prova->soggetto = $soggetto;
        $prova->esercizioContabile = $esercizio;
        $prova->causale = CausaleContabile::giroconto;
        $prova->dataRegistrazione = new \SismaFramework\Orm\CustomTypes\SismaDate($data->format('Y-m-d'));
        $blocco = (new StampeDefinitive($dataMapper))->getBlocco($prova);
        if ($blocco !== null) {
            throw new \DomainException('Scritture non registrate: ' . lcfirst($blocco));
        }
    }

    /**
     * Sostituisce la scrittura generata con origine e riferimento: elimina
     * la precedente (se modificabile) e registra le nuove righe, se ce ne
     * sono, nell'esercizio alla data.
     *
     * @param RigaContabile[] $righe
     * @param string[] $avvisi
     * @throws \DomainException se la scrittura precedente non si può sostituire
     */
    public static function sostituisci(DataMapper $dataMapper, Soggetto $soggetto, EsercizioContabile $esercizio, string $origine, string $riferimento, \DateTimeInterface $data,
            string $descrizione, array $righe, array &$avvisi = []): ?RegistrazioneContabile
    {
        $salvataggio = new SalvataggioRegistrazione($dataMapper);
        $esistente = (new RegistrazioneContabileModel($dataMapper))->getGenerata($soggetto, $origine, $riferimento);
        if ($esistente !== null) {
            $blocco = $esistente->isModificabile() ? (new StampeDefinitive($dataMapper))->getBlocco($esistente) : 'l\'esercizio della registrazione n. ' . $esistente->numero . ' è chiuso.';
            if ($blocco !== null) {
                throw new \DomainException('Scrittura non aggiornata: ' . lcfirst($blocco));
            }
            $salvataggio->elimina($esistente);
        }
        if (count($righe) === 0) {
            return null;
        }
        if ($esercizio->chiuso) {
            throw new \DomainException('L\'esercizio ' . $esercizio->getEtichetta() . ' è chiuso.');
        }
        $registrazione = new RegistrazioneContabile($dataMapper);
        $registrazione->studio = $soggetto->studio;
        $registrazione->soggetto = $soggetto;
        $registrazione->esercizioContabile = $esercizio;
        $registrazione->dataRegistrazione = new \SismaFramework\Orm\CustomTypes\SismaDate($data->format('Y-m-d'));
        $registrazione->causale = CausaleContabile::giroconto;
        $registrazione->descrizione = mb_substr($descrizione, 0, 255);
        $registrazione->origine = $origine;
        $registrazione->riferimentoOrigine = $riferimento;
        return $salvataggio->salva($registrazione, [], $righe);
    }
}
