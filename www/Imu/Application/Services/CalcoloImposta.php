<?php

namespace Imu\Application\Services;

use Anagrafica\Application\Entities\DatiCatastali;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\CategoriaCatastale;
use Anagrafica\Application\Enumerations\TipoCatasto;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Enumerations\TitoloPossesso;
use Anagrafica\Application\Enumerations\UtilizzoImmobile;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\PossessoModel;
use Imu\Application\Entities\AgevolazioneImu;
use Imu\Application\Entities\AliquotaApplicata;
use Imu\Application\Entities\CaratteristicheImu;
use Imu\Application\Entities\RigoCalcoloImu;
use Imu\Application\Enumerations\AssimilazioneAbitazione;
use Imu\Application\Enumerations\FattispecieImu;
use Imu\Application\Models\AgevolazioneImuModel;
use Imu\Application\Models\AliquotaApplicataModel;
use Imu\Application\Models\CaratteristicheImuModel;
use Imu\Application\Models\ComuneEsenzioneTerreniModel;
use Imu\Application\Models\UtilizzoMistoEncModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Calcolo dell'IMU dovuta dal contribuente per un anno (art. 1, commi
 * 738-783, L. 160/2019) sugli immobili che possiede secondo l'Anagrafica.
 *
 * Per ogni possesso dell'anno l'immobile si divide in periodi con possesso
 * (titolo, quota, utilizzo), classamento, caratteristiche e agevolazioni
 * costanti; i mesi si attribuiscono con MesiPossesso. Per ogni periodo:
 * - soggetto passivo: proprietario e titolare di usufrutto, uso,
 *   abitazione, enfiteusi, superficie, locatario finanziario (comma 743);
 *   non il nudo proprietario;
 * - base imponibile (commi 745-746): rendita rivalutata del 5% per il
 *   moltiplicatore della categoria; reddito dominicale rivalutato del 25%
 *   per 135; valore venale delle aree fabbricabili; riduzione del 50% per
 *   interesse storico, inagibilità e comodato ai parenti (comma 747);
 * - fattispecie e aliquota (commi 748-755, AliquoteComune); esenzioni
 *   dell'abitazione principale e pertinenze (comma 740), dei fabbricati
 *   merce dal 2022 (comma 751), dei terreni dei coltivatori diretti e IAP
 *   (comma 758), del gruppo E e di quelle oggettive indicate (comma 759);
 * - riduzione dell'imposta al 75% per il canone concordato (comma 760) e
 *   per i pensionati esteri;
 * - detrazione di 200 euro per l'abitazione principale A/1, A/8, A/9,
 *   rapportata ai mesi e ripartita tra i possessori che vi dimorano in
 *   proporzione alle quote (comma 749), fino a concorrenza dell'imposta
 *   dell'abitazione e delle pertinenze;
 * - acconto: imposta del primo semestre con le aliquote e la detrazione
 *   dell'anno precedente (comma 762);
 * - terreni esenti per ubicazione (comma 758, lett. b e d: isole minori e
 *   circolare n. 9/1993, ComuneEsenzioneTerreni);
 * - fabbricati del gruppo D a valore contabile: costi per i coefficienti
 *   del decreto annuale (comma 746);
 * - enti non commerciali: immobili destinati alle attività del comma 759,
 *   lett. g), esenti o imponibili per la sola parte commerciale
 *   (UtilizzoMistoEnc, D.M. 200/2012).
 */
class CalcoloImposta
{

    private const CATEGORIE_LUSSO = [CategoriaCatastale::a1, CategoriaCatastale::a8, CategoriaCatastale::a9];
    private const CATEGORIE_PERTINENZA = [CategoriaCatastale::c2, CategoriaCatastale::c6, CategoriaCatastale::c7];

    private AliquoteComune $aliquote;
    private RisultatoCalcoloImu $risultato;
    private bool $enteNonCommerciale = false;
    private Soggetto $soggetto;
    private ClassamentoImu $classamento;
    /** @var array<int, \Imu\Application\Entities\UtilizzoMistoEnc> */
    private array $utilizziEnc = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public static function isSoggettoPassivo(TitoloPossesso $titolo): bool
    {
        return $titolo !== TitoloPossesso::nudaProprieta;
    }

    /**
     * Moltiplicatore della rendita rivalutata (comma 745).
     */
    public static function getMoltiplicatore(CategoriaCatastale $categoria): int
    {
        return match (true) {
            $categoria === CategoriaCatastale::c1 => 55,
            in_array($categoria, [CategoriaCatastale::a10, CategoriaCatastale::d5], true) => 80,
            $categoria->getGruppo() === 'D' => 65,
            ($categoria->getGruppo() === 'B') || in_array($categoria, [CategoriaCatastale::c3, CategoriaCatastale::c4, CategoriaCatastale::c5], true) => 140,
            default => 160,
        };
    }

    public function calcola(Soggetto $soggetto, int $anno): RisultatoCalcoloImu
    {
        $this->aliquote = new AliquoteComune($soggetto->studio, $this->dataMapper);
        $this->soggetto = $soggetto;
        $this->classamento = new ClassamentoImu($this->dataMapper);
        $this->risultato = new RisultatoCalcoloImu();
        if ($anno === 2020) {
            $this->risultato->avviso('2020: l\'acconto era pari alla metà di IMU e TASI 2019 (comma 762); l\'acconto qui calcolato con le aliquote 2019 è indicativo.');
        }
        $this->enteNonCommerciale = self::isEnteNonCommerciale($soggetto, $anno, $this->dataMapper);
        $this->utilizziEnc = $this->enteNonCommerciale ? (new UtilizzoMistoEncModel($this->dataMapper))->getPerImmobile($soggetto, $anno) : [];
        $periodi = $this->getPeriodi($soggetto, $anno);
        foreach ($periodi as &$periodo) {
            $this->classifica($periodo, $soggetto, $anno);
            $this->applicaEnteNonCommerciale($periodo);
        }
        unset($periodo);
        foreach ($periodi as &$periodo) {
            $this->risolviPertinenza($periodo, $periodi);
        }
        unset($periodo);
        $aliquoteApplicate = (new AliquotaApplicataModel($this->dataMapper))->getPerImmobile($soggetto, $anno);
        $aliquotePrecedenti = (new AliquotaApplicataModel($this->dataMapper))->getPerImmobile($soggetto, $anno - 1);
        foreach ($periodi as $periodo) {
            $this->risultato->righi[] = $this->creaRigo($periodo, $anno, $aliquoteApplicate[$periodo['immobile']->id] ?? null, $aliquotePrecedenti[$periodo['immobile']->id] ?? null);
        }
        $this->applicaDetrazioni($periodi, $soggetto, $anno);
        return $this->risultato;
    }

    /**
     * Periodi omogenei dei possessi del contribuente nell'anno, con i mesi
     * attribuiti.
     *
     * @return array<int, array> periodi con possesso, immobile, dal, al, mesi
     */
    private function getPeriodi(Soggetto $soggetto, int $anno): array
    {
        $inizio = new \DateTimeImmutable($anno . '-01-01');
        $fine = new \DateTimeImmutable($anno . '-12-31');
        $gruppi = [];
        foreach ((new PossessoModel($this->dataMapper))->getStoricoSoggetto($soggetto) as $possesso) {
            $dal = max($inizio, new \DateTimeImmutable($possesso->validoDal->format('Y-m-d')));
            $al = min($fine, $possesso->validoAl === null ? $fine : new \DateTimeImmutable($possesso->validoAl->format('Y-m-d')));
            if ($dal <= $al) {
                $gruppi[$possesso->immobile->id . '|' . $possesso->titolo->value][] = ['possesso' => $possesso, 'dal' => $dal, 'al' => $al];
            }
        }
        $periodi = [];
        foreach ($gruppi as $versioni) {
            $immobile = $versioni[0]['possesso']->immobile;
            $variazioni = $this->getDateVariazione($soggetto, $immobile);
            $segmenti = [];
            foreach ($versioni as ['possesso' => $possesso, 'dal' => $dal, 'al' => $al]) {
                foreach (self::dividi($dal, $al, $variazioni) as [$da, $a]) {
                    $segmenti[] = ['possesso' => $possesso, 'immobile' => $immobile, 'dal' => $da, 'al' => $a];
                }
            }
            usort($segmenti, fn(array $primo, array $secondo) => $primo['dal'] <=> $secondo['dal']);
            $mesi = MesiPossesso::attribuisci($anno, array_map(fn(array $segmento) => [$segmento['dal'], $segmento['al']], $segmenti));
            foreach ($segmenti as $indice => $segmento) {
                $segmento['mesi'] = count($mesi[$indice]);
                $segmento['mesiAcconto'] = count(array_filter($mesi[$indice], fn(int $mese) => $mese <= 6));
                if ($segmento['mesi'] > 0) {
                    $periodi[] = $segmento;
                }
            }
        }
        usort($periodi, fn(array $primo, array $secondo) => [$primo['immobile']->comune->codiceCatastale, $primo['immobile']->id, $primo['dal']] <=> [$secondo['immobile']->comune->codiceCatastale, $secondo['immobile']->id, $secondo['dal']]);
        return $periodi;
    }

    /**
     * Date da cui cambiano classamento (decorrenza IMU, ClassamentoImu),
     * caratteristiche o agevolazioni.
     *
     * @return string[] Y-m-d
     */
    private function getDateVariazione(Soggetto $soggetto, Immobile $immobile): array
    {
        $date = $this->classamento->getDateVariazione($immobile);
        $storici = [
            (new CaratteristicheImuModel($this->dataMapper))->getStorico($immobile),
            (new AgevolazioneImuModel($this->dataMapper))->getStorico($soggetto, $immobile),
        ];
        foreach ($storici as $storico) {
            foreach ($storico as $versione) {
                $date[] = $versione->validoDal->format('Y-m-d');
                if ($versione->validoAl !== null) {
                    $date[] = (new \DateTimeImmutable($versione->validoAl->format('Y-m-d')))->modify('+1 day')->format('Y-m-d');
                }
            }
        }
        return array_unique($date);
    }

    /**
     * @param string[] $variazioni date di inizio di nuovi periodi
     * @return array<int, array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    private static function dividi(\DateTimeImmutable $dal, \DateTimeImmutable $al, array $variazioni): array
    {
        $tagli = array_filter($variazioni, fn(string $data) => ($data > $dal->format('Y-m-d')) && ($data <= $al->format('Y-m-d')));
        sort($tagli);
        $segmenti = [];
        $inizio = $dal;
        foreach ($tagli as $taglio) {
            $giorno = new \DateTimeImmutable($taglio);
            $segmenti[] = [$inizio, $giorno->modify('-1 day')];
            $inizio = $giorno;
        }
        $segmenti[] = [$inizio, $al];
        return $segmenti;
    }

    /**
     * Fattispecie del periodo (prima delle pertinenze), base imponibile e
     * riduzioni.
     */
    /**
     * Ente non commerciale secondo i dati fiscali dell'Anagrafica in vigore
     * alla fine dell'anno (o all'inizio, se cessato).
     */
    public static function isEnteNonCommerciale(Soggetto $soggetto, int $anno, DataMapper $dataMapper): bool
    {
        $datiFiscaliModel = new DatiFiscaliModel($dataMapper);
        $datiFiscali = $datiFiscaliModel->getVigenteAl($soggetto, new SismaDate($anno . '-12-31')) ?? $datiFiscaliModel->getVigenteAl($soggetto, new SismaDate($anno . '-01-01'));
        return $datiFiscali?->enteNonCommerciale === true;
    }

    private function classifica(array &$periodo, Soggetto $soggetto, int $anno): void
    {
        $immobile = $periodo['immobile'];
        $possesso = $periodo['possesso'];
        $data = new SismaDate($periodo['dal']->format('Y-m-d'));
        $caratteristiche = (new CaratteristicheImuModel($this->dataMapper))->getVigenteAl($immobile, $data);
        // fabbricati a valore contabile: la rendita vale solo dall'attribuzione, niente classamento presunto
        $classamento = $this->classamento->getVigenteAl($immobile, $data, $caratteristiche?->valoreContabile !== true);
        $datiCatastali = $classamento['datiCatastali'] ?? null;
        $agevolazione = (new AgevolazioneImuModel($this->dataMapper))->getVigenteAl($soggetto, $immobile, $data);
        $periodo += ['datiCatastali' => $datiCatastali, 'caratteristiche' => $caratteristiche, 'agevolazione' => $agevolazione,
            'fattispecie' => null, 'esenzione' => null, 'base' => 0.0, 'riduzioneBase' => 1.0, 'riduzioneImposta' => 1.0, 'note' => [],
            'abitazionePrincipale' => false, 'pertinenza' => false];
        $this->risultato->comuni[$immobile->comune->codiceCatastale] = $immobile->comune->denominazione;
        $descrizione = $immobile->getIdentificativo() . ' (' . $immobile->comune->denominazione . ')';
        if ($classamento['presunto'] ?? false) {
            $periodo['note'][] = 'Classamento presunto: dati catastali in vigore dal ' . $datiCatastali->validoDal->format('d/m/Y');
            $this->risultato->avviso($descrizione . ': dati catastali in vigore dal ' . $datiCatastali->validoDal->format('d/m/Y') . ', applicati anche al possesso precedente'
                    . ' (classamento presunto): verificare la visura storica e, se il classamento era diverso, inserirlo in Anagrafica.');
        }
        $this->segnalaVariazioniDifferite($periodo, $immobile, $descrizione);
        if (self::isSoggettoPassivo($possesso->titolo) === false) {
            $periodo['esenzione'] = 'Nuda proprietà: l\'imposta è dovuta dal titolare del diritto di godimento (comma 743)';
            return;
        }
        if (($caratteristiche?->valoreContabile === true) && ($caratteristiche->esenzione === null)) {
            $this->classificaContabile($periodo, $caratteristiche, $datiCatastali, $anno, $descrizione);
            return;
        }
        if ($datiCatastali === null) {
            $periodo['esenzione'] = 'Dati catastali mancanti al ' . $data->format('d/m/Y');
            $this->risultato->avviso($descrizione . ': dati catastali mancanti al ' . $data->format('d/m/Y') . ', immobile non calcolato.');
            return;
        }
        if ($caratteristiche?->esenzione !== null) {
            $periodo['esenzione'] = $caratteristiche->esenzione->getLabel() . ' (' . $caratteristiche->esenzione->getRiferimento() . ')';
            return;
        }
        if ($caratteristiche?->areaFabbricabile === true) {
            $this->classificaArea($periodo, $caratteristiche, $agevolazione, $descrizione);
            return;
        }
        if ($immobile->tipoCatasto === TipoCatasto::terreni) {
            $this->classificaTerreno($periodo, $datiCatastali, $agevolazione, $descrizione);
            return;
        }
        $this->classificaFabbricato($periodo, $datiCatastali, $caratteristiche, $agevolazione, $anno, $descrizione);
    }

    /**
     * Variazioni catastali con decorrenza nel periodo non ancora efficaci per
     * l'IMU (non dovute a interventi edilizi: valgono dal 1° gennaio
     * successivo, comma 745).
     */
    private function segnalaVariazioniDifferite(array &$periodo, Immobile $immobile, string $descrizione): void
    {
        $dal = $periodo['dal']->format('Y-m-d');
        $al = $periodo['al']->format('Y-m-d');
        foreach ((new DatiCatastaliModel($this->dataMapper))->getStorico($immobile) as $variazione) {
            $decorrenza = $variazione->validoDal->format('Y-m-d');
            if (($decorrenza < $dal) || ($decorrenza > $al) || (ClassamentoImu::getDecorrenza($variazione) === $decorrenza)) {
                continue;
            }
            $efficace = (new \DateTimeImmutable(ClassamentoImu::getDecorrenza($variazione)))->format('d/m/Y');
            $periodo['note'][] = 'Variazione catastale del ' . $variazione->validoDal->format('d/m/Y') . ' efficace dal ' . $efficace . ' (comma 745)';
            $this->risultato->avviso($descrizione . ': la variazione catastale del ' . $variazione->validoDal->format('d/m/Y') . ' non è dovuta a interventi edilizi, per l\'IMU vale dal ' . $efficace
                    . ' (comma 745); se lo è, indicarne la causale in Anagrafica.');
        }
    }

    private function classificaArea(array &$periodo, CaratteristicheImu $caratteristiche, ?AgevolazioneImu $agevolazione, string $descrizione): void
    {
        if ($agevolazione?->conduzioneAgricola === true) {
            $periodo['esenzione'] = 'Area posseduta e condotta da coltivatore diretto o IAP: terreno agricolo esente (commi 741, lett. d, e 758, lett. a)';
            return;
        }
        $periodo['fattispecie'] = FattispecieImu::areeFabbricabili;
        $periodo['base'] = (float) ($caratteristiche->valoreVenale ?? 0);
        if ($caratteristiche->valoreVenale === null) {
            $this->risultato->avviso($descrizione . ': area fabbricabile senza valore venale, imposta zero.');
        }
    }

    private function classificaTerreno(array &$periodo, DatiCatastali $datiCatastali, ?AgevolazioneImu $agevolazione, string $descrizione): void
    {
        if ($agevolazione?->conduzioneAgricola === true) {
            $periodo['esenzione'] = 'Terreno posseduto e condotto da coltivatore diretto o IAP (comma 758, lett. a)';
            return;
        }
        foreach ((new ComuneEsenzioneTerreniModel($this->dataMapper))->getPerComune($periodo['immobile']->comune->codiceCatastale) as $esenzione) {
            if ($esenzione->parziale === false) {
                $periodo['esenzione'] = $esenzione->motivo->getLabel() . ' (' . $esenzione->motivo->getRiferimento() . ')';
                return;
            }
            $this->risultato->avviso($descrizione . ': il comune è delimitato solo in parte (' . mb_strtolower($esenzione->motivo->getLabel()) . '); se il terreno ricade nella zona esente indicare l\'esenzione nelle caratteristiche IMU.');
        }
        $periodo['fattispecie'] = FattispecieImu::terreniAgricoli;
        $periodo['base'] = ValoreImmobile::terreno($datiCatastali);
        if ($datiCatastali->redditoDominicale === null) {
            $this->risultato->avviso($descrizione . ': reddito dominicale mancante, imposta zero.');
        }
    }

    /**
     * Fabbricato del gruppo D senza rendita, interamente posseduto da
     * un'impresa e distintamente contabilizzato (comma 746): valore al 1°
     * gennaio (o all'acquisto, se nell'anno) pari ai costi per i coefficienti
     * del decreto dell'anno; il coefficiente del 1982 vale anche per i costi
     * precedenti.
     */
    private function classificaContabile(array &$periodo, CaratteristicheImu $caratteristiche, ?DatiCatastali $datiCatastali, int $anno, string $descrizione): void
    {
        $periodo['fattispecie'] = ($caratteristiche->ruraleStrumentale || ($datiCatastali?->categoria === CategoriaCatastale::d10)) ? FattispecieImu::ruraliStrumentali : FattispecieImu::gruppoD;
        $contabile = (new ValoreImmobile($this->dataMapper))->contabile($periodo['immobile'], $anno, $periodo['possesso']->validoDal->format('Y') === (string) $anno);
        $annoDecreto = $contabile['annoDecreto'];
        $costi = $contabile['costi'];
        if ($annoDecreto === null) {
            $this->risultato->avviso($descrizione . ': coefficienti dei fabbricati D per il ' . $anno . ' non in archivio (sisma tabelle-imu), valore pari ai costi.');
        } elseif ($annoDecreto !== $anno) {
            $this->risultato->avviso($descrizione . ': coefficienti dei fabbricati D ' . $anno . ' non ancora in archivio, applicati quelli del ' . $annoDecreto . '.');
        }
        $valore = $contabile['valore'];
        $periodo['base'] = round($valore, 2);
        $periodo['note'][] = 'Valore contabile (comma 746): ' . $costi . ' costi' . ($annoDecreto !== null ? ' rivalutati con i coefficienti del ' . $annoDecreto : '');
        if ($costi === 0) {
            $this->risultato->avviso($descrizione . ': fabbricato a valore contabile senza costi registrati, imposta zero.');
        }
        $this->applicaRiduzioni($periodo, $datiCatastali?->categoria ?? CategoriaCatastale::d1, $caratteristiche, $periodo['agevolazione'], $anno, $descrizione);
    }

    /**
     * Immobile di un ente non commerciale destinato alle attività del comma
     * 759, lett. g): esente se utilizzato solo con modalità non
     * commerciali, altrimenti imponibile per la parte commerciale (D.M.
     * 200/2012).
     */
    private function applicaEnteNonCommerciale(array &$periodo): void
    {
        $utilizzo = $this->utilizziEnc[$periodo['immobile']->id] ?? null;
        if (($this->enteNonCommerciale === false) || ($utilizzo === null) || ($periodo['fattispecie'] === null) || ($periodo['esenzione'] !== null)) {
            return;
        }
        if ($utilizzo->esente) {
            $periodo['esenzione'] = 'Immobile di ente non commerciale destinato ad attività non commerciali (comma 759, lett. g)';
            return;
        }
        if ($utilizzo->isInteramenteImponibile()) {
            return;
        }
        $coefficiente = $utilizzo->getCoefficienteImponibile();
        $periodo['riduzioneBase'] *= $coefficiente;
        $periodo['note'][] = 'Ente non commerciale, utilizzo misto: imponibile il ' . number_format($coefficiente * 100, 2, ',', '') . '% (D.M. 200/2012)';
    }

    private function classificaFabbricato(array &$periodo, DatiCatastali $datiCatastali, ?CaratteristicheImu $caratteristiche, ?AgevolazioneImu $agevolazione, int $anno, string $descrizione): void
    {
        $categoria = $datiCatastali->categoria;
        $possesso = $periodo['possesso'];
        if (($categoria === null) || ($categoria->haRendita() === false) || ($datiCatastali->rendita === null)) {
            $periodo['esenzione'] = 'Fabbricato senza rendita' . ($categoria !== null ? ' (' . $categoria->value . ')' : '') . ': imponibile solo come area fabbricabile';
            if (in_array($categoria, [CategoriaCatastale::f3, CategoriaCatastale::f4], true) || ($categoria === null)) {
                $this->risultato->avviso($descrizione . ': unità senza rendita; se in costruzione indicare l\'area fabbricabile con il valore venale (comma 746).');
            }
            return;
        }
        if ($categoria->getGruppo() === 'E') {
            $periodo['esenzione'] = 'Categoria ' . $categoria->value . ' (comma 759, lett. b)';
            return;
        }
        if ($caratteristiche?->beneMerce === true) {
            if ($anno >= 2022) {
                $periodo['esenzione'] = 'Fabbricato merce, esente dal 2022 (comma 751)';
                return;
            }
            $periodo['fattispecie'] = FattispecieImu::beniMerce;
        }
        $periodo['base'] = ValoreImmobile::fabbricato($datiCatastali);
        $assimilazione = $agevolazione?->assimilazione;
        if (($assimilazione === AssimilazioneAbitazione::anzianiDisabili) && ($this->aliquote->isAssimilazioneAnziani($periodo['immobile']->comune->codiceCatastale, $anno) === false)) {
            $this->risultato->avviso($descrizione . ': il comune non prevede l\'assimilazione per anziani e disabili ricoverati, immobile trattato secondo l\'utilizzo.');
            $assimilazione = null;
        }
        if ($periodo['fattispecie'] === null) {
            if ($caratteristiche?->ruraleStrumentale === true || $categoria === CategoriaCatastale::d10) {
                $periodo['fattispecie'] = FattispecieImu::ruraliStrumentali;
            } elseif (($possesso->utilizzo === UtilizzoImmobile::abitazionePrincipale) || ($assimilazione !== null)) {
                if ($categoria->isAbitativa()) {
                    $periodo['abitazionePrincipale'] = true;
                    $this->classificaAbitazione($periodo, $categoria, $assimilazione);
                } else {
                    $this->risultato->avviso($descrizione . ': abitazione principale su categoria ' . $categoria->value . ' non abitativa, trattato come altro fabbricato.');
                    $periodo['fattispecie'] = $categoria->getGruppo() === 'D' ? FattispecieImu::gruppoD : FattispecieImu::altriFabbricati;
                }
            } elseif ($possesso->utilizzo === UtilizzoImmobile::pertinenzaAbitazionePrincipale) {
                $periodo['pertinenza'] = true;
            } else {
                $periodo['fattispecie'] = $categoria->getGruppo() === 'D' ? FattispecieImu::gruppoD : FattispecieImu::altriFabbricati;
            }
        }
        $this->applicaRiduzioni($periodo, $categoria, $caratteristiche, $agevolazione, $anno, $descrizione);
    }

    private function classificaAbitazione(array &$periodo, CategoriaCatastale $categoria, ?AssimilazioneAbitazione $assimilazione): void
    {
        if (in_array($categoria, self::CATEGORIE_LUSSO, true)) {
            $periodo['fattispecie'] = FattispecieImu::abitazionePrincipale;
            if ($assimilazione !== null) {
                $periodo['note'][] = 'Assimilata: ' . $assimilazione->getRiferimento();
            }
            return;
        }
        $periodo['esenzione'] = $assimilazione === null ? 'Abitazione principale (comma 740)' : 'Assimilata all\'abitazione principale (' . $assimilazione->getRiferimento() . ')';
    }

    private function applicaRiduzioni(array &$periodo, CategoriaCatastale $categoria, ?CaratteristicheImu $caratteristiche, ?AgevolazioneImu $agevolazione, int $anno, string $descrizione): void
    {
        if ($caratteristiche?->storicoArtistico === true) {
            $periodo['riduzioneBase'] *= 0.5;
            $periodo['note'][] = 'Interesse storico o artistico: base al 50% (comma 747, lett. a)';
        }
        if ($caratteristiche?->inagibile === true) {
            $periodo['riduzioneBase'] *= 0.5;
            $periodo['note'][] = 'Inagibile o inabitabile: base al 50% (comma 747, lett. b)';
        }
        if ($agevolazione?->comodatoParenti === true) {
            if (in_array($categoria, self::CATEGORIE_LUSSO, true) || (($categoria->isAbitativa() === false) && (in_array($categoria, self::CATEGORIE_PERTINENZA, true) === false))) {
                $this->risultato->avviso($descrizione . ': la riduzione per comodato non spetta alla categoria ' . $categoria->value . ' (comma 747, lett. c).');
            } else {
                $periodo['riduzioneBase'] *= 0.5;
                $periodo['note'][] = 'Comodato a parenti in linea retta: base al 50% (comma 747, lett. c)';
            }
        }
        if ($periodo['possesso']->utilizzo === UtilizzoImmobile::locatoCanoneConcordato) {
            $periodo['riduzioneImposta'] *= 0.75;
            $periodo['note'][] = 'Locato a canone concordato: imposta al 75% (comma 760)';
        }
        if (($agevolazione?->pensionatoEstero === true) && ($anno >= 2021)) {
            $coefficiente = $anno === 2022 ? 0.375 : 0.5;
            $periodo['riduzioneImposta'] *= $coefficiente;
            $periodo['note'][] = 'Pensionato residente all\'estero: imposta al ' . ($coefficiente * 100) . '% (L. 178/2020, c. 48' . ($anno === 2022 ? '; L. 234/2021, c. 743' : '') . ')';
        }
    }

    /**
     * Pertinenza dell'abitazione principale (comma 741, lett. b): solo C/2,
     * C/6 e C/7, al massimo una per categoria; segue l'abitazione dello
     * stesso comune (esente, o fattispecie A/1, A/8, A/9).
     */
    private function risolviPertinenza(array &$periodo, array $periodi): void
    {
        if ($periodo['pertinenza'] === false) {
            return;
        }
        $immobile = $periodo['immobile'];
        $categoria = $periodo['datiCatastali']->categoria;
        $descrizione = $immobile->getIdentificativo() . ' (' . $immobile->comune->denominazione . ')';
        $altroFabbricato = $categoria->getGruppo() === 'D' ? FattispecieImu::gruppoD : FattispecieImu::altriFabbricati;
        if (in_array($categoria, self::CATEGORIE_PERTINENZA, true) === false) {
            $this->risultato->avviso($descrizione . ': la categoria ' . $categoria->value . ' non può essere pertinenza dell\'abitazione principale, trattato come altro fabbricato.');
            $periodo['fattispecie'] = $altroFabbricato;
            return;
        }
        $abitazione = null;
        foreach ($periodi as $altro) {
            if ($altro['abitazionePrincipale'] && self::sovrapposti($altro, $periodo) && ($altro['immobile']->comune->codiceCatastale === $immobile->comune->codiceCatastale)) {
                $abitazione = $altro;
            }
            if ($altro['pertinenza'] && ($altro['immobile']->id !== $immobile->id) && self::sovrapposti($altro, $periodo) && ($altro['datiCatastali']->categoria === $categoria)
                    && (($altro['datiCatastali']->rendita > $periodo['datiCatastali']->rendita) || (($altro['datiCatastali']->rendita == $periodo['datiCatastali']->rendita) && ($altro['immobile']->id < $immobile->id)))) {
                $this->risultato->avviso($descrizione . ': oltre una pertinenza di categoria ' . $categoria->value . ' (la pertinenza è quella con la rendita maggiore), trattato come altro fabbricato.');
                $periodo['fattispecie'] = $altroFabbricato;
                return;
            }
        }
        if ($abitazione === null) {
            $this->risultato->avviso($descrizione . ': pertinenza senza abitazione principale del contribuente nello stesso comune, trattato come altro fabbricato.');
            $periodo['fattispecie'] = $altroFabbricato;
            return;
        }
        if ($abitazione['fattispecie'] === FattispecieImu::abitazionePrincipale) {
            $periodo['fattispecie'] = FattispecieImu::abitazionePrincipale;
            $periodo['note'][] = 'Pertinenza dell\'abitazione ' . $abitazione['immobile']->getIdentificativo();
        } else {
            $periodo['esenzione'] = 'Pertinenza dell\'abitazione principale (comma 740)';
        }
    }

    private static function sovrapposti(array $primo, array $secondo): bool
    {
        return ($primo['dal'] <= $secondo['al']) && ($secondo['dal'] <= $primo['al']);
    }

    private function creaRigo(array $periodo, int $anno, ?AliquotaApplicata $scelta, ?AliquotaApplicata $sceltaPrecedente): RigoCalcoloImu
    {
        $immobile = $periodo['immobile'];
        $possesso = $periodo['possesso'];
        $rigo = new RigoCalcoloImu($this->dataMapper);
        $rigo->immobile = $immobile;
        $rigo->dal = new SismaDate($periodo['dal']->format('Y-m-d'));
        $rigo->al = new SismaDate($periodo['al']->format('Y-m-d'));
        $rigo->mesi = $periodo['mesi'];
        $rigo->mesiAcconto = $periodo['mesiAcconto'];
        $rigo->titolo = $possesso->titolo;
        $rigo->quota = $possesso->quota;
        $rigo->utilizzo = $possesso->utilizzo;
        $rigo->categoria = $periodo['datiCatastali']?->categoria;
        $rigo->codiceComune = $immobile->comune->codiceCatastale;
        $rigo->fattispecie = $periodo['esenzione'] === null ? $periodo['fattispecie'] : null;
        $rigo->esenzione = $periodo['esenzione'] === null ? null : mb_substr($periodo['esenzione'], 0, 150);
        $rigo->baseImponibile = $periodo['base'];
        $rigo->riduzioneBase = $periodo['riduzioneBase'];
        $rigo->riduzioneImposta = $periodo['riduzioneImposta'];
        if ($rigo->fattispecie !== null) {
            $this->calcolaImposta($rigo, $anno, $scelta, $sceltaPrecedente, $periodo['caratteristiche']?->exComune, $periodo['caratteristiche']?->inagibile === true);
        }
        $rigo->note = count($periodo['note']) > 0 ? mb_substr(implode('; ', $periodo['note']), 0, 255) : null;
        return $rigo;
    }

    private function calcolaImposta(RigoCalcoloImu $rigo, int $anno, ?AliquotaApplicata $scelta, ?AliquotaApplicata $sceltaPrecedente, ?string $exComune = null, bool $inagibile = false): void
    {
        $ente = $this->soggetto->tipoSoggetto === TipoSoggetto::ente;
        $comune = $rigo->codiceComune;
        $descrizione = $rigo->immobile->getIdentificativo() . ' (' . $this->risultato->comuni[$comune] . ')';
        $annua = $this->aliquote->getAliquota($comune, $anno, $rigo->fattispecie, $scelta, $sceltaPrecedente, $rigo->categoria, $rigo->utilizzo, $exComune, $ente, $inagibile);
        $acconto = $this->aliquote->getAliquota($comune, $anno - 1, $rigo->fattispecie, $sceltaPrecedente, $scelta, $rigo->categoria, $rigo->utilizzo, $exComune, $ente, $inagibile);
        $prospetto = $this->aliquote->getProspetto($comune, $anno, $exComune);
        if (($prospetto['prospetto'] === null) || ($prospetto['prospetto']->anno !== $anno)) {
            $this->risultato->avviso($this->risultato->comuni[$comune] . ' ' . $anno . ': ' . $prospetto['origine'] . '; se il comune ha pubblicato il prospetto ' . $anno . ' importalo, o inserisci le aliquote del comune.');
        } elseif ($prospetto['prospetto']->isPubblicato() && ($prospetto['prospetto']->dataPubblicazione !== null) && ($prospetto['prospetto']->dataPubblicazione->format('Y-m-d') > $anno . '-10-28')) {
            $this->risultato->avviso($this->risultato->comuni[$comune] . ' ' . $anno . ': prospetto pubblicato il ' . $prospetto['prospetto']->dataPubblicazione->format('d/m/Y') . ', dopo il 28 ottobre: verificare se è applicabile (comma 767).');
        }
        // l'acconto si calcola con le aliquote dell'anno precedente (comma 762): segnalarne il prospetto mancante (il 2020 ha il suo avviso)
        $prospettoPrecedente = $this->aliquote->getProspetto($comune, $anno - 1, $exComune);
        if (($anno > 2020) && (($prospettoPrecedente['prospetto'] === null) || ($prospettoPrecedente['prospetto']->anno !== $anno - 1))) {
            $this->risultato->avviso($this->risultato->comuni[$comune] . ' ' . $anno . ': acconto con le aliquote del ' . ($anno - 1) . ', ' . $prospettoPrecedente['origine']
                    . '; importa il prospetto ' . ($anno - 1) . ' o inserisci le aliquote del comune.');
        }
        if (($exComune === null) && $this->aliquote->hasExComuni($comune, $anno)) {
            $this->risultato->avviso($descrizione . ': il comune applica aliquote diverse negli ex comuni, indicare l\'ex comune nelle caratteristiche IMU.');
        }
        if ($annua['proposta']) {
            $this->risultato->avviso($descrizione . ': ' . $annua['origine'] . ', da confermare.');
        }
        foreach ($annua['suggerite'] as $suggerita) {
            $this->risultato->avviso($descrizione . ': possibile aliquota differenziata ' . $suggerita . '.');
        }
        $rigo->aliquota = $annua['aliquota'];
        $rigo->aliquotaAcconto = $acconto['aliquota'];
        $rigo->origineAliquota = mb_substr($annua['origine'], 0, 150);
        $base = $rigo->baseImponibile * $rigo->quota / 100 * $rigo->riduzioneBase * $rigo->riduzioneImposta;
        $rigo->impostaAnnua = round($base * $rigo->mesi / 12 * $rigo->aliquota / 100, 2);
        $rigo->impostaAcconto = round($base * $rigo->mesiAcconto / 12 * $rigo->aliquotaAcconto / 100, 2);
        if ($rigo->fattispecie === FattispecieImu::gruppoD) {
            $stato = FattispecieImu::ALIQUOTA_STATO_GRUPPO_D;
            if ($rigo->aliquota < $stato) {
                $this->risultato->avviso($descrizione . ': aliquota del gruppo D inferiore allo 0,76% riservato allo Stato (comma 753).');
            }
            $rigo->quotaStatoAnnua = round($base * $rigo->mesi / 12 * min($rigo->aliquota, $stato) / 100, 2);
            $rigo->quotaStatoAcconto = round($base * $rigo->mesiAcconto / 12 * min($rigo->aliquotaAcconto, $stato) / 100, 2);
        }
    }

    /**
     * Detrazione per l'abitazione principale A/1, A/8, A/9 (comma 749):
     * importo del comune rapportato ai mesi e alla quota del contribuente
     * rispetto a quelle dei possessori che vi dimorano, fino a concorrenza
     * dell'imposta dell'abitazione e delle pertinenze dello stesso comune.
     */
    private function applicaDetrazioni(array $periodi, Soggetto $soggetto, int $anno): void
    {
        $spettanti = [];
        foreach ($this->risultato->righi as $indice => $rigo) {
            if (($rigo->fattispecie !== FattispecieImu::abitazionePrincipale) || ($periodi[$indice]['abitazionePrincipale'] === false)) {
                continue;
            }
            $ripartizione = $this->getRipartizioneDetrazione($periodi[$indice], $soggetto);
            $comune = $rigo->codiceComune;
            $spettanti[$comune]['annua'] = ($spettanti[$comune]['annua'] ?? 0) + $this->aliquote->getDetrazione($comune, $anno) * $rigo->mesi / 12 * $ripartizione;
            $spettanti[$comune]['acconto'] = ($spettanti[$comune]['acconto'] ?? 0) + $this->aliquote->getDetrazione($comune, $anno - 1) * $rigo->mesiAcconto / 12 * $ripartizione;
        }
        foreach ($spettanti as $comune => ['annua' => $annua, 'acconto' => $acconto]) {
            $annua = round($annua, 2);
            $acconto = round($acconto, 2);
            $righi = array_filter($this->risultato->righi, fn(RigoCalcoloImu $rigo) => ($rigo->codiceComune === $comune) && ($rigo->fattispecie === FattispecieImu::abitazionePrincipale));
            uasort($righi, fn(RigoCalcoloImu $primo, RigoCalcoloImu $secondo) => ($primo->utilizzo === UtilizzoImmobile::pertinenzaAbitazionePrincipale) <=> ($secondo->utilizzo === UtilizzoImmobile::pertinenzaAbitazionePrincipale));
            foreach ($righi as $rigo) {
                $rigo->detrazioneAnnua = min($annua, $rigo->impostaAnnua);
                $rigo->detrazioneAcconto = min($acconto, $rigo->impostaAcconto);
                $annua = round($annua - $rigo->detrazioneAnnua, 2);
                $acconto = round($acconto - $rigo->detrazioneAcconto, 2);
            }
        }
    }

    private function getRipartizioneDetrazione(array $periodo, Soggetto $soggetto): float
    {
        $quote = 0.0;
        foreach ((new PossessoModel($this->dataMapper))->getVigentiAl($periodo['immobile'], $periodo['dal']) as $possesso) {
            if (self::isSoggettoPassivo($possesso->titolo) && (($possesso->utilizzo === UtilizzoImmobile::abitazionePrincipale) || ($possesso->soggetto->id === $soggetto->id))) {
                $quote += $possesso->quota;
            }
        }
        return $quote > 0 ? min(1.0, $periodo['possesso']->quota / $quote) : 1.0;
    }
}
