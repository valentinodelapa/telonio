<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoOpzioneTassazione;
use Anagrafica\Application\Models\LegameModel;
use Anagrafica\Application\Models\OpzioneTassazioneModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Contabilita\Application\Services\ComponentiReddito;
use Contabilita\Application\Services\PartiteAperte;
use Deleghe\Application\Services\DatiContribuente;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta dei quadri dei modelli Redditi SC e SP dalla contabilità
 * (istruzioni e specifiche tecniche 2026).
 *
 * Quadro RF (contabilità ordinaria, per competenza, ComponentiReddito):
 * utile o perdita (RF4, RF5), imposte sul reddito dell'esercizio (voce
 * E.20 del conto economico: IRES e IRAP) e altri costi indeducibili in
 * parte tra le variazioni in aumento (RF16, RF18, RF31 codice 99), parte
 * non imponibile dei ricavi tra quelle in diminuzione (RF55 codice 99),
 * totali RF32 e RF56 con le formule dei controlli delle specifiche,
 * somma algebrica RF57, reddito al lordo delle partecipazioni RF60 e
 * reddito o perdita RF63 (al netto delle erogazioni liberali RF61); per
 * Redditi SP anche il reddito RF66 al netto delle perdite RF64 e
 * dell'agevolazione RF65 (inserite a mano).
 *
 * Quadro RG di Redditi SP in contabilità semplificata (per cassa) e
 * quadri RN (redditi da imputare ai soci) e RO (amministratori e soci dai
 * legami dell'Anagrafica) di Redditi SP.
 *
 * Quadro RN di Redditi SC (determinazione dell'IRES): reddito (RN1) o
 * perdita (RN2), perdite pregresse nel rigo RN4 dai residui dell'anno
 * prima o dall'archivio delle perdite pregresse (RiportoPerdite; col. 1
 * in misura limitata all'80 per cento del reddito, col. 2 in misura
 * piena, totale col. 4), perdita residua RN5, reddito imponibile RN6
 * (col. 2 e 11), IRES al 24 per cento (RN8), imposta RN9 e netta RN11,
 * ritenute d'acconto subite dalle partite aperte (RN15), totale crediti e
 * ritenute RN16, IRES dovuta RN17, eccedenza della dichiarazione dell'anno
 * prima (RN19), acconti versati in Deleghe (codici 2001 e 2002, RN22) e
 * IRES a debito o a credito (RN23 col. 3, RN24) riportata nel rigo RX1.
 */
class PropostaRedditiSocieta
{

    public const QUADRI = ['RF', 'RG', 'RN', 'RO', 'RS', 'RX', 'GN', 'TN'];
    public const ALIQUOTA_IRES = 0.24;

    /** Righi delle variazioni in aumento e in diminuzione sommati nei totali RF32 e RF56 (controlli delle specifiche) */
    private const AUMENTI = [
        'RSC' => ['RF007002', 'RF008001', 'RF009001', 'RF010001', 'RF011001', 'RF012002', 'RF013001', 'RF014001', 'RF015002', 'RF016001', 'RF017002', 'RF018001',
            'RF019002', 'RF020001', 'RF021003', 'RF022004', 'RF023003', 'RF024001', 'RF025003', 'RF026003', 'RF027001', 'RF030003', 'RF031055'],
        'RSP' => ['RF007002', 'RF008001', 'RF009001', 'RF010001', 'RF011001', 'RF012002', 'RF013001', 'RF014001', 'RF015002', 'RF016001', 'RF017002', 'RF018001',
            'RF019002', 'RF020001', 'RF021003', 'RF023003', 'RF024001', 'RF025003', 'RF027001', 'RF030003', 'RF031055'],
        'ENC' => ['RF007002', 'RF008001', 'RF010001', 'RF011001', 'RF012002', 'RF013001', 'RF014001', 'RF015002', 'RF016001', 'RF017002', 'RF018001',
            'RF019002', 'RF020001', 'RF021003', 'RF023003', 'RF024001', 'RF025003', 'RF027001', 'RF031055'],
    ];
    private const DIMINUZIONI = [
        'RSC' => ['RF034002', 'RF036001', 'RF037001', 'RF038001', 'RF039001', 'RF040001', 'RF041001', 'RF042004', 'RF043003', 'RF044001', 'RF046002', 'RF047003',
            'RF048001', 'RF050010', 'RF053003', 'RF054001', 'RF055055'],
        // il controllo delle specifiche SP cita RF048002, che non è nel modello: il rigo RF48 ha la sola colonna 1
        'RSP' => ['RF034002', 'RF036001', 'RF037001', 'RF039001', 'RF040001', 'RF043003', 'RF044001', 'RF046002', 'RF047002', 'RF048001', 'RF050008', 'RF053002',
            'RF054001', 'RF055055'],
        'ENC' => ['RF034002', 'RF036001', 'RF039001', 'RF040001', 'RF043003', 'RF044001', 'RF046002', 'RF047002', 'RF048001', 'RF050007', 'RF055055'],
    ];
    /** Colonne degli importi sommate nei totali delle altre variazioni (RF31 col. 55, RF55 col. 55): Redditi SC salta la coppia 43-44 */
    private const ALTRE = [
        'RSC' => [2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40, 42, 46, 48, 50, 52, 54],
        'RSP' => [2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54],
        'ENC' => [2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36, 38, 40, 42, 46, 48, 50, 52, 54],
    ];

    /** Codici tributo degli acconti IRES versati per l'anno */
    public const ACCONTI_IRES = ['2001', '2002'];

    /** @var string[] */
    private array $avvisi = [];

    /** @var array{limitata: int, piena: int, fonte: string}|null perdite residue alla fine dell'anno prima (RiportoPerdite) */
    private ?array $residui = null;

    /** Primi tre periodi d'imposta dall'inizio dell'attività: perdite in misura piena (art. 84, c. 2, Tuir) */
    private bool $primiTreAnni = false;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[] avvisi
     */
    public function proponi(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, bool $salva = true): array
    {
        $this->avvisi = [];
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $quadri->usaModulo(1)->avviaProposta(...self::QUADRI);
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $dati = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'));
        $modello = $dichiarazione->modello->value;
        if ($dati === null) {
            $this->avvisi[] = 'Dati fiscali dell\'anno non trovati in Anagrafica: quadri del reddito d\'impresa da compilare a mano.';
        } elseif (($dati->regimeFiscale === RegimeFiscale::ordinario) || ($dichiarazione->modello === ModelloDichiarativo::redditiSc)) {
            $this->proponiRf($quadri, $dati, $soggetto, $anno, $modello);
        } elseif ($dati->regimeFiscale === RegimeFiscale::semplificato) {
            $this->proponiRg($quadri, $dati, $soggetto, $anno);
        } else {
            $this->avvisi[] = 'Regime ' . ($dati->regimeFiscale?->getLabel() ?? 'non indicato') . ': quadro del reddito d\'impresa (RG o RE) da compilare a mano.';
        }
        if ($dati !== null) {
            array_push($this->avvisi, ...(new OperativitaSocieta($this->dataMapper))->proponi($quadri, $dichiarazione));
        }
        // conservazione dei documenti rilevanti ai fini tributari (RS40 di SP, RS104 di SC), obbligatoria per i soggetti IVA: 1 se nell'anno è
        // stato conservato in modalità elettronica almeno un documento (fatture elettroniche), 2 altrimenti; come il rigo RS140 di Redditi PF
        if ($dati?->partitaIva !== null) {
            $rigo = $dichiarazione->modello === ModelloDichiarativo::redditiSc ? 'RS104' : 'RS040';
            $quadri->proponi($rigo . '001', 1);
            if ($quadri->getImporto($rigo . '001') === 1) {
                $this->avvisi[] = sprintf('Quadro RS, rigo %s: proposto il codice 1 (documenti rilevanti conservati in modalità elettronica, ad esempio le fatture elettroniche); indica 2 se nell\'anno non è stato conservato elettronicamente alcun documento.', str_replace('RS0', 'RS', $rigo));
            }
        }
        $this->residui = $dichiarazione->modello === ModelloDichiarativo::redditiSc ? (new RiportoPerdite($this->dataMapper))->getResidui($soggetto, $anno, $dichiarazione->modello) : null;
        $this->primiTreAnni = ($dati?->dataInizioAttivita !== null) && ($anno - (int) $dati->dataInizioAttivita->format('Y') <= 2);
        $opzione = $dichiarazione->modello === ModelloDichiarativo::redditiSc ? (new OpzioneTassazioneModel($this->dataMapper))->getVigente($soggetto, $anno) : null;
        if ($opzione?->tipoOpzione === TipoOpzioneTassazione::consolidatoNazionale) {
            $this->proponiConsolidato($quadri, $opzione);
        } elseif ($opzione !== null) {
            $this->proponiTrasparenza($quadri, $opzione);
        } elseif ($dichiarazione->modello === ModelloDichiarativo::redditiSc) {
            $this->proponiIres($quadri, $dichiarazione, $dati?->soggettoIsa ?? false);
        } else {
            $this->proponiImputazione($quadri, $soggetto, $anno);
            $this->proponiSoci($quadri, $soggetto, $anno);
        }
        // quadro RU, crediti d'imposta delle imprese dagli archivi di Deleghe
        array_push($this->avvisi, ...(new CreditiImpostaRu($this->dataMapper))->proponi($quadri, $dichiarazione));
        array_push($this->avvisi, ...$quadri->getDifferenzeManuali());
        if ($salva) {
            $quadri->salva();
            $dichiarazione->dataElaborazione = new SismaDateTime();
            $dichiarazione->avvisi = count($this->avvisi) > 0 ? implode("\n", array_unique($this->avvisi)) : null;
            $this->dataMapper->save($dichiarazione);
        }
        return array_values(array_unique($this->avvisi));
    }

    /**
     * Avvisi dell'ultima proposta (anche del solo quadro RF, proponiRf).
     *
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return array_values(array_unique($this->avvisi));
    }

    /**
     * Quadro RF dei modelli Redditi SC, SP ed ENC ("RSC", "RSP", "ENC"):
     * per Redditi ENC con i soli conti dell'attività commerciale e la quota
     * dei costi promiscui (ComponentiReddito), perdite pregresse RF64 (a
     * mano) e reddito RF65 da riportare nel quadro RN.
     */
    public function proponiRf(QuadriDichiarazione $quadri, DatiFiscali $dati, Soggetto $soggetto, int $anno, string $modello): void
    {
        $componenti = new ComponentiReddito($this->dataMapper);
        $competenza = $componenti->perCompetenza($soggetto, $anno);
        $utile = (int) round($competenza['utile']);
        $variazioni = array_map(fn(float $importo) => (int) round($importo), $competenza['variazioni'] ?? []);
        // imposte sul reddito dell'esercizio (voce E.20): indeducibili (art. 99 Tuir); la deduzione dell'IRAP si indica a mano nel rigo RF55
        $imposte = (int) round($componenti->getPerVoceCee()['E.20'] ?? 0.0);
        // Redditi SC, rigo RS107: ricavi dell'art. 85, comma 1, lett. a) e b), Tuir (voce A1), o la casella dell'assenza di ricavi
        if ($modello === ModelloDichiarativo::redditiSc->value) {
            $ricavi = (int) round(array_sum(array_filter($componenti->getPerVoceCee(), fn(string $voce) => ($voce === 'E.A.1') || str_starts_with($voce, 'E.A.1.'), ARRAY_FILTER_USE_KEY)));
            $quadri->proponi('RS107002', $ricavi > 0 ? $ricavi : null);
            $quadri->proponi('RS107001', $ricavi > 0 ? null : 1);
        }
        $quadri->proponi('RF001001', $dati->codiceAteco?->codice);
        $quadri->proponi('RF004001', $utile > 0 ? $utile : null);
        $quadri->proponi('RF005001', $utile < 0 ? -$utile : null);
        $quadri->proponi('RF016001', (($variazioni['RF16'] ?? 0) + $imposte) ?: null);
        $quadri->proponi('RF018001', ($variazioni['RF18'] ?? 0) ?: null);
        $this->proponiAltraVariazione($quadri, 'RF031', $variazioni['RF31:99'] ?? 0);
        $this->proponiAltraVariazione($quadri, 'RF055', $variazioni['RF55:99'] ?? 0);
        foreach (['RF031', 'RF055'] as $rigo) {
            $totale = array_sum(array_map(fn(int $colonna) => $quadri->getImporto(sprintf('%s%03d', $rigo, $colonna)), self::ALTRE[$modello]));
            $quadri->proponi($rigo . '055', $totale ?: null);
        }
        $aumento = array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), self::AUMENTI[$modello]));
        $quadri->proponi('RF032001', $aumento ?: null);
        $diminuzione = array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), self::DIMINUZIONI[$modello]));
        $quadri->proponi('RF056001', $diminuzione ?: null);
        $somma = $quadri->getImporto('RF004001') - $quadri->getImporto('RF005001') + $quadri->getImporto('RF006004') + $aumento - $diminuzione;
        $quadri->proponi('RF057001', $somma ?: null);
        if ($modello === 'RSC') {
            $this->proponiPartecipazioniTrasparenti($quadri, $soggetto, $anno);
        }
        // reddito con le partecipazioni (RF58 e RF59, colonna 1 a mano): colonna 4 e 3 in SC, colonna 2 in SP
        $lordo = $modello === 'RSC' ? $somma + $quadri->getImporto('RF058004') - $quadri->getImporto('RF059003')
                : $somma + $quadri->getImporto('RF058002') - $quadri->getImporto('RF059002');
        $quadri->proponi('RF060002', $lordo ?: null);
        $reddito = $lordo - $quadri->getImporto('RF061001');
        if ($modello !== 'RSC') {
            // proventi esenti: riducono la perdita (RF63 = RF60 - RF61 + RF62, controlli delle specifiche SP ed ENC)
            $reddito += $reddito < 0 ? $quadri->getImporto('RF062001') : 0;
        }
        $quadri->proponi('RF063001', $reddito ?: null);
        if ($modello === 'ENC') {
            // perdite pregresse (colonne 1 e 2 e "di periodo" a mano), totale colonna 3 e reddito RF65 da riportare nel rigo RN6
            $perdite = $reddito > 0 ? min($reddito, $quadri->getImporto('RF064001') + $quadri->getImporto('RF064002') + $quadri->getImporto('RF0640A1') + $quadri->getImporto('RF0640A2')) : 0;
            $quadri->proponi('RF064003', $perdite ?: null);
            // campo positivo: in caso di perdita resta vuoto (la perdita va nel prospetto delle perdite del quadro RS)
            $quadri->proponi('RF065001', $reddito - $perdite > 0 ? $reddito - $perdite : null);
        }
        if ($modello === 'RSP') {
            $perdite = $reddito > 0 ? min($reddito, $quadri->getImporto('RF064001') + $quadri->getImporto('RF064002') + $quadri->getImporto('RF064003')) : 0;
            $quadri->proponi('RF064004', $perdite ?: null);
            $netto = $reddito > 0 ? $reddito - $perdite - min($quadri->getImporto('RF065001'), $reddito - $perdite) : $reddito;
            $quadri->proponi('RF066001', $netto ?: null);
        }
        if ($imposte > 0) {
            $this->avvisi[] = sprintf('Quadro RF: imposte sul reddito dell\'esercizio (voce E.20, %s euro) tra le variazioni in aumento del rigo RF16; la quota deducibile dell\'IRAP va indicata a mano nel rigo RF55 (codici 12 e 33).', number_format($imposte, 0, ',', '.'));
        }
        $this->avvisi[] = 'Quadro RF: utile del conto economico proposto (' . number_format($utile, 0, ',', '.') . ' euro) con le variazioni dei conti deducibili o imponibili in parte; indica le altre variazioni in aumento (righi da RF6 a RF31) e in diminuzione (righi da RF34 a RF55): i totali si ricalcolano alla riproposta.';
    }

    /**
     * Quadro RG di Redditi SP (contabilità semplificata, per cassa, come
     * Redditi PF): componenti positivi e negativi per rigo dei conti,
     * reddito RG28 e RG31, perdite RG32 (a mano) e reddito RG34.
     */
    private function proponiRg(QuadriDichiarazione $quadri, DatiFiscali $dati, Soggetto $soggetto, int $anno): void
    {
        $componenti = (new ComponentiReddito($this->dataMapper))->perCassa($soggetto, $anno, false);
        $importo = fn(string $rigo) => (int) round($componenti[$rigo] ?? 0.0);
        $impresa = new PropostaImpresa($this->dataMapper);
        $quadri->proponi('RG001001', $dati->codiceAteco?->codice);
        // in Redditi SP il totale dei ricavi è nella colonna 5 del rigo RG2 (le colonne 1-4 sono "di cui")
        $quadri->proponi('RG002005', $importo('RG2') ?: null);
        $quadri->proponi('RG003001', $importo('RG3') ?: null);
        $quadri->proponi('RG006002', $importo('RG6') ?: null);
        $quadri->proponi('RG007001', $importo('RG7') ?: null);
        $positivi = $impresa->proponiCodici($quadri, 'RG010', 33, $componenti, 'RG10');
        // totali RG12 e RG24 con le formule dei controlli delle specifiche SP
        $totalePositivi = $quadri->getImporto('RG002005') + $quadri->getImporto('RG003001') + $quadri->getImporto('RG005002') + $quadri->getImporto('RG006002') + $quadri->getImporto('RG007001') + $positivi + $quadri->getImporto('RG011001');
        $quadri->proponi('RG012001', $totalePositivi ?: null);
        foreach (['RG15' => 'RG015001', 'RG16' => 'RG016001', 'RG18' => 'RG018001', 'RG19' => 'RG019001', 'RG20' => 'RG020001'] as $rigo => $codice) {
            $quadri->proponi($codice, $importo($rigo) ?: null);
        }
        $negativi = $impresa->proponiCodici($quadri, 'RG022', 37, $componenti, 'RG22');
        array_push($this->avvisi, ...$impresa->getAvvisi());
        $totaleNegativi = $quadri->getImporto('RG013004') + $quadri->getImporto('RG015001') + $quadri->getImporto('RG016001') + $quadri->getImporto('RG017001')
                + $quadri->getImporto('RG018001') + $quadri->getImporto('RG019001') + $quadri->getImporto('RG020001') + $negativi + $quadri->getImporto('RG023008');
        $quadri->proponi('RG024001', $totaleNegativi ?: null);
        $differenza = $totalePositivi - $totaleNegativi;
        $quadri->proponi('RG025001', $differenza ?: null);
        $lordo = $differenza + $quadri->getImporto('RG026002') - $quadri->getImporto('RG027002');
        $quadri->proponi('RG028002', $lordo ?: null);
        $reddito = $lordo - $quadri->getImporto('RG029001');
        // proventi esenti: riducono la perdita (RG31 = RG28 - RG29 + RG30)
        $reddito += $reddito < 0 ? $quadri->getImporto('RG030001') : 0;
        $quadri->proponi('RG031001', $reddito ?: null);
        $perdite = $reddito > 0 ? min($reddito, $quadri->getImporto('RG032001') + $quadri->getImporto('RG032002') + $quadri->getImporto('RG032003')) : 0;
        $quadri->proponi('RG032004', $perdite ?: null);
        $netto = $reddito > 0 ? $reddito - $perdite - min($quadri->getImporto('RG033001'), $reddito - $perdite) : $reddito;
        $quadri->proponi('RG034001', $netto ?: null);
        $rimanenze = $quadri->getImporto('RG038002') + $quadri->getImporto('RG038003') + $quadri->getImporto('RG038004');
        $quadri->proponi('RG038001', $rimanenze > 0 ? null : 1);
        $this->avvisi[] = 'Quadro RG: componenti per cassa dalla contabilità; perdite pregresse (rigo RG32) e partecipazioni (righi RG26 e RG27) da indicare a mano.';
    }

    /**
     * Quadro RN di Redditi SP: redditi d'impresa da imputare ai soci (RN1
     * contabilità ordinaria, RN2 semplificata) con le ritenute d'acconto
     * subite dalle partite aperte (colonna 2).
     */
    private function proponiImputazione(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $ritenute = (int) round((new PartiteAperte($this->dataMapper))->getRitenute($soggetto, $anno));
        foreach (['RN001' => 'RF066001', 'RN002' => 'RG034001'] as $rigo => $codice) {
            $presente = $quadri->get($codice) !== null;
            $quadri->proponi($rigo . '001', $presente ? $quadri->getImporto($codice) : null);
            $quadri->proponi($rigo . '002', $presente && ($ritenute > 0) ? $ritenute : null);
            if ($presente) {
                $ritenute = 0;
            }
        }
    }

    /**
     * Quadro RO di Redditi SP dai legami dell'Anagrafica vigenti alla fine
     * dell'anno: sezione I, amministratori (legale rappresentante,
     * amministratore unico, presidente, consiglieri; qualifica A se anche
     * soci, B altrimenti); sezione II, soci con la quota di partecipazione
     * (qualifica A se amministratori o accomandatari, B accomandanti, R gli
     * altri). Righi RO1-RO9 e RO10-RO18 del primo modulo.
     */
    private function proponiSoci(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $fine = new SismaDate($anno . '-12-31');
        $amministratori = $soci = [];
        foreach ((new LegameModel($this->dataMapper))->getCollegati($soggetto) as $legame) {
            if ($legame->isVigenteAl($fine) === false) {
                continue;
            }
            $persona = $legame->soggettoCollegato;
            if (in_array($legame->ruolo, [RuoloLegame::legaleRappresentante, RuoloLegame::amministratoreUnico, RuoloLegame::presidenteCda, RuoloLegame::consigliere], true)) {
                $amministratori[$persona->codiceFiscale] = $persona;
            } elseif (in_array($legame->ruolo, [RuoloLegame::socio, RuoloLegame::socioAccomandatario, RuoloLegame::socioAccomandante], true)) {
                $soci[$persona->codiceFiscale] = [$persona, $legame->ruolo, $legame->quotaPartecipazione];
            }
        }
        $numero = 1;
        foreach (array_slice($amministratori, 0, 9) as $codiceFiscale => $persona) {
            $rigo = sprintf('RO%03d', $numero++);
            $this->proponiPersona($quadri, $rigo, $persona, [1, 2, 3, 4, 5, 6]);
            $quadri->proponi($rigo . '007', isset($soci[$codiceFiscale]) ? 'A' : 'B');
        }
        $numero = 10;
        foreach (array_slice($soci, 0, 9) as $codiceFiscale => [$persona, $ruolo, $quota]) {
            $rigo = sprintf('RO%03d', $numero++);
            $this->proponiPersona($quadri, $rigo, $persona, [1, 2, 6, 3, 4, 5]);
            $quadri->proponi($rigo . '008', $quota);
            $quadri->proponi($rigo . '010', match (true) {
                $ruolo === RuoloLegame::socioAccomandante => 'B',
                ($ruolo === RuoloLegame::socioAccomandatario) || isset($amministratori[$codiceFiscale]) => 'A',
                default => 'R',
            });
            if ($quota === null) {
                $this->avvisi[] = 'Quadro RO: quota di partecipazione agli utili di ' . $persona->nominativo . ' non indicata nel legame di socio.';
            }
        }
        if ((count($amministratori) > 9) || (count($soci) > 9)) {
            $this->avvisi[] = 'Quadro RO: più di nove amministratori o soci, i successivi vanno indicati a mano in un altro modulo.';
        }
        if (count($soci) === 0) {
            $this->avvisi[] = 'Quadro RO: nessun socio nei legami dell\'Anagrafica (ruolo socio con la quota di partecipazione).';
        }
    }

    /**
     * Dati anagrafici del rigo del quadro RO nelle colonne indicate, in
     * quest'ordine: codice fiscale, cognome e nome (o denominazione), data
     * di nascita, sesso, comune e provincia di nascita (l'ordine delle
     * colonne differisce tra le due sezioni).
     *
     * @param int[] $colonne
     */
    private function proponiPersona(QuadriDichiarazione $quadri, string $rigo, Soggetto $persona, array $colonne): void
    {
        [$cf, $nome, $nascita, $sesso, $comune, $provincia] = $colonne;
        $quadri->proponi(sprintf('%s%03d', $rigo, $cf), $persona->codiceFiscale);
        $quadri->proponi(sprintf('%s%03d', $rigo, $nome), mb_strtoupper(trim($persona->isPersonaFisica() ? $persona->cognome . ' ' . $persona->nome : $persona->nominativo)));
        if ($persona->isPersonaFisica()) {
            [$luogo, $sigla] = DatiContribuente::getLuogoNascita($persona);
            $quadri->proponi(sprintf('%s%03d', $rigo, $nascita), $persona->dataNascita?->format('Y-m-d'));
            $quadri->proponi(sprintf('%s%03d', $rigo, $sesso), $persona->sesso?->value);
            $quadri->proponi(sprintf('%s%03d', $rigo, $comune), $luogo === null ? null : mb_strtoupper($luogo));
            $quadri->proponi(sprintf('%s%03d', $rigo, $provincia), in_array($sigla, ['EE', '', null], true) ? null : $sigla);
        }
    }

    /**
     * Quote di reddito e di perdita imputate per trasparenza (art. 115 Tuir)
     * dalle società di capitali partecipate con l'opzione vigente e la
     * dichiarazione Redditi SC dell'anno nella suite (quadro TN), per la
     * quota del legame di socio: rigo RF58 (o RF59), colonna 2, e totali.
     */
    private function proponiPartecipazioniTrasparenti(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $reddito = $perdita = 0;
        foreach (self::getPartecipazioniTrasparenti($this->dataMapper, $soggetto, $anno, TipoOpzioneTassazione::trasparenza) as $partecipazione) {
            $reddito += $partecipazione['reddito'];
            $perdita += $partecipazione['perdita'];
            $this->avvisi[] = sprintf('Quadro RF: quota del reddito di %s imputata per trasparenza (art. 115 Tuir) dal quadro TN della sua dichiarazione.', $partecipazione['societa']->nominativo);
        }
        $quadri->proponi('RF058002', $reddito ?: null);
        $quadri->proponi('RF058004', ($quadri->getImporto('RF058001') + $quadri->getImporto('RF058002')) ?: null);
        $quadri->proponi('RF059002', $perdita ?: null);
        $quadri->proponi('RF059003', ($quadri->getImporto('RF059001') + $quadri->getImporto('RF059002')) ?: null);
    }

    /**
     * Partecipazioni del socio in società di capitali con l'opzione per la
     * trasparenza del tipo indicato vigente nell'anno e la dichiarazione
     * Redditi SC dell'anno nella suite: quota di partecipazione (legame di
     * socio), quota del reddito imponibile (TN4, col. 2) o della perdita da
     * attribuire (TN2, col. 4) e delle ritenute (TN15).
     *
     * @return list<array{societa: Soggetto, quota: float, reddito: int, perdita: int, ritenute: int}>
     */
    public static function getPartecipazioniTrasparenti(DataMapper $dataMapper, Soggetto $socio, int $anno, TipoOpzioneTassazione $tipo): array
    {
        $partecipazioni = [];
        $fine = new SismaDate($anno . '-12-31');
        foreach ((new LegameModel($dataMapper))->getIncarichi($socio) as $legame) {
            if (($legame->ruolo !== RuoloLegame::socio) || ($legame->isVigenteAl($fine) === false) || ($legame->quotaPartecipazione === null)) {
                continue;
            }
            $societa = $legame->soggetto;
            $opzione = (new OpzioneTassazioneModel($dataMapper))->getVigente($societa, $anno);
            $dichiarazione = $opzione?->tipoOpzione === $tipo ? (new DichiarazioneModel($dataMapper))->getPerModello($societa, ModelloDichiarativo::redditiSc, $anno) : null;
            if ($dichiarazione === null) {
                continue;
            }
            $quadri = new QuadriDichiarazione($dichiarazione, $dataMapper);
            $quota = $legame->quotaPartecipazione;
            $partecipazioni[] = ['societa' => $societa, 'quota' => $quota, 'reddito' => (int) round($quadri->getImporto('TN004006', 1) * $quota / 100),
                'perdita' => (int) round($quadri->getImporto('TN002004', 1) * $quota / 100), 'ritenute' => (int) round($quadri->getImporto('TN015001', 1) * $quota / 100)];
        }
        return $partecipazioni;
    }

    /**
     * Quadro GN di Redditi SC (società del consolidato nazionale, artt.
     * 117-129 Tuir): codice fiscale della consolidante, reddito (GN1) o
     * perdita (GN2), perdite degli esercizi anteriori al consolidato
     * inserite a mano nel rigo GN4 (col. 1 limitata all'80 per cento del
     * reddito, col. 2 piena, col. 3 ricevute), perdita da trasferire (GN5)
     * e reddito complessivo netto da comunicare alla consolidante (GN6,
     * col. 2), ritenute subite (GN19). La società non liquida l'IRES: la
     * liquida e la versa la consolidante con il modello CNM.
     */
    private function proponiConsolidato(QuadriDichiarazione $quadri, \Anagrafica\Application\Entities\OpzioneTassazione $opzione): void
    {
        $quadri->proponi('GN000002', $opzione->consolidante?->codiceFiscale);
        $reddito = $quadri->getImporto('RF063001');
        $quadri->proponi('GN001003', $reddito > 0 ? $reddito - $quadri->getImporto('GN001002') : null);
        $quadri->proponi('GN002001', $reddito < 0 ? -$reddito : null);
        $positivo = $quadri->getImporto('GN001003') + $quadri->getImporto('GN003003');
        // perdite anteriori al consolidato: restano della società (prospetto RS44-RS45), la perdita dell'anno va alla consolidante
        $this->proponiRiportoPerdite($quadri, $positivo, 'GN004001', 'GN004002');
        $perdite = $positivo > 0 ? min($positivo, $quadri->getImporto('GN004002') + $quadri->getImporto('GN004003') + min($quadri->getImporto('GN004001'), (int) floor($positivo * 0.8))) : 0;
        $quadri->proponi('GN004004', $perdite ?: null);
        $this->proponiProspettoPerdite($quadri, 'GN004001', 'GN004002', 0);
        $saldo = $positivo - $quadri->getImporto('GN002001') - $perdite;
        $quadri->proponi('GN005003', $saldo < 0 ? -$saldo : null);
        $quadri->proponi('GN006002', $saldo > 0 ? $saldo : null);
        $imponibile = max(0, $quadri->getImporto('GN006002') - $quadri->getImporto('GN006003') - $quadri->getImporto('GN006004') - $quadri->getImporto('GN006005'));
        $quadri->proponi('GN006006', $imponibile ?: null);
        $ritenute = (int) round((new PartiteAperte($this->dataMapper))->getRitenute($opzione->soggetto, (int) $quadri->getDichiarazione()->anno));
        $quadri->proponi('GN019001', $ritenute ?: null);
        $this->avvisi[] = sprintf('Consolidato nazionale con %s: quadro GN al posto del quadro RN; reddito complessivo netto (GN6) da comunicare alla consolidante, che liquida l\'IRES del gruppo nel modello CNM. Perdite anteriori al consolidato (GN4) dai residui dell\'anno prima o dall\'archivio delle perdite pregresse.', $opzione->consolidante?->nominativo ?? 'la consolidante');
    }

    /**
     * Quadro TN di Redditi SC (società trasparente, artt. 115 e 116 Tuir):
     * tipo di trasparenza, reddito (TN1) o perdita da attribuire (TN2),
     * perdite degli esercizi anteriori all'opzione dai residui (TN3),
     * reddito (TN4, col. 2) e reddito imponibile da imputare ai soci (col. 6), ritenute
     * (TN15). La società non liquida l'IRES: le quote vanno ai soci (quadro
     * RF delle società socie, righi RH5 e RH6 dei soci persone fisiche).
     */
    private function proponiTrasparenza(QuadriDichiarazione $quadri, \Anagrafica\Application\Entities\OpzioneTassazione $opzione): void
    {
        $quadri->proponi('TN000001', $opzione->tipoOpzione->getTipoTrasparenza());
        $reddito = $quadri->getImporto('RF063001');
        $quadri->proponi('TN001003', $reddito > 0 ? $reddito - $quadri->getImporto('TN001002') : null);
        $quadri->proponi('TN002002', $reddito < 0 ? -$reddito : null);
        $quadri->proponi('TN002004', $reddito < 0 ? max(0, -$reddito - $quadri->getImporto('TN002001')) ?: null : null);
        $positivo = $quadri->getImporto('TN001003');
        // perdite anteriori all'opzione: restano della società, la perdita dell'anno va ai soci
        $this->proponiRiportoPerdite($quadri, $positivo, 'TN003001', 'TN003002');
        $perdite = $positivo > 0 ? min($positivo, $quadri->getImporto('TN003002') + $quadri->getImporto('TN003003') + min($quadri->getImporto('TN003001'), (int) floor($positivo * 0.8))) : 0;
        $quadri->proponi('TN003004', $perdite ?: null);
        $this->proponiProspettoPerdite($quadri, 'TN003001', 'TN003002', 0);
        $quadri->proponi('TN004002', ($positivo - $perdite) > 0 ? $positivo - $perdite : null);
        // reddito imponibile: col. 2 - col. 3 - col. 4 - col. 5 + RJ22 col. 1 + RS543 col. 3 (istruzioni del rigo TN4)
        $imponibile = $quadri->getImporto('TN004002') - $quadri->getImporto('TN004003') - $quadri->getImporto('TN004004') - $quadri->getImporto('TN004005')
                + $quadri->getImporto('RJ022001') + $quadri->getImporto('RS543003');
        $quadri->proponi('TN004006', $imponibile > 0 ? $imponibile : null);
        $ritenute = (int) round((new PartiteAperte($this->dataMapper))->getRitenute($opzione->soggetto, (int) $quadri->getDichiarazione()->anno));
        $quadri->proponi('TN015001', $ritenute ?: null);
        $this->proponiImputazioneTrasparenza($quadri, $opzione->soggetto, (int) $quadri->getDichiarazione()->anno);
        $this->avvisi[] = 'Trasparenza fiscale (' . $opzione->tipoOpzione->getLabel() . '): quadro TN al posto del quadro RN; il reddito imponibile (TN4) è imputato ai soci per le quote dei legami di socio. Perdite anteriori all\'opzione (TN3) dai residui dell\'anno prima o dall\'archivio delle perdite pregresse.';
    }

    /**
     * Sezione di imputazione ai soci del quadro TN (righi da TN30 a TN33):
     * per ogni socio dei legami codice fiscale, partecipazione agli utili e
     * alle perdite (quota del legame) e reddito o perdita imputati (TN4
     * col. 6 o TN2 col. 4 per la quota; all'ultimo socio l'arrotondamento,
     * perché il totale coincida con il reddito o la perdita della società,
     * come chiedono i controlli). Oltre quattro soci i moduli successivi si
     * compilano a mano.
     */
    private function proponiImputazioneTrasparenza(QuadriDichiarazione $quadri, Soggetto $societa, int $anno): void
    {
        $fine = new SismaDate($anno . '-12-31');
        $soci = [];
        foreach ((new LegameModel($this->dataMapper))->getCollegati($societa) as $legame) {
            if (($legame->ruolo === RuoloLegame::socio) && $legame->isVigenteAl($fine) && ($legame->quotaPartecipazione !== null)) {
                $soci[$legame->soggettoCollegato->codiceFiscale] = $legame->quotaPartecipazione;
            }
        }
        $reddito = $quadri->getImporto('TN004006') > 0 ? $quadri->getImporto('TN004006') : -$quadri->getImporto('TN002004');
        $imputato = 0;
        $righe = array_slice($soci, 0, 4, true);
        $ultimo = array_key_last($righe);
        $rigo = 30;
        foreach ($righe as $codiceFiscale => $quota) {
            $campo = fn(int $colonna) => sprintf('TN%03d%03d', $rigo, $colonna);
            $importo = (($codiceFiscale === $ultimo) && (count($righe) === count($soci)) && (abs(array_sum($soci) - 100) < 0.01))
                    ? $reddito - $imputato : (int) round($reddito * $quota / 100);
            $imputato += $importo;
            $quadri->proponi($campo(1), (string) $codiceFiscale);
            $quadri->proponi($campo(2), $quota);
            $quadri->proponi($campo(3), $reddito < 0 ? $quota : null);
            $quadri->proponi($campo(4), $importo ?: null);
            $rigo++;
        }
        if (count($soci) === 0) {
            $this->avvisi[] = 'Quadro TN: nessun socio con la quota di partecipazione nei legami dell\'Anagrafica, imputazione ai soci (righi da TN30 a TN33) da compilare a mano.';
        } elseif (count($soci) > 4) {
            $this->avvisi[] = sprintf('Quadro TN: %d soci, proposti i primi quattro nei righi da TN30 a TN33; gli altri si indicano nei moduli successivi.', count($soci));
        }
        if (abs(array_sum($soci) - 100) >= 0.01 && (count($soci) > 0)) {
            $this->avvisi[] = sprintf('Quadro TN: le quote dei soci nei legami sommano al %s per cento, non al 100.', number_format(array_sum($soci), 2, ',', '.'));
        }
    }

    /**
     * Perdite dei periodi precedenti utilizzate nel rigo RN4 (o GN4, TN3):
     * dai residui della dichiarazione dell'anno prima o dell'archivio delle
     * perdite pregresse, prima quelle in misura limitata fino all'80 per
     * cento del reddito, poi quelle in misura piena (come Redditi PF,
     * PropostaImpresa::utilizzaPerdite). Senza residui noti restano quelle
     * indicate a mano.
     */
    private function proponiRiportoPerdite(QuadriDichiarazione $quadri, int $reddito, string $campoLimitata, string $campoPiena): void
    {
        if ($this->residui === null) {
            if ($quadri->getImporto($campoLimitata) + $quadri->getImporto($campoPiena) > 0) {
                $this->avvisi[] = 'Perdite pregresse indicate a mano: senza la dichiarazione Redditi SC dell\'anno prima nella suite né l\'archivio delle perdite pregresse del contribuente, compila a mano anche i residui del prospetto RS44-RS45 (colonna 6).';
            }
            return;
        }
        $utilizzo = PropostaImpresa::utilizzaPerdite($reddito, $this->residui, $this->primiTreAnni);
        $quadri->proponi($campoLimitata, $utilizzo['limitata'] ?: null);
        $quadri->proponi($campoPiena, $utilizzo['piena'] ?: null);
        $this->avvisi[] = sprintf('Perdite pregresse dalla %s: %s euro in misura limitata e %s in misura piena; utilizzate %s e %s.', $this->residui['fonte'],
                number_format($this->residui['limitata'], 0, ',', '.'), number_format($this->residui['piena'], 0, ',', '.'),
                number_format($quadri->getImporto($campoLimitata), 0, ',', '.'), number_format($quadri->getImporto($campoPiena), 0, ',', '.'));
    }

    /**
     * Prospetto delle perdite non compensate di Redditi SC (righi RS44 e
     * RS45): perdita dell'anno (colonna 2, in misura piena nei primi tre
     * periodi d'imposta) e residui (colonna 6) = residui dell'anno prima -
     * perdite utilizzate + perdita dell'anno. Per le società in consolidato
     * o trasparenti la perdita dell'anno va alla consolidante o ai soci.
     */
    private function proponiProspettoPerdite(QuadriDichiarazione $quadri, string $campoLimitata, string $campoPiena, int $perditaAnno): void
    {
        $limitataAnno = $this->primiTreAnni ? 0 : $perditaAnno;
        $pienaAnno = $this->primiTreAnni ? $perditaAnno : 0;
        $quadri->proponi('RS044002', $limitataAnno ?: null);
        $quadri->proponi('RS045002', $pienaAnno ?: null);
        $manuali = ($this->residui === null) && ($quadri->getImporto($campoLimitata) + $quadri->getImporto($campoPiena) > 0);
        if ($manuali) {
            return;
        }
        $quadri->proponi('RS044006', (($this->residui['limitata'] ?? 0) - $quadri->getImporto($campoLimitata) + $limitataAnno) ?: null);
        $quadri->proponi('RS045006', (($this->residui['piena'] ?? 0) - $quadri->getImporto($campoPiena) + $pienaAnno) ?: null);
    }

    /**
     * Quadro RN e rigo RX1 di Redditi SC (istruzioni del quadro RN, par. 6.1).
     */
    private function proponiIres(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, bool $isa): void
    {
        $reddito = $quadri->getImporto('RF063001');
        $quadri->proponi('RN001003', $reddito > 0 ? $reddito - $quadri->getImporto('RN001002') : null);
        $quadri->proponi('RN002001', $reddito < 0 ? -$reddito : null);
        $positivo = $quadri->getImporto('RN001003') + $quadri->getImporto('RN003003');
        $this->proponiRiportoPerdite($quadri, $positivo, 'RN004001', 'RN004002');
        $perdite = $positivo > 0 ? min($positivo, $quadri->getImporto('RN004002') + $quadri->getImporto('RN004003') + min($quadri->getImporto('RN004001'), (int) floor($positivo * 0.8))) : 0;
        $quadri->proponi('RN004004', $perdite ?: null);
        $saldo = $positivo - $quadri->getImporto('RN002001') - $perdite;
        $quadri->proponi('RN005003', $saldo < 0 ? -$saldo : null);
        $this->proponiProspettoPerdite($quadri, 'RN004001', 'RN004002', $quadri->getImporto('RN005003'));
        $imponibileLordo = max(0, $saldo);
        $quadri->proponi('RN006002', $imponibileLordo ?: null);
        $imponibile = max(0, $imponibileLordo - $quadri->getImporto('RN006005') - $quadri->getImporto('RN006006') - $quadri->getImporto('RN006010'));
        $quadri->proponi('RN006011', $imponibile ?: null);
        $ordinario = max(0, $imponibile - $quadri->getImporto('RN007001') - $quadri->getImporto('RN008A01'));
        $quadri->proponi('RN008001', $ordinario ?: null);
        $quadri->proponi('RN008002', (int) round($ordinario * self::ALIQUOTA_IRES) ?: null);
        $imposta = $quadri->getImporto('RN007003') + $quadri->getImporto('RN008002') + $quadri->getImporto('RN008A05');
        $quadri->proponi('RN009001', $imposta ?: null);
        $netta = max(0, $imposta - $quadri->getImporto('RN009A01') - $quadri->getImporto('RN010006'));
        $quadri->proponi('RN011002', $netta ?: null);
        $quadri->proponi('RN012001', $quadri->getImporto('RN003001') ?: null);
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $ritenute = (int) round((new PartiteAperte($this->dataMapper))->getRitenute($soggetto, $anno));
        $quadri->proponi('RN015002', $ritenute ?: null);
        $crediti = $quadri->getImporto('RN012001') + $quadri->getImporto('RN013001') + $quadri->getImporto('RN014010') + $quadri->getImporto('RN015002') - $quadri->getImporto('RN015001');
        $quadri->proponi('RN016001', $crediti ?: null);
        $dovuta = $netta - $crediti;
        $quadri->proponi('RN017001', $dovuta ?: null);
        // eccedenza IRES della dichiarazione dell'anno prima, se è nella suite (credito da utilizzare in compensazione, RX1 col. 5)
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::redditiSc, $anno - 1);
        if ($precedente !== null) {
            $quadri->proponi('RN019001', (new QuadriDichiarazione($precedente, $this->dataMapper))->getImporto('RX001005', 1) ?: null);
        }
        $acconti = 0.0;
        foreach (VersamentiRedditi::getVersatiPerCodici($this->dataMapper, $soggetto, $anno, self::ACCONTI_IRES) as $importo) {
            $acconti += $importo;
        }
        $quadri->proponi('RN022002', (int) round($acconti) ?: null);
        $totaleAcconti = $quadri->getImporto('RN022001') + $quadri->getImporto('RN022002') - $quadri->getImporto('RN022003') + $quadri->getImporto('RN022004') + $quadri->getImporto('RN022005');
        $quadri->proponi('RN022006', $totaleAcconti ?: null);
        $risultato = $dovuta - $quadri->getImporto('RN018001') - $quadri->getImporto('RN019001') + $quadri->getImporto('RN020001') + $quadri->getImporto('RN021001') - $totaleAcconti;
        $quadri->proponi('RN023003', $risultato > 0 ? $risultato : null);
        $quadri->proponi('RN024001', $risultato < 0 ? -$risultato : null);
        $quadri->proponi('RX001001', $risultato > 0 ? $risultato : null);
        $credito = $risultato < 0 ? -$risultato - $quadri->getImporto('RN025001') : 0;
        $quadri->proponi('RX001002', $credito ?: null);
        $quadri->proponi('RX001005', $credito > 0 ? $credito - $quadri->getImporto('RX001004') : null);
        [$primo, $secondo] = self::getAccontiIres((float) $dovuta, $isa);
        if ($primo + $secondo > 0) {
            $this->avvisi[] = sprintf('Acconti IRES %d: %s euro (100%% dell\'IRES dovuta, rigo RN17, metodo storico)%s.', $anno + 1, number_format($primo + $secondo, 0, ',', '.'),
                    $primo > 0 ? sprintf(', in due rate da %s e %s euro', number_format($primo, 0, ',', '.'), number_format($secondo, 0, ',', '.')) : ' in unica rata a novembre');
        }
        $this->avvisi[] = 'Quadro RN: perdite dei periodi precedenti (rigo RN4, colonne 1 e 2), crediti d\'imposta (righi RN3, RN13, RN14, RN18) e detrazioni (RN10) da indicare a mano; il reddito imponibile e l\'IRES si ricalcolano alla riproposta.';
    }

    /**
     * Riepilogo per la scheda della dichiarazione (importi non nulli).
     *
     * @return array<string, int>
     */
    public static function getRiepilogo(QuadriDichiarazione $quadri): array
    {
        return array_filter([
            'Utile (RF4)' => $quadri->getImporto('RF004001', 1),
            'Perdita (RF5)' => $quadri->getImporto('RF005001', 1),
            'Variazioni in aumento (RF32)' => $quadri->getImporto('RF032001', 1),
            'Variazioni in diminuzione (RF56)' => $quadri->getImporto('RF056001', 1),
            'Reddito o perdita (RF63)' => $quadri->getImporto('RF063001', 1),
            'Reddito da imputare (RF66)' => $quadri->getImporto('RF066001', 1),
            'Reddito imponibile (RN6, col. 11)' => $quadri->getImporto('RN006011', 1),
            'IRES (RN9)' => $quadri->getImporto('RN009001', 1),
            'IRES dovuta (RN17)' => $quadri->getImporto('RN017001', 1),
            'IRES a debito (RX1, col. 1)' => $quadri->getImporto('RX001001', 1),
            'IRES a credito (RX1, col. 2)' => $quadri->getImporto('RX001002', 1),
        ]);
    }

    /**
     * Acconti IRES per l'anno successivo con il metodo storico (istruzioni
     * generali, par. 4.2): 100 per cento dell'imposta dovuta al netto di
     * detrazioni, crediti e ritenute (rigo RN17), non dovuto fino a 20,66
     * euro; prima rata del 40 per cento (50 per i soggetti ISA), non dovuta
     * se non supera 103 euro (rata unica a novembre).
     *
     * @return array{0: float, 1: float} prima e seconda (o unica) rata
     */
    public static function getAccontiIres(float $base, bool $isa): array
    {
        if ($base <= 20.66) {
            return [0.0, 0.0];
        }
        $primo = round($base * ($isa ? 0.5 : 0.4));
        return $primo > 103 ? [$primo, $base - $primo] : [0.0, $base];
    }

    private function proponiAltraVariazione(QuadriDichiarazione $quadri, string $rigo, int $importo, int $codice = 99): void
    {
        $libera = null;
        for ($coppia = 1; $coppia <= 53; $coppia += 2) {
            $presente = $quadri->get(sprintf('%s%03d', $rigo, $coppia));
            if ((int) $presente === $codice) {
                $libera = $coppia;
                break;
            }
            if (($presente === null) && ($libera === null) && $quadri->haCampo(sprintf('%s%03d', $rigo, $coppia))) {
                $libera = $coppia;
            }
        }
        if ($libera === null) {
            if ($importo > 0) {
                $this->avvisi[] = 'Rigo ' . substr($rigo, 0, 2) . (int) substr($rigo, 2) . ': nessuna coppia libera per la variazione con il codice ' . $codice . '.';
            }
            return;
        }
        $quadri->proponi(sprintf('%s%03d', $rigo, $libera), $importo > 0 ? $codice : null);
        $quadri->proponi(sprintf('%s%03d', $rigo, $libera + 1), $importo ?: null);
    }
}
