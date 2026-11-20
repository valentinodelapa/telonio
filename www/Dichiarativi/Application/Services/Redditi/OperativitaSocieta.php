<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Contabilita\Application\Entities\Cespite;
use Contabilita\Application\Enumerations\VoceCee;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Contabilita\Application\Models\CespiteModel;
use Contabilita\Application\Models\ContrattoLeasingModel;
use Contabilita\Application\Services\Leasing;
use Contabilita\Application\Models\EsercizioContabileModel;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Services\ChiusuraEsercizio;
use Contabilita\Application\Services\ComponentiReddito;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Prospetto del quadro RS per la verifica dell'operatività delle società
 * (società di comodo, art. 30 L. 724/1994): righi da RS11 a RS20 di
 * Redditi SP, da RS116 a RS125 di Redditi SC (istruzioni e specifiche
 * 2026).
 *
 * Se è indicata a mano una causa di esclusione, di disapplicazione (codici
 * 2, 3, 7, 8, 9, 99), l'impegno allo scioglimento o l'interpello per le
 * imposte e l'IRAP, il prospetto non si propone. Altrimenti dalla
 * contabilità dell'esercizio e dei due precedenti:
 * - valori dei beni (art. 110 Tuir: costo, al più il costo fiscale
 *   massimo) dal registro dei cespiti, ragguagliati al periodo di possesso
 *   di ciascun esercizio: fabbricati (voce B.II.1) tra gli immobili,
 *   gli altri beni tra le altre immobilizzazioni, escluse quelle in corso
 *   e gli acconti; media del triennio (colonna 1) e valore dell'esercizio
 *   (colonna 4, solo se la società non è operativa);
 * - titoli e crediti non commerciali dai saldi dei conti delle voci B.III e
 *   C.III dello stato patrimoniale (media dei saldi iniziale e finale di
 *   ciascun esercizio);
 * - ricavi effettivi: media dei ricavi, degli incrementi di rimanenze e dei
 *   proventi (voci A1, A2, A3 e A5 del conto economico) degli esercizi in
 *   contabilità;
 * - ricavi presunti e reddito presunto con le percentuali del comma 1 e
 *   del comma 3 dell'art. 30 (formule dei controlli delle specifiche);
 * - casella "Casi particolari" 1 se nei tre esercizi non ci sono beni, 2
 *   se mancano solo nell'esercizio della dichiarazione.
 * Se i ricavi effettivi sono inferiori ai presunti la società non è
 * operativa: reddito presunto (colonna 5) e reddito minimo, con le
 * agevolazioni e i proventi esenti (colonna 1 e 2 del rigo successivo)
 * inseriti a mano.
 */
class OperativitaSocieta
{

    /** Codici dei campi per modello: righi dei beni (titoli, immobili, 8-bis, A/10, abitativi, altre, piccoli comuni) e righi dei totali (SP: RS19 col. 1 ACE, col. 2 agevolazioni, col. 3 variazioni in aumento; SC: RS124 col. 1 agevolazioni, col. 2 variazioni in aumento) */
    private const CAMPI = [
        'RSP' => ['esclusione' => 'RS011001', 'disapplicazione' => 'RS011002', 'imposte' => 'RS011004', 'irap' => 'RS011006', 'casi' => 'RS011008', 'scioglimento' => 'RS011000',
            'beni' => ['titoli' => ['RS012001', 'RS012004'], 'immobili' => ['RS013001', 'RS013004'], 'navi' => ['RS013A01', 'RS013A04'], 'uffici' => ['RS014001', 'RS014004'],
                'abitativi' => ['RS015001', 'RS015004'], 'altre' => ['RS016001', 'RS016004'], 'piccoliComuni' => ['RS017001', 'RS017004']],
            'presunti' => 'RS018002', 'effettivi' => 'RS018003', 'redditoPresunto' => 'RS018005',
            'agevolazioni' => 'RS019001', 'esenti' => 'RS019002', 'aumenti' => 'RS019003', 'totaleRettifiche' => 'RS019004', 'redditoMinimo' => 'RS020001'],
        'RSC' => ['esclusione' => 'RS116001', 'disapplicazione' => 'RS116002', 'imposte' => 'RS116004', 'irap' => 'RS116006', 'casi' => 'RS116008', 'scioglimento' => 'RS116000',
            'beni' => ['titoli' => ['RS117001', 'RS117004'], 'immobili' => ['RS118001', 'RS118004'], 'navi' => ['RS118A01', 'RS118A04'], 'uffici' => ['RS119001', 'RS119004'],
                'abitativi' => ['RS120001', 'RS120004'], 'altre' => ['RS121001', 'RS121004'], 'piccoliComuni' => ['RS122001', 'RS122004']],
            'presunti' => 'RS123002', 'effettivi' => 'RS123003', 'redditoPresunto' => 'RS123005',
            'agevolazioni' => 'RS124001', 'esenti' => null, 'aumenti' => 'RS124002', 'totaleRettifiche' => 'RS124003', 'redditoMinimo' => 'RS125001'],
    ];

    /** Percentuali dell'art. 30, comma 1 (ricavi presunti) e comma 3 (reddito presunto) */
    private const PERCENTUALI_RICAVI = ['titoli' => 0.01, 'immobili' => 0.03, 'navi' => 0.06, 'uffici' => 0.025, 'abitativi' => 0.02, 'altre' => 0.15, 'piccoliComuni' => 0.005];
    private const PERCENTUALI_REDDITO = ['titoli' => 0.0075, 'immobili' => 0.0238, 'navi' => 0.0475, 'uffici' => 0.02, 'abitativi' => 0.015, 'altre' => 0.12, 'piccoliComuni' => 0.0045];

    /** Codici di disapplicazione totale (con 4, 5 e 6 il prospetto si compila senza i beni interessati) */
    private const DISAPPLICAZIONE_TOTALE = [2, 3, 7, 8, 9, 99];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[] avvisi
     */
    public function proponi(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): array
    {
        $this->avvisi = [];
        $campi = self::CAMPI[$dichiarazione->modello->value] ?? null;
        if ($campi === null) {
            return [];
        }
        if (($quadri->get($campi['esclusione']) !== null) || ($quadri->get($campi['scioglimento']) !== null)
                || in_array((int) $quadri->get($campi['disapplicazione']), self::DISAPPLICAZIONE_TOTALE, true)
                || (($quadri->get($campi['imposte']) !== null) && ($quadri->get($campi['irap']) !== null))) {
            return [];
        }
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $anni = [$anno - 2, $anno - 1, $anno];
        $valori = $this->getValoriBeni($soggetto, $anni);
        $valori['titoli'] = $this->getTitoliECrediti($soggetto, $anni);
        $medi = $esercizio = [];
        foreach (array_keys(self::PERCENTUALI_RICAVI) as $tipo) {
            $media = (int) round(array_sum($valori[$tipo] ?? []) / 3);
            [$colonnaMedia, $colonnaEsercizio] = $campi['beni'][$tipo];
            $quadri->proponi($colonnaMedia, $media ?: null);
            $medi[$tipo] = $quadri->getImporto($colonnaMedia);
            // valore dell'esercizio (colonna 4): si indica solo se la società non è operativa (controlli delle specifiche)
            $quadri->proponi($colonnaEsercizio, null);
            $esercizio[$tipo] = $quadri->isManuale($colonnaEsercizio) ? $quadri->getImporto($colonnaEsercizio) : (int) round($valori[$tipo][$anno] ?? 0);
        }
        $casi = null;
        if (array_sum($medi) === 0) {
            $casi = 1;
        } elseif (array_sum($esercizio) === 0) {
            $casi = 2;
        }
        $quadri->proponi($campi['casi'], $casi);
        $casi = $quadri->get($campi['casi']) === null ? null : (int) $quadri->get($campi['casi']);
        if ($casi === 1) {
            $this->avvisi[] = 'Quadro RS, società di comodo: nessun bene nei registri dei cespiti e nessun titolo o credito non commerciale nei saldi dell\'esercizio e dei due precedenti, casella "Casi particolari" 1 (il resto del prospetto non si compila).';
            foreach (['presunti', 'effettivi', 'redditoPresunto', 'redditoMinimo'] as $chiave) {
                $quadri->proponi($campi[$chiave], null);
            }
            return $this->avvisi;
        }
        $presunti = (int) round(array_sum(array_map(fn(string $tipo) => $medi[$tipo] * self::PERCENTUALI_RICAVI[$tipo], array_keys($medi))));
        $quadri->proponi($campi['presunti'], $presunti ?: null);
        [$effettivi, $esercizi] = $this->getRicaviMedi($soggetto, $anni);
        $quadri->proponi($campi['effettivi'], $effettivi ?: null);
        if ($esercizi < 3) {
            $this->avvisi[] = sprintf('Quadro RS, società di comodo: ricavi effettivi (media) dai %d esercizi in contabilità; con meno di tre esercizi verificare la media con i bilanci degli anni precedenti.', $esercizi);
        }
        $nonOperativa = $quadri->getImporto($campi['effettivi']) < $quadri->getImporto($campi['presunti']);
        foreach ($campi['beni'] as $tipo => [, $colonnaEsercizio]) {
            $quadri->proponi($colonnaEsercizio, $nonOperativa && ($casi !== 2) && ($esercizio[$tipo] > 0) ? $esercizio[$tipo] : null);
        }
        $redditoPresunto = ($nonOperativa && ($casi !== 2))
                ? (int) round(array_sum(array_map(fn(string $tipo) => $esercizio[$tipo] * self::PERCENTUALI_REDDITO[$tipo], array_keys($esercizio)))) : 0;
        $quadri->proponi($campi['redditoPresunto'], $redditoPresunto ?: null);
        if ($nonOperativa) {
            // SP: ACE (RS19 col. 1) + agevolazioni (col. 2) - variazioni in aumento (col. 3); SC: agevolazioni (RS124 col. 1) - variazioni in aumento (col. 2)
            $rettifiche = $quadri->getImporto($campi['agevolazioni']) + ($campi['esenti'] === null ? 0 : $quadri->getImporto($campi['esenti'])) - $quadri->getImporto($campi['aumenti']);
            $quadri->proponi($campi['totaleRettifiche'], $rettifiche ?: null);
            $minimo = $quadri->getImporto($campi['redditoPresunto']) - $rettifiche;
            $quadri->proponi($campi['redditoMinimo'], $minimo ?: null);
            $this->avvisi[] = sprintf('Quadro RS: società non operativa (ricavi effettivi %s euro, inferiori ai presunti %s): reddito minimo %s euro da confrontare con il reddito dichiarato; agevolazioni e proventi esenti si indicano a mano, salvo cause di esclusione o di disapplicazione.',
                    number_format($quadri->getImporto($campi['effettivi']), 0, ',', '.'), number_format($quadri->getImporto($campi['presunti']), 0, ',', '.'), number_format($minimo, 0, ',', '.'));
        } else {
            $quadri->proponi($campi['totaleRettifiche'], null);
            $quadri->proponi($campi['redditoMinimo'], null);
        }
        $this->avvisi[] = 'Quadro RS, società di comodo: fabbricati dei cespiti e dei contratti di leasing per categoria catastale dell\'immobile collegato (A/10 tra gli uffici, abitazioni acquisite nel triennio tra gli abitativi); spostare a mano quelli nei comuni sotto i 1.000 abitanti e gli impianti fotovoltaici; cause di esclusione o disapplicazione a mano.';
        return $this->avvisi;
    }

    /**
     * Valore dei beni dei cespiti per tipo e anno, ragguagliato ai giorni
     * di possesso dell'anno.
     *
     * @param int[] $anni
     * @return array<string, array<int, float>>
     */
    private function getValoriBeni(Soggetto $soggetto, array $anni): array
    {
        $valori = [];
        $ultimo = end($anni);
        foreach ((new CespiteModel($this->dataMapper))->getElenco($soggetto) as $cespite) {
            $voce = $cespite->contoCespite->voceCee;
            if (in_array($voce, [VoceCee::attivoBI6, VoceCee::attivoBII5], true)) {
                continue;
            }
            $tipo = $this->getTipoBene($cespite->immobile, ($voce === VoceCee::attivoBII1) || ($cespite->immobile !== null), (int) $cespite->dataAcquisto->format('Y'), $ultimo);
            $costo = $cespite->limiteCostoFiscale === null ? $cespite->costo : min($cespite->costo, $cespite->limiteCostoFiscale);
            foreach ($anni as $anno) {
                $valori[$tipo][$anno] = ($valori[$tipo][$anno] ?? 0.0) + $costo * self::getQuotaPossesso($cespite, $anno);
            }
        }
        // beni in locazione finanziaria: costo sostenuto dalla concedente per il periodo del contratto
        foreach ((new ContrattoLeasingModel($this->dataMapper))->getElenco($soggetto) as $contratto) {
            $tipo = $this->getTipoBene($contratto->immobile, $contratto->beneImmobile || ($contratto->immobile !== null), (int) $contratto->dataInizio->format('Y'), $ultimo);
            foreach ($anni as $anno) {
                $valori[$tipo][$anno] = ($valori[$tipo][$anno] ?? 0.0) + $contratto->costoConcedente * Leasing::getQuotaPossesso($contratto, $anno);
            }
        }
        return $valori;
    }

    /**
     * Riga del prospetto per il bene: immobili A/10 (uffici), abitazioni
     * acquisite nell'esercizio o nei due precedenti (abitativi), altri
     * fabbricati (immobili, categoria catastale alla fine dell'anno
     * dall'Anagrafica), altri beni (altre immobilizzazioni).
     */
    private function getTipoBene(?Immobile $immobile, bool $fabbricato, int $annoAcquisto, int $anno): string
    {
        if ($fabbricato === false) {
            return 'altre';
        }
        $categoria = $immobile === null ? null : (new DatiCatastaliModel($this->dataMapper))->getVigenteAl($immobile, new \DateTimeImmutable($anno . '-12-31'))?->categoria;
        return match (true) {
            $categoria === CategoriaCatastale::a10 => 'uffici',
            ($categoria?->isAbitativa() === true) && ($annoAcquisto >= $anno - 2) => 'abitativi',
            default => 'immobili',
        };
    }

    /**
     * Parte dell'anno in cui il bene è posseduto (dall'acquisto alla
     * dismissione).
     */
    public static function getQuotaPossesso(Cespite $cespite, int $anno): float
    {
        $inizio = new \DateTimeImmutable($anno . '-01-01');
        $fine = new \DateTimeImmutable($anno . '-12-31');
        $dal = max($inizio, new \DateTimeImmutable($cespite->dataAcquisto->format('Y-m-d')));
        $al = $cespite->dataDismissione === null ? $fine : min($fine, new \DateTimeImmutable($cespite->dataDismissione->format('Y-m-d')));
        if ($al < $dal) {
            return 0.0;
        }
        return ($dal->diff($al)->days + 1) / ($inizio->diff($fine)->days + 1);
    }

    /**
     * Titoli e crediti non commerciali (voci B.III e C.III dell'attivo):
     * media dei saldi iniziale e finale di ciascun anno.
     *
     * @param int[] $anni
     * @return array<int, float>
     */
    private function getTitoliECrediti(Soggetto $soggetto, array $anni): array
    {
        $saldi = [];
        foreach (array_merge([$anni[0] - 1], $anni) as $anno) {
            $saldo = 0.0;
            foreach ((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31')) as $riga) {
                $voce = $riga->conto->voceCee?->value;
                if (($riga->registrazioneContabile->origine !== ChiusuraEsercizio::ORIGINE) && ($voce !== null)
                        && (str_starts_with($voce, 'A.B.III.') || str_starts_with($voce, 'A.C.III.'))) {
                    $saldo += $riga->dare - $riga->avere;
                }
            }
            $saldi[$anno] = max(0.0, $saldo);
        }
        $valori = [];
        foreach ($anni as $anno) {
            $valori[$anno] = ($saldi[$anno - 1] + $saldi[$anno]) / 2;
        }
        return $valori;
    }

    /**
     * Media dei ricavi, degli incrementi di rimanenze e dei proventi (voci
     * A1, A2, A3 e A5) degli anni con l'esercizio contabile, per cassa in
     * contabilità semplificata.
     *
     * @param int[] $anni
     * @return array{0: int, 1: int} media e numero di esercizi
     */
    private function getRicaviMedi(Soggetto $soggetto, array $anni): array
    {
        $totale = 0.0;
        $esercizi = 0;
        foreach ($anni as $anno) {
            if ((new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno) === null) {
                continue;
            }
            $componenti = new ComponentiReddito($this->dataMapper);
            $dati = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'));
            if ($dati?->regimeFiscale === RegimeFiscale::semplificato) {
                $componenti->perCassa($soggetto, $anno, false);
            } else {
                $componenti->perCompetenza($soggetto, $anno);
            }
            foreach ($componenti->getPerVoceCee() as $voce => $importo) {
                if (preg_match('/^E\.A\.[1235](\.|$)/', (string) $voce) === 1) {
                    $totale += $importo;
                }
            }
            $esercizi++;
        }
        return [$esercizi > 0 ? (int) round($totale / $esercizi) : 0, $esercizi];
    }
}
