<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\ClassificazioneAteco;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Anagrafica\Application\Models\CodiceAtecoModel;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\LegameModel;
use Contabilita\Application\Services\ComponentiReddito;
use Contabilita\Application\Services\RighiRedditi;
use Deleghe\Application\Enumerations\SezioneF24;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\VersamentoModel;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Redditi d'impresa e di lavoro autonomo dalla Contabilità, secondo il
 * regime dei dati fiscali dell'Anagrafica al 31 dicembre:
 * - RE (arti e professioni) e RG (impresa in contabilità semplificata) per
 *   cassa, dai componenti per rigo (ComponentiReddito, RighiRedditi);
 * - RF (contabilità ordinaria): utile o perdita del conto economico, le
 *   variazioni fiscali si indicano a mano (totali RF32 e RF56);
 * - LM, sezione II (forfetario, L. 190/2014): ricavi incassati per il
 *   coefficiente di redditività del codice ATECO 2007, contributi
 *   previdenziali dai versamenti F24 della sezione INPS dell'anno,
 *   imposta sostitutiva del 15 per cento (5 per cento nei primi cinque
 *   anni di una nuova attività, da attestare nel rigo LM21);
 * - impresa familiare: prospetto di imputazione del quadro RS (righi RS6 e
 *   RS7) dai legami "Collaboratore familiare" con la quota dell'Anagrafica,
 *   quote del reddito positivo (i collaboratori non partecipano alle
 *   perdite) nei righi RG33 o RF98 e reddito di spettanza nei righi RG34 o
 *   RF99; nel regime forfetario il prospetto con le sole quote (l'imposta
 *   sostitutiva resta al titolare, art. 1, comma 64, L. 190/2014);
 * - perdite d'impresa degli anni precedenti (righi RG35 e RF100) dal
 *   prospetto delle perdite non compensate (righi RS12 e RS13, col. 2) della
 *   dichiarazione dell'anno prima nella suite: prima quelle in misura
 *   limitata, fino all'80 per cento del reddito, poi quelle in misura piena
 *   sul reddito che resta (art. 8, comma 3, e art. 84 Tuir); residui e
 *   perdita dell'anno nel prospetto RS12-RS13 (misura piena nei primi tre
 *   periodi d'imposta dall'inizio dell'attività).
 */
class PropostaImpresa
{

    public const QUADRI = ['RE', 'RG', 'RF', 'LM', 'RS'];

    /** @var string[] */
    private array $avvisi = [];

    /** @var array{limitata: int, piena: int}|null perdite residue della dichiarazione dell'anno prima */
    private ?array $perditePrecedenti = null;

    /** Primi tre periodi d'imposta dall'inizio dell'attività (perdite in misura piena) */
    private bool $primiTreAnni = false;

    /** @var list<array{codiceFiscale: string, quota: float, nominativo: string}> collaboratori dell'impresa familiare */
    private array $collaboratori = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * @return string[]
     */
    public function proponi(Soggetto $soggetto, int $anno, QuadriDichiarazione $quadri): array
    {
        $this->avvisi = [];
        $quadri->usaModulo(1);
        $dati = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'));
        if (($dati === null) || ($dati->partitaIva === null) || ($dati->regimeFiscale === null)) {
            return [];
        }
        $componenti = new ComponentiReddito($this->dataMapper);
        $this->perditePrecedenti = $this->getPerditePrecedenti($soggetto, $anno);
        $this->primiTreAnni = ($dati->dataInizioAttivita !== null) && ($anno - (int) $dati->dataInizioAttivita->format('Y') <= 2);
        $professionista = $dati->tipoAttivita === TipoAttivita::lavoroAutonomo;
        $this->collaboratori = $dati->tipoAttivita === TipoAttivita::impresaFamiliare ? $this->getCollaboratori($soggetto, $anno) : [];
        if (($dati->tipoAttivita === TipoAttivita::impresaFamiliare) && (count($this->collaboratori) === 0)) {
            $this->avvisi[] = 'Impresa familiare senza collaboratori nei legami dell\'Anagrafica (ruolo "Collaboratore familiare" con la quota): prospetto del quadro RS e rigo RG33 o RF98 da compilare a mano.';
        }
        match (true) {
            in_array($dati->regimeFiscale, [RegimeFiscale::forfetario], true) => $this->proponiForfetario($quadri, $soggetto, $anno, $dati, $componenti->perCassa($soggetto, $anno, $professionista), $professionista),
            $professionista => $this->proponiProfessionista($quadri, $dati, $componenti->perCassa($soggetto, $anno, true)),
            $dati->regimeFiscale === RegimeFiscale::semplificato => $this->proponiSemplificata($quadri, $dati, $componenti->perCassa($soggetto, $anno, false)),
            $dati->regimeFiscale === RegimeFiscale::ordinario => $this->proponiOrdinaria($quadri, $dati, $componenti->perCompetenza($soggetto, $anno)),
            default => $this->avvisi[] = 'Regime ' . $dati->regimeFiscale->getLabel() . ': quadro del reddito d\'impresa da compilare a mano.',
        };
        array_push($this->avvisi, ...array_values($componenti->getAvvisi()));
        return $this->avvisi;
    }

    /**
     * Quadro RG, impresa in contabilità semplificata (per cassa).
     */
    private function proponiSemplificata(QuadriDichiarazione $quadri, DatiFiscali $dati, array $componenti): void
    {
        $importo = fn(string $rigo) => (int) round($componenti[$rigo] ?? 0.0);
        $quadri->proponi('RG001001', $dati->codiceAteco?->codice);
        $quadri->proponi('RG002002', $importo('RG2') ?: null);
        $quadri->proponi('RG003001', $importo('RG3') ?: null);
        $quadri->proponi('RG006002', $importo('RG6') ?: null);
        $quadri->proponi('RG007001', $importo('RG7') ?: null);
        $positivi = $this->proponiCodici($quadri, 'RG010', 39, $componenti, 'RG10');
        $totalePositivi = $quadri->getImporto('RG002002') + $quadri->getImporto('RG003001') + $quadri->getImporto('RG005002') + $quadri->getImporto('RG006002') + $quadri->getImporto('RG007001') + $positivi;
        $quadri->proponi('RG012001', $totalePositivi ?: null);
        foreach (['RG15' => 'RG015001', 'RG16' => 'RG016001', 'RG18' => 'RG018001', 'RG19' => 'RG019001', 'RG20' => 'RG020001'] as $rigo => $codice) {
            $quadri->proponi($codice, $importo($rigo) ?: null);
        }
        $negativi = $this->proponiCodici($quadri, 'RG022', 65, $componenti, 'RG22');
        $totaleNegativi = $quadri->getImporto('RG013004') + $quadri->getImporto('RG015001') + $quadri->getImporto('RG016001') + $quadri->getImporto('RG017001')
                + $quadri->getImporto('RG018001') + $quadri->getImporto('RG019001') + $quadri->getImporto('RG020001') + $negativi;
        $quadri->proponi('RG024001', $totaleNegativi ?: null);
        $differenza = $totalePositivi - $totaleNegativi;
        $quadri->proponi('RG025001', $differenza ?: null);
        $lordo = $differenza + $quadri->getImporto('RG026004') - $quadri->getImporto('RG027003');
        $quadri->proponi('RG028002', $lordo ?: null);
        $reddito = $lordo - $quadri->getImporto('RG029001') - $quadri->getImporto('RG030001');
        $quadri->proponi('RG031001', $reddito ?: null);
        $quadri->proponi('RG033004', $this->imputaCollaboratori($quadri, $this->collaboratori, $reddito) ?: null);
        $spettanza = $reddito - $quadri->getImporto('RG033004');
        $quadri->proponi('RG034003', $spettanza ?: null);
        $netto = $this->proponiPerdite($quadri, $spettanza, 'RG035');
        $quadri->proponi('RG036001', $netto > 0 ? $netto : null);
        if ($netto < 0) {
            $this->avvisi[] = 'Quadro RG: perdita d\'impresa di ' . (-$netto) . ' euro, da riportare (rigo RS, prospetto delle perdite) e da compensare con gli altri redditi solo nei limiti di legge.';
        }
    }

    /**
     * Righi con il codice della voce (RG10, RG22): coppie codice e importo,
     * totale nell'ultima colonna.
     */
    private function proponiCodici(QuadriDichiarazione $quadri, string $prefisso, int $colonnaTotale, array $componenti, string $rigo): int
    {
        $voci = [];
        foreach ($componenti as $chiave => $importo) {
            [$rigoChiave, $codice] = RighiRedditi::scomponi($chiave);
            if (($rigoChiave === $rigo) && ($codice !== null) && (round($importo) != 0)) {
                $voci[$codice] = ($voci[$codice] ?? 0) + (int) round($importo);
            }
        }
        ksort($voci);
        $colonna = 1;
        foreach ($voci as $codice => $importo) {
            if ($colonna + 1 >= $colonnaTotale) {
                $this->avvisi[] = 'Rigo ' . $rigo . ': più voci di quante ne entrino nel rigo, aggiungi un modulo a mano.';
                break;
            }
            $quadri->proponi(sprintf('%s%03d', $prefisso, $colonna), $codice);
            $quadri->proponi(sprintf('%s%03d', $prefisso, $colonna + 1), $importo);
            $colonna += 2;
        }
        $totale = array_sum($voci);
        $quadri->proponi(sprintf('%s%03d', $prefisso, $colonnaTotale), $totale ?: null);
        return $totale;
    }

    /**
     * Quadro RE, arti e professioni (per cassa): spese di rappresentanza
     * deducibili fino all'1 per cento dei compensi, alberghi e ristoranti
     * al 75 per cento fino al 2 per cento (art. 54, c. 5, Tuir).
     */
    private function proponiProfessionista(QuadriDichiarazione $quadri, DatiFiscali $dati, array $componenti): void
    {
        $importo = fn(string $rigo) => (int) round($componenti[$rigo] ?? 0.0);
        $quadri->proponi('RE001001', $dati->codiceAteco?->codice);
        $quadri->proponi('RE002002', $importo('RE2') ?: null);
        $quadri->proponi('RE003001', $importo('RE3') ?: null);
        $quadri->proponi('RE004001', $importo('RE4') ?: null);
        $compensi = $quadri->getImporto('RE002002') + $quadri->getImporto('RE003001') + $quadri->getImporto('RE004001') + $quadri->getImporto('RE005002');
        $quadri->proponi('RE006001', $compensi ?: null);
        foreach (['RE7' => 'RE007002', 'RE8' => 'RE008002', 'RE9' => 'RE009001', 'RE10' => 'RE010001', 'RE11' => 'RE011003', 'RE12' => 'RE012001',
            'RE13' => 'RE013001', 'RE14' => 'RE014001', 'RE18' => 'RE018001', 'RE19' => 'RE019004'] as $rigo => $codice) {
            $quadri->proponi($codice, $importo($rigo) ?: null);
        }
        $alberghi = (int) round(min($importo('RE15') * 0.75, $compensi * 0.02));
        $quadri->proponi('RE015001', $alberghi ?: null);
        $quadri->proponi('RE016002', $importo('RE16') ?: null);
        $quadri->proponi('RE016003', (int) round(min($importo('RE16'), $compensi * 0.01)) ?: null);
        $spese = 0;
        foreach (['RE007002', 'RE008002', 'RE009001', 'RE010001', 'RE011003', 'RE012001', 'RE013001', 'RE014001', 'RE015001', 'RE016003', 'RE017001', 'RE018001', 'RE019004'] as $codice) {
            $spese += $quadri->getImporto($codice);
        }
        $quadri->proponi('RE020001', $spese ?: null);
        $differenza = $compensi - $spese;
        $quadri->proponi('RE021005', $differenza ?: null);
        $reddito = $differenza - $quadri->getImporto('RE021001');
        $quadri->proponi('RE023001', $reddito ?: null);
        $quadri->proponi('RE025001', ($reddito - $quadri->getImporto('RE024001')) ?: null);
    }

    /**
     * Quadro RF, contabilità ordinaria: utile o perdita del conto economico;
     * variazioni in aumento per la parte indeducibile dei costi dei conti con
     * la percentuale deducibile (righi RF16, RF18 e RF31 codice 99) e totale
     * del rigo RF32 (specifiche, controlli del record C); le altre
     * variazioni in aumento e quelle in diminuzione (RF56) a mano.
     */
    private function proponiOrdinaria(QuadriDichiarazione $quadri, DatiFiscali $dati, array $competenza): void
    {
        $utile = (int) round($competenza['utile']);
        $quadri->proponi('RF001001', $dati->codiceAteco?->codice);
        $quadri->proponi('RF004001', $utile > 0 ? $utile : null);
        $quadri->proponi('RF005001', $utile < 0 ? -$utile : null);
        $variazioni = array_map(fn(float $importo) => (int) round($importo), $competenza['variazioni'] ?? []);
        $quadri->proponi('RF016001', ($variazioni['RF16'] ?? 0) ?: null);
        $quadri->proponi('RF018001', ($variazioni['RF18'] ?? 0) ?: null);
        $this->proponiAltraVariazione($quadri, 99, $variazioni['RF31:99'] ?? 0);
        $altre = 0;
        for ($coppia = 2; $coppia <= 66; $coppia += 2) {
            $altre += $quadri->getImporto(sprintf('RF031%03d', $coppia));
        }
        $quadri->proponi('RF031067', $altre ?: null);
        $aumento = 0;
        foreach (['RF007002', 'RF008001', 'RF010001', 'RF011001', 'RF012002', 'RF013001', 'RF015002', 'RF016001', 'RF017002', 'RF018001', 'RF019002', 'RF020001',
            'RF021003', 'RF023003', 'RF024001', 'RF025003', 'RF027001', 'RF031067'] as $codice) {
            $aumento += $quadri->getImporto($codice);
        }
        $quadri->proponi('RF032001', $aumento ?: null);
        $somma = $quadri->getImporto('RF004001') - $quadri->getImporto('RF005001') + $quadri->getImporto('RF032001') - $quadri->getImporto('RF056001');
        $quadri->proponi('RF057001', $somma ?: null);
        $quadri->proponi('RF058004', $somma > 0 ? $somma : null);
        $quadri->proponi('RF059003', $somma < 0 ? -$somma : null);
        $lordo = $quadri->getImporto('RF058004') - $quadri->getImporto('RF059003') - $quadri->getImporto('RF060001');
        $quadri->proponi('RF060002', $lordo ?: null);
        $reddito = $lordo - $quadri->getImporto('RF061001') - $quadri->getImporto('RF062001');
        $quadri->proponi('RF063001', $reddito ?: null);
        $quadri->proponi('RF098004', $this->imputaCollaboratori($quadri, $this->collaboratori, $reddito) ?: null);
        $spettanza = $reddito - $quadri->getImporto('RF098004');
        $quadri->proponi('RF099003', $spettanza ?: null);
        $netto = $this->proponiPerdite($quadri, $spettanza, 'RF100');
        $quadri->proponi('RF101001', $netto > 0 ? $netto : null);
        $this->avvisi[] = 'Quadro RF: utile del conto economico proposto (' . $utile . ' euro) con le variazioni dei conti deducibili in parte; indica le altre variazioni in aumento (righi da RF6 a RF31) e quelle in diminuzione (righi da RF33 a RF55, totale RF56), poi riproponi.';
    }

    /**
     * Quadro LM, sezione II (regime forfetario).
     */
    private function proponiForfetario(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno, DatiFiscali $dati, array $componenti, bool $professionista): void
    {
        $ricavi = (int) round(($componenti['RG2'] ?? 0.0) + ($componenti['RG3'] ?? 0.0) + ($componenti['RE2'] ?? 0.0) + ($componenti['RE3'] ?? 0.0));
        $quadri->proponi('LM021004', $dati->codiceAteco?->codice);
        $codice2007 = $this->getCodice2007($soggetto, $dati, $anno);
        $coefficiente = $codice2007 === null ? null : CoefficientiForfetari::getCoefficiente($codice2007);
        if ($coefficiente === null) {
            $this->avvisi[] = 'Quadro LM: codice ATECO 2007 dell\'attività non individuato, indica a mano codice e coefficiente di redditività (rigo LM22).';
        }
        $quadri->proponi('LM022001', $codice2007);
        $quadri->proponi('LM022002', $coefficiente);
        $quadri->proponi('LM022003', $ricavi ?: null);
        $reddito = (int) round($quadri->getImporto('LM022003') * $quadri->getDecimale('LM022002') / 100 + $quadri->getImporto('LM022004') * 0.75);
        $quadri->proponi('LM022005', $reddito ?: null);
        $quadri->proponi('LM022006', $professionista ? 2 : 1);
        $lordo = $quadri->getImporto('LM022005');
        $quadri->proponi('LM034003', $lordo ?: null);
        $quadri->proponi($professionista ? 'LM034002' : 'LM034001', $lordo ?: null);
        $contributi = (int) round($this->getContributiVersati($soggetto, $anno));
        $quadri->proponi('LM035001', $contributi ?: null);
        $deducibili = min($quadri->getImporto('LM035001'), $quadri->getImporto('LM034003'));
        $quadri->proponi('LM035002', $deducibili ?: null);
        $netto = max(0, $quadri->getImporto('LM034003') - $quadri->getImporto('LM035002'));
        $quadri->proponi('LM036001', $netto ?: null);
        $imponibile = max(0, $netto - $quadri->getImporto('LM037005'));
        $quadri->proponi('LM038001', $imponibile ?: null);
        $nuova = $quadri->get('LM021003') === null && ($dati->dataInizioAttivita !== null) && ($anno - (int) $dati->dataInizioAttivita->format('Y') <= 4);
        if ($nuova) {
            $this->avvisi[] = 'Quadro LM: attività iniziata nel ' . $dati->dataInizioAttivita->format('Y') . ', imposta sostitutiva del 5 per cento se ricorrono le condizioni del comma 65: barra la casella del rigo LM21, col. 3, e riproponi.';
        }
        if (count($this->collaboratori) > 0) {
            $this->imputaCollaboratori($quadri, $this->collaboratori, null);
        }
        $imposta = (int) round($imponibile * ($quadri->get('LM021003') !== null ? 0.05 : 0.15));
        $quadri->proponi('LM039001', $imposta ?: null);
        $differenza = $imposta - $quadri->getImporto('LM040041') - $quadri->getImporto('LM041001');
        $quadri->proponi('LM042001', $differenza ?: null);
        $quadri->proponi('LM045002', (int) round(VersamentiRedditi::getAccontiVersati($this->dataMapper, $soggetto, $anno)['forfetario']) ?: null);
        $saldo = $differenza - $quadri->getImporto('LM043001') + $quadri->getImporto('LM044001') - $quadri->getImporto('LM045002');
        $quadri->proponi('LM046001', $saldo > 0 ? $saldo : null);
        $quadri->proponi('LM047001', $saldo < 0 ? -$saldo : null);
    }

    /**
     * Codice ATECO 2007/2022 dell'attività: quello dei dati fiscali o di una
     * versione precedente, altrimenti il codice 2022 con il raccordo ISTAT
     * verso il codice 2025 dei dati fiscali.
     */
    private function getCodice2007(Soggetto $soggetto, DatiFiscali $dati, int $anno): ?string
    {
        if ($dati->codiceAteco === null) {
            return null;
        }
        if ($dati->codiceAteco->classificazione !== ClassificazioneAteco::ateco2025) {
            return $dati->codiceAteco->codice;
        }
        foreach ((new DatiFiscaliModel($this->dataMapper))->getStorico($soggetto) as $versione) {
            if (($versione->codiceAteco !== null) && ($versione->codiceAteco->classificazione !== ClassificazioneAteco::ateco2025)) {
                return $versione->codiceAteco->codice;
            }
        }
        $this->avvisi[] = 'Quadro LM: il codice ATECO 2007 non è nei dati fiscali, verifica il coefficiente di redditività.';
        return (new CodiceAtecoModel($this->dataMapper))->getCodice2022PerRaccordo($dati->codiceAteco->codice);
    }

    /**
     * Contributi previdenziali versati nell'anno: versamenti della sezione
     * INPS con scadenza nell'anno in deleghe non in bozza.
     */
    private function getContributiVersati(Soggetto $soggetto, int $anno): float
    {
        $totale = 0.0;
        foreach ((new VersamentoModel($this->dataMapper))->getPerPeriodo($soggetto->studio, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'), $soggetto) as $versamento) {
            if (($versamento->sezione === SezioneF24::inps) && ($versamento->delega !== null) && ($versamento->delega->stato !== StatoDelega::bozza)) {
                $totale += $versamento->importoDebito - $versamento->importoCredito;
            }
        }
        return $totale;
    }

    /**
     * Collaboratori dell'impresa familiare: legami "Collaboratore familiare"
     * vigenti nell'anno, con la quota dell'ultima versione dell'anno.
     *
     * @return list<array{codiceFiscale: string, quota: float, nominativo: string}>
     */
    private function getCollaboratori(Soggetto $soggetto, int $anno): array
    {
        $ultimi = [];
        foreach ((new LegameModel($this->dataMapper))->getBySoggetto($soggetto) as $legame) {
            if (($legame->ruolo !== RuoloLegame::collaboratoreFamiliare) || ($legame->validoDal->format('Y-m-d') > $anno . '-12-31')
                    || (($legame->validoAl !== null) && ($legame->validoAl->format('Y-m-d') < $anno . '-01-01'))) {
                continue;
            }
            $id = $legame->soggettoCollegato->id;
            if (!isset($ultimi[$id]) || ($ultimi[$id]->validoDal->format('Y-m-d') < $legame->validoDal->format('Y-m-d'))) {
                $ultimi[$id] = $legame;
            }
        }
        $collaboratori = [];
        foreach ($ultimi as $legame) {
            if ($legame->quotaPartecipazione === null) {
                $this->avvisi[] = 'Impresa familiare: quota di ' . $legame->soggettoCollegato->nominativo . ' non indicata nel legame, prospetto del quadro RS da completare a mano.';
                continue;
            }
            $collaboratori[] = ['codiceFiscale' => $legame->soggettoCollegato->codiceFiscale, 'quota' => $legame->quotaPartecipazione, 'nominativo' => $legame->soggettoCollegato->nominativo];
        }
        return $collaboratori;
    }

    /**
     * Prospetto di imputazione del reddito dell'impresa familiare (righi RS6
     * e RS7, due collaboratori per modulo): quote del reddito positivo; con
     * $reddito null (forfetario) solo codice fiscale e quota.
     *
     * @param list<array{codiceFiscale: string, quota: float, nominativo: string}> $collaboratori
     * @return int totale delle quote dei collaboratori
     */
    public function imputaCollaboratori(QuadriDichiarazione $quadri, array $collaboratori, ?int $reddito): int
    {
        if (count($collaboratori) === 0) {
            return 0;
        }
        $quote = array_sum(array_column($collaboratori, 'quota'));
        if ($quote > 49) {
            $this->avvisi[] = sprintf('Impresa familiare: quote dei collaboratori del %s per cento, oltre il 49 per cento ammesso (art. 5, comma 4, Tuir).', number_format($quote, 2, ',', '.'));
        }
        if (($reddito !== null) && ($reddito <= 0)) {
            $this->avvisi[] = 'Impresa familiare: perdita imputata al solo titolare (i collaboratori partecipano agli utili, non alle perdite).';
        }
        if ($reddito === null) {
            $this->avvisi[] = 'Impresa familiare nel regime forfetario: nel prospetto del quadro RS indica a mano le quote di reddito dei collaboratori (colonna 3), che rilevano per il limite dei familiari a carico e per i contributi.';
        }
        $totale = 0;
        foreach ($collaboratori as $indice => $collaboratore) {
            $modulo = intdiv($indice, 2) + 1;
            $quadri->usaModulo($modulo);
            if (($modulo > 1) && ($indice % 2 === 0)) {
                $quadri->avviaProposta('RS');
            }
            $rigo = 6 + $indice % 2;
            $quotaReddito = ($reddito !== null) && ($reddito > 0) ? (int) round($reddito * $collaboratore['quota'] / 100) : null;
            $quadri->proponi(sprintf('RS%03d001', $rigo), $collaboratore['codiceFiscale']);
            $quadri->proponi(sprintf('RS%03d002', $rigo), $collaboratore['quota']);
            $quadri->proponi(sprintf('RS%03d003', $rigo), $quotaReddito);
            $totale += $quotaReddito ?? 0;
        }
        $quadri->usaModulo(1);
        return $totale;
    }

    /**
     * Utilizzo delle perdite pregresse e prospetto delle perdite non
     * compensate: prima le perdite in misura limitata fino all'80 per cento
     * del reddito, poi quelle in misura piena fino al reddito che resta.
     *
     * @param array{limitata: int, piena: int}|null $precedenti residui dell'anno prima (null se sconosciuti)
     * @return array{limitata: int, piena: int, rs12: array{int, ?int}, rs13: array{int, ?int}} utilizzi e righi RS12, RS13 (col. 1 e 2)
     */
    public static function utilizzaPerdite(int $reddito, ?array $precedenti, bool $primiTreAnni): array
    {
        $limitata = 0;
        $piena = 0;
        if (($reddito > 0) && ($precedenti !== null)) {
            $limitata = min($precedenti['limitata'], (int) floor($reddito * 0.8));
            $piena = min($precedenti['piena'], $reddito - $limitata);
        }
        $perdita = $reddito < 0 ? -$reddito : 0;
        $perditaLimitata = $primiTreAnni ? 0 : $perdita;
        $perditaPiena = $primiTreAnni ? $perdita : 0;
        $residuo = fn(string $tipo, int $usata, int $dellAnno) => $precedenti === null ? ($dellAnno ?: null) : (($precedenti[$tipo] - $usata + $dellAnno) ?: null);
        return ['limitata' => $limitata, 'piena' => $piena,
            'rs12' => [$perditaLimitata, $residuo('limitata', $limitata, $perditaLimitata)],
            'rs13' => [$perditaPiena, $residuo('piena', $piena, $perditaPiena)]];
    }

    /**
     * Righi RG35 o RF100 (col. 1, 2 e 3) e prospetto RS12-RS13.
     *
     * @return int reddito al netto delle perdite
     */
    private function proponiPerdite(QuadriDichiarazione $quadri, int $spettanza, string $rigo): int
    {
        $utilizzo = self::utilizzaPerdite($spettanza, $this->perditePrecedenti, $this->primiTreAnni);
        if ($this->perditePrecedenti !== null) {
            $quadri->proponi($rigo . '001', $utilizzo['limitata'] ?: null);
            $quadri->proponi($rigo . '002', $utilizzo['piena'] ?: null);
        } elseif (($spettanza > 0) && ($quadri->getImporto($rigo . '001') + $quadri->getImporto($rigo . '002') > 0)) {
            $this->avvisi[] = 'Perdite d\'impresa degli anni precedenti indicate a mano: senza la dichiarazione dell\'anno prima nella suite, compila a mano anche i residui del prospetto RS12-RS13.';
        }
        $totale = $quadri->getImporto($rigo . '001') + $quadri->getImporto($rigo . '002') + $quadri->getImporto($rigo . '02A') + $quadri->getImporto($rigo . '02B');
        $quadri->proponi($rigo . '003', $totale ?: null);
        $manuali = ($this->perditePrecedenti === null) && ($totale > 0);
        foreach (['RS012' => $utilizzo['rs12'], 'RS013' => $utilizzo['rs13']] as $prospetto => [$dellAnno, $residuo]) {
            $quadri->proponi($prospetto . '001', $dellAnno ?: null);
            if ($manuali === false) {
                $quadri->proponi($prospetto . '002', $residuo);
            }
        }
        return $spettanza - $totale;
    }

    /**
     * Perdite residue (righi RS12 e RS13, col. 2) della dichiarazione Redditi
     * dell'anno prima nella suite; null se non c'è.
     *
     * @return array{limitata: int, piena: int}|null
     */
    private function getPerditePrecedenti(Soggetto $soggetto, int $anno): ?array
    {
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::redditiPf, $anno - 1);
        if ($precedente === null) {
            return null;
        }
        $quadri = new QuadriDichiarazione($precedente, $this->dataMapper);
        return ['limitata' => $quadri->getImporto('RS012002', 1), 'piena' => $quadri->getImporto('RS013002', 1)];
    }

    /**
     * Variazione del rigo RF31 con il codice indicato: nella coppia con lo
     * stesso codice o nella prima libera.
     */
    private function proponiAltraVariazione(QuadriDichiarazione $quadri, int $codice, int $importo): void
    {
        $libera = null;
        for ($coppia = 1; $coppia <= 65; $coppia += 2) {
            $presente = $quadri->get(sprintf('RF031%03d', $coppia));
            if ((int) $presente === $codice) {
                $libera = $coppia;
                break;
            }
            if (($presente === null) && ($libera === null)) {
                $libera = $coppia;
            }
        }
        if ($libera === null) {
            $this->avvisi[] = 'Rigo RF31: nessuna coppia libera per la variazione con il codice ' . $codice . ', aggiungi un modulo a mano.';
            return;
        }
        $quadri->proponi(sprintf('RF031%03d', $libera), $importo > 0 ? $codice : null);
        $quadri->proponi(sprintf('RF031%03d', $libera + 1), $importo ?: null);
    }
}
