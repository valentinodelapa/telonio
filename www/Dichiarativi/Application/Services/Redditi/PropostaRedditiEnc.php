<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\LegameModel;
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
 * Proposta del modello Redditi Enti non commerciali ed equiparati 2026
 * (istruzioni e specifiche tecniche Redditi ENC 2026): reddito
 * dell'attività commerciale dalla contabilità dell'ente, con le righe
 * dell'attività commerciale e la parte commerciale delle righe comuni (art.
 * 144 TUIR, Contabilita\Services\ComponentiReddito), e IRES.
 *
 * - Quadro RF (contabilità ordinaria): come Redditi SC e SP
 *   (PropostaRedditiSocieta::proponiRf), perdite pregresse RF64 a mano e
 *   reddito RF65.
 * - Quadro RG (contabilità semplificata, per cassa): ricavi RG2 col. 7,
 *   componenti per rigo dei conti, reddito RG31 e RG33. Regimi forfetari:
 *   art. 145 TUIR (ricavi nel rigo RG4 col. 2 con il codice dell'attività,
 *   costi forfetari RG21 col. 2 con i coefficienti per scaglioni dei
 *   controlli delle specifiche) e legge 398/1991 (ricavi in RG2 col. 4,
 *   costi forfetari del 97 per cento in RG21 col. 1).
 * - Quadri RA e RB: terreni e fabbricati dai possessi dell'Anagrafica
 *   (PropostaTerreni e PropostaFabbricati in modalità ente), redditi con le
 *   regole di Redditi ENC (CalcoloTerreniEnc, CalcoloFabbricatiEnc): il
 *   reddito dominicale concorre anche per i terreni non affittati, il
 *   canone è per intero al netto delle spese di manutenzione ordinaria
 *   (colonna 7, a mano, fino al 15 per cento del canone).
 * - Quadro RL: righi dei redditi di capitale e diversi a mano, totali RL3,
 *   RL20 e reddito netto RL21 con le formule dei controlli delle
 *   specifiche; le ritenute del quadro (RL3 col. 3, RL4 col. 5, RL22)
 *   concorrono a quelle del rigo RN26.
 * - Quadro RN: redditi dei quadri (RN1-RN11, dei quadri compilati a mano
 *   RH, RT), reddito complessivo RN13, oneri deducibili RN15
 *   (rigo RS31 a mano), imponibile RN16, IRES al 24 per cento RN17 (le
 *   aliquote agevolate dell'art. 6 D.P.R. 601/1973, rigo RN18, a mano),
 *   detrazioni RN20 a mano, imposta netta RN21, ritenute d'acconto subite
 *   dalle partite aperte RN26, IRES dovuta RN28, crediti RU, eccedenza
 *   dell'anno prima RN30, acconti versati in Deleghe (2001 e 2002) RN33,
 *   saldo RN34 o credito RN35 e RN38, rigo RX1.
 * - Perdite d'impresa: residui della dichiarazione Redditi ENC dell'anno
 *   prima o dell'archivio delle perdite pregresse (RiportoPerdite),
 *   utilizzati nei righi RF64 e RG32 (colonne 1 e 2, prima quelle in misura
 *   limitata fino all'80 per cento del reddito); prospetto delle perdite non
 *   compensate del quadro RS (righi RS10 e RS11) con la perdita dell'anno,
 *   in misura piena nei primi tre periodi d'imposta (art. 84, c. 2, TUIR).
 * - Quadro RO: amministratori e rappresentanti dai legami dell'Anagrafica
 *   (qualifica B, amministratore non socio).
 */
class PropostaRedditiEnc
{

    public const QUADRI = ['RA', 'RB', 'RF', 'RG', 'RN', 'RO', 'RX'];

    /** Righi per modulo dei quadri RA (RA1-RA51), RB (RB1-RB34) e della sezione II del quadro RB (RB36-RB38) */
    private const RIGHI_RA = 51;
    private const RIGHI_RB = 34;
    private const RIGHI_CONTRATTI = 3;

    /** Spese di manutenzione ordinaria deducibili dal canone (art. 144, c. 1, TUIR, rinvio all'art. 90) */
    public const SPESE_MANUTENZIONE = 0.15;

    /** Costi forfetari dei ricavi dell'art. 145 TUIR per scaglione: [limite, percentuale] (codice 1 servizi, 2 altre attività) */
    public const SCAGLIONI_145 = [
        1 => [[15493.71, 0.85], [500000.0, 0.75]],
        2 => [[25822.84, 0.90], [800000.0, 0.85]],
    ];

    /** Costi forfetari delle associazioni in regime della legge 398/1991 (istruzioni, rigo RG21) */
    public const COSTI_398 = 0.97;

    /** @var string[] */
    private array $avvisi = [];

    /** @var array{limitata: int, piena: int, fonte: string}|null perdite residue alla fine dell'anno prima (RiportoPerdite) */
    private ?array $residui = null;

    /** Primi tre periodi d'imposta dall'inizio dell'attività: perdite in misura piena */
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
        $this->residui = (new RiportoPerdite($this->dataMapper))->getResidui($soggetto, $anno, ModelloDichiarativo::redditiEnc);
        $this->primiTreAnni = ($dati?->dataInizioAttivita !== null) && ($anno - (int) $dati->dataInizioAttivita->format('Y') <= 2);
        foreach (['RS001001', 'RS010001', 'RS010002', 'RS011001', 'RS011002'] as $codice) {
            $quadri->proponi($codice, null);
        }
        if ($dati === null) {
            $this->avvisi[] = 'Dati fiscali dell\'anno non trovati in Anagrafica: quadri del reddito d\'impresa da compilare a mano.';
        } elseif ($dati->partitaIva === null) {
            $this->avvisi[] = 'Ente senza partita IVA: nessuna attività commerciale, quadri RF e RG non proposti.';
        } else {
            match ($dati->regimeFiscale) {
                RegimeFiscale::ordinario => $this->proponiRf($quadri, $dati, $soggetto, $anno),
                RegimeFiscale::semplificato => $this->proponiRg($quadri, $dati, $soggetto, $anno),
                RegimeFiscale::forfetarioEnc => $this->proponiForfetario($quadri, $dati, $soggetto, $anno),
                RegimeFiscale::associazioniSportive => $this->proponi398($quadri, $dati, $soggetto, $anno),
                default => $this->avvisi[] = 'Regime ' . ($dati->regimeFiscale?->getLabel() ?? 'non indicato') . ' non previsto per gli enti non commerciali: quadro del reddito d\'impresa da compilare a mano.',
            };
        }
        $this->proponiImmobili($quadri, $soggetto, $anno);
        $this->proponiIres($quadri, $dichiarazione, $dati?->soggettoIsa ?? false);
        $this->proponiAmministratori($quadri, $soggetto, $anno);
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

    private function proponiRf(QuadriDichiarazione $quadri, DatiFiscali $dati, Soggetto $soggetto, int $anno): void
    {
        $societa = new PropostaRedditiSocieta($this->dataMapper);
        $societa->proponiRf($quadri, $dati, $soggetto, $anno, ModelloDichiarativo::redditiEnc->value);
        array_push($this->avvisi, ...$societa->getAvvisi());
        $reddito = $quadri->getImporto('RF063001');
        $perdite = $this->proponiPerdite($quadri, 'RF064', $reddito, 'RF');
        // campo positivo: in caso di perdita resta vuoto (la perdita va nel prospetto delle perdite del quadro RS)
        $quadri->proponi('RF065001', $reddito - $perdite > 0 ? $reddito - $perdite : null);
        $componenti = new ComponentiReddito($this->dataMapper);
        $componenti->perCompetenza($soggetto, $anno);
        $this->avvisoPromiscui($componenti);
    }

    /**
     * Quadro RG in contabilità semplificata (per cassa): righi dei conti
     * dell'attività commerciale, totali con le formule dei controlli delle
     * specifiche ENC (RG12, RG24, RG25, RG28, RG31, RG33).
     */
    private function proponiRg(QuadriDichiarazione $quadri, DatiFiscali $dati, Soggetto $soggetto, int $anno): void
    {
        $calcolo = new ComponentiReddito($this->dataMapper);
        $componenti = $calcolo->perCassa($soggetto, $anno, false);
        $importo = fn(string $rigo) => (int) round($componenti[$rigo] ?? 0.0);
        $impresa = new PropostaImpresa($this->dataMapper);
        $quadri->proponi('RG001001', $dati->codiceAteco?->codice);
        $quadri->proponi('RG002007', $importo('RG2') ?: null);
        $quadri->proponi('RG003001', $importo('RG3') ?: null);
        $quadri->proponi('RG006002', $importo('RG6') ?: null);
        $quadri->proponi('RG007001', $importo('RG7') ?: null);
        $impresa->proponiCodici($quadri, 'RG010', 33, $componenti, 'RG10');
        foreach (['RG15' => 'RG015001', 'RG16' => 'RG016001', 'RG18' => 'RG018001', 'RG19' => 'RG019001', 'RG20' => 'RG020001'] as $rigo => $codice) {
            $quadri->proponi($codice, $importo($rigo) ?: null);
        }
        $impresa->proponiCodici($quadri, 'RG022', 37, $componenti, 'RG22');
        array_push($this->avvisi, ...$impresa->getAvvisi());
        $this->proponiTotaliRg($quadri);
        $this->avvisoPromiscui($calcolo);
        $this->avvisi[] = 'Quadro RG: componenti per cassa dei conti dell\'attività commerciale; perdite pregresse (rigo RG32) e partecipazioni (righi RG26 e RG27) da indicare a mano.';
    }

    /**
     * Regime forfetario dell'art. 145 TUIR: ricavi dell'attività commerciale
     * (per cassa) nel rigo RG4 con il codice dell'attività (1 prestazioni
     * di servizi, 2 altre attività, proposto dal codice ATECO) e costi
     * forfetari nel rigo RG21 col. 2.
     */
    private function proponiForfetario(QuadriDichiarazione $quadri, DatiFiscali $dati, Soggetto $soggetto, int $anno): void
    {
        $calcolo = new ComponentiReddito($this->dataMapper);
        $componenti = $calcolo->perCassa($soggetto, $anno, false);
        $ricavi = (int) round(($componenti['RG2'] ?? 0.0) + ($componenti['RG3'] ?? 0.0));
        $codice = self::getCodiceAttivita145($dati->codiceAteco?->codice);
        $quadri->proponi('RG001001', $dati->codiceAteco?->codice);
        $quadri->proponi('RG004001', $codice);
        $quadri->proponi('RG004002', $ricavi ?: null);
        $quadri->proponi('RG006002', ((int) round($componenti['RG6'] ?? 0.0)) ?: null);
        $quadri->proponi('RG007001', ((int) round($componenti['RG7'] ?? 0.0)) ?: null);
        $codiceEffettivo = (int) $quadri->get('RG004001');
        $quadri->proponi('RG021002', (int) round(self::getCostiForfetari145($codiceEffettivo === 2 ? 2 : 1, (float) $quadri->getImporto('RG004002'))) ?: null);
        $this->proponiTotaliRg($quadri);
        $limite = self::SCAGLIONI_145[$codiceEffettivo === 2 ? 2 : 1][1][0];
        if ($quadri->getImporto('RG004002') > $limite) {
            $this->avvisi[] = sprintf('Quadro RG: ricavi oltre il limite del regime forfetario dell\'art. 145 TUIR (%s euro): il regime non è applicabile.', number_format($limite, 2, ',', '.'));
        }
        $this->avvisi[] = sprintf('Quadro RG: regime forfetario dell\'art. 145 TUIR, codice attività %d (%s) proposto dal codice ATECO; verifica il codice del rigo RG4.', $codiceEffettivo, $codiceEffettivo === 2 ? 'altre attività' : 'prestazioni di servizi');
        $this->avvisoPromiscui($calcolo);
    }

    /**
     * Regime forfetario della legge 398/1991 (associazioni sportive
     * dilettantistiche, pro loco e senza fine di lucro): proventi
     * commerciali in RG2 col. 4 e 7, costi forfetari del 97 per cento in
     * RG21 col. 1.
     */
    private function proponi398(QuadriDichiarazione $quadri, DatiFiscali $dati, Soggetto $soggetto, int $anno): void
    {
        $calcolo = new ComponentiReddito($this->dataMapper);
        $componenti = $calcolo->perCassa($soggetto, $anno, false);
        $ricavi = (int) round(($componenti['RG2'] ?? 0.0) + ($componenti['RG3'] ?? 0.0));
        $quadri->proponi('RG001001', $dati->codiceAteco?->codice);
        $quadri->proponi('RG002004', $ricavi ?: null);
        $quadri->proponi('RG002007', $ricavi ?: null);
        $quadri->proponi('RG006002', ((int) round($componenti['RG6'] ?? 0.0)) ?: null);
        $quadri->proponi('RG007001', ((int) round($componenti['RG7'] ?? 0.0)) ?: null);
        $quadri->proponi('RG021001', (int) round($quadri->getImporto('RG002004') * self::COSTI_398) ?: null);
        $this->proponiTotaliRg($quadri);
        if ($ricavi > 400000) {
            $this->avvisi[] = 'Quadro RG: proventi commerciali oltre 400.000 euro, limite del regime della legge 398/1991.';
        }
        $this->avvisi[] = 'Quadro RG: regime forfetario della legge 398/1991 (costi forfetari del 97 per cento dei proventi commerciali); i proventi dei due eventi annui e della raccolta pubblica di fondi esclusi dal reddito (art. 25 legge 133/1999) vanno tolti a mano.';
    }

    /**
     * Totali del quadro RG con le formule dei controlli delle specifiche
     * ENC: positivi RG12, negativi RG24 (con i costi forfetari RG21 col. 3),
     * differenza RG25, reddito lordo RG28, reddito RG31 (erogazioni
     * liberali RG29, proventi esenti RG30), perdite RG32 col. 3 e reddito
     * RG33.
     */
    private function proponiTotaliRg(QuadriDichiarazione $quadri): void
    {
        $quadri->proponi('RG021003', ($quadri->getImporto('RG021001') + $quadri->getImporto('RG021002')) ?: null);
        $positivi = $quadri->getImporto('RG002007') + $quadri->getImporto('RG003001') + $quadri->getImporto('RG004002') + $quadri->getImporto('RG005002')
                + $quadri->getImporto('RG006002') + $quadri->getImporto('RG007001') + $quadri->getImporto('RG010033');
        $quadri->proponi('RG012001', $positivi ?: null);
        $negativi = $quadri->getImporto('RG013004') + $quadri->getImporto('RG015001') + $quadri->getImporto('RG016001') + $quadri->getImporto('RG017001')
                + $quadri->getImporto('RG018001') + $quadri->getImporto('RG019001') + $quadri->getImporto('RG020001') + $quadri->getImporto('RG021003')
                + $quadri->getImporto('RG022037') + $quadri->getImporto('RG023006');
        $quadri->proponi('RG024001', $negativi ?: null);
        $differenza = $positivi - $negativi;
        $quadri->proponi('RG025001', $differenza ?: null);
        $lordo = $differenza + $quadri->getImporto('RG026002') - $quadri->getImporto('RG027002');
        if ($quadri->getImporto('RG026001') > 0) {
            $lordo = max($quadri->getImporto('RG026001'), $lordo);
        }
        $quadri->proponi('RG028002', $lordo ?: null);
        $reddito = $lordo - ($lordo > 0 ? $quadri->getImporto('RG029001') : 0);
        $reddito += $reddito < 0 ? $quadri->getImporto('RG030001') : 0;
        $quadri->proponi('RG031001', $reddito ?: null);
        $perdite = $this->proponiPerdite($quadri, 'RG032', $reddito, 'RG');
        $quadri->proponi('RG033001', $reddito >= 0 ? (($reddito - $perdite) ?: null) : null);
        $rimanenze = $quadri->getImporto('RG038002') + $quadri->getImporto('RG038003') + $quadri->getImporto('RG038004');
        $quadri->proponi('RG038001', $rimanenze > 0 ? null : 1);
    }

    /**
     * Perdite d'impresa del rigo RF64 o RG32 e prospetto delle perdite non
     * compensate (righi RS10 e RS11). Le perdite di periodo delle
     * partecipazioni (colonna 1A) e quelle della liquidazione (colonna 2A)
     * si indicano a mano; quelle dei periodi precedenti (colonne 1 e 2)
     * vengono dai residui dell'anno prima, sul reddito al netto di quelle
     * di periodo. Senza residui noti restano quelle indicate a mano.
     *
     * @return int perdite utilizzate (colonna 3)
     */
    private function proponiPerdite(QuadriDichiarazione $quadri, string $rigo, int $reddito, string $quadro): int
    {
        if ($this->residui !== null) {
            $utilizzo = PropostaImpresa::utilizzaPerdite(max(0, $reddito - $quadri->getImporto($rigo . '0A1')), $this->residui, $this->primiTreAnni);
            $quadri->proponi($rigo . '001', $utilizzo['limitata'] ?: null);
            $quadri->proponi($rigo . '002', $utilizzo['piena'] ?: null);
            if ($this->residui['limitata'] + $this->residui['piena'] > 0) {
                $this->avvisi[] = sprintf('Perdite d\'impresa pregresse dalla %s: %s euro in misura limitata e %s in misura piena; utilizzate %s e %s (rigo %s).', $this->residui['fonte'],
                        number_format($this->residui['limitata'], 0, ',', '.'), number_format($this->residui['piena'], 0, ',', '.'),
                        number_format($quadri->getImporto($rigo . '001'), 0, ',', '.'), number_format($quadri->getImporto($rigo . '002'), 0, ',', '.'), substr($rigo, 0, 2) . (int) substr($rigo, 2));
            }
        }
        $manuali = ($this->residui === null) && ($quadri->getImporto($rigo . '001') + $quadri->getImporto($rigo . '002') > 0);
        if ($manuali) {
            $this->avvisi[] = 'Perdite d\'impresa pregresse indicate a mano: senza la dichiarazione Redditi ENC dell\'anno prima nella suite né l\'archivio delle perdite pregresse dell\'ente, compila a mano anche i residui del prospetto delle perdite (righi RS10 e RS11, colonna 2).';
        }
        $perdite = $reddito > 0 ? min($reddito, $quadri->getImporto($rigo . '0A1') + $quadri->getImporto($rigo . '001') + $quadri->getImporto($rigo . '002') + $quadri->getImporto($rigo . '0A2')) : 0;
        $quadri->proponi($rigo . '003', $perdite ?: null);
        $perditaAnno = $reddito < 0 ? -$reddito : 0;
        $limitataAnno = $this->primiTreAnni ? 0 : $perditaAnno;
        $pienaAnno = $this->primiTreAnni ? $perditaAnno : 0;
        $quadri->proponi('RS010001', $limitataAnno ?: null);
        $quadri->proponi('RS011001', $pienaAnno ?: null);
        if ($manuali === false) {
            $quadri->proponi('RS010002', (($this->residui['limitata'] ?? 0) - $quadri->getImporto($rigo . '001') + $limitataAnno) ?: null);
            $quadri->proponi('RS011002', (($this->residui['piena'] ?? 0) - $quadri->getImporto($rigo . '002') + $pienaAnno) ?: null);
        }
        $prospetto = $quadri->getImporto('RS010001') + $quadri->getImporto('RS010002') + $quadri->getImporto('RS011001') + $quadri->getImporto('RS011002');
        $quadri->proponi('RS001001', $prospetto > 0 ? $quadro : null);
        return $perdite;
    }

    /**
     * Codice dell'attività del regime forfetario dell'art. 145 TUIR dal
     * codice ATECO: 2 (altre attività) per agricoltura, industria,
     * costruzioni e commercio (sezioni A-G, divisioni 01-47), 1 (prestazioni
     * di servizi) per le altre.
     */
    public static function getCodiceAttivita145(?string $codiceAteco): int
    {
        $divisione = (int) substr(str_replace('.', '', (string) $codiceAteco), 0, 2);
        return ($divisione >= 1) && ($divisione <= 47) ? 2 : 1;
    }

    /**
     * Costi forfetariamente riconosciuti dell'art. 145 TUIR (rigo RG21 col.
     * 2): percentuale dei ricavi per scaglioni, come nei controlli delle
     * specifiche (servizi: 85 per cento fino a 15.493,71 euro, 75 oltre;
     * altre attività: 90 per cento fino a 25.822,84 euro, 85 oltre).
     */
    public static function getCostiForfetari145(int $codice, float $ricavi): float
    {
        [[$soglia, $prima], [, $seconda]] = self::SCAGLIONI_145[$codice];
        // oltre la soglia: costi del primo scaglione arrotondati al centesimo (13.169,65 e 23.240,56 nei controlli) più la seconda percentuale
        return $ricavi <= $soglia ? round($ricavi * $prima, 2) : round(round($soglia * $prima, 2) + ($ricavi - $soglia) * $seconda, 2);
    }

    /**
     * Avviso della quota dei costi promiscui dell'ultimo calcolo della
     * contabilità (art. 144, c. 4, TUIR).
     */
    private function avvisoPromiscui(ComponentiReddito $componenti): void
    {
        $quota = $componenti->getQuotaPromiscui();
        if ($quota === null) {
            $this->avvisi[] = 'Nessuna riga della contabilità è dell\'attività istituzionale: indica le attività dell\'ente (pagina dei registri IVA) e l\'attività dei conti o delle righe; senza, tutte le righe sono dell\'attività commerciale.';
            return;
        }
        $this->avvisi[] = sprintf('Righe comuni senza quote deducibili al %s per cento (rapporto tra i ricavi commerciali e il totale dei ricavi e proventi, art. 144, c. 4, TUIR).', number_format($quota * 100, 2, ',', '.'));
    }

    /**
     * Quadri RA e RB dai possessi dei terreni e dei fabbricati
     * dell'Anagrafica, con i moduli che servono (51 terreni, 34 fabbricati e
     * 3 contratti della sezione II del quadro RB per modulo).
     */
    private function proponiImmobili(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $terreni = new PropostaTerreni($this->dataMapper);
        $righiRa = $terreni->getRighi($soggetto, $anno, true);
        $fabbricati = new PropostaFabbricati($this->dataMapper);
        $righiRb = $fabbricati->getRighi($soggetto, $anno, true);
        array_push($this->avvisi, ...$terreni->getAvvisi(), ...$fabbricati->getAvvisi());
        $abruzzo = array_values(array_filter($righiRb, fn(RigoFabbricato $rigo) => ($rigo->utilizzo === 14) && ($rigo->contratto !== null)));
        $moduli = max((int) ceil(count($righiRa) / self::RIGHI_RA), (int) ceil(count($righiRb) / self::RIGHI_RB), (int) ceil(count($abruzzo) / self::RIGHI_CONTRATTI), max($quadri->getModuli() ?: [1]));
        for ($modulo = 2; $modulo <= $moduli; $modulo++) {
            $quadri->usaModulo($modulo)->avviaProposta('RA', 'RB');
        }
        $this->proponiTerreni($quadri, $righiRa);
        $this->proponiFabbricati($quadri, $righiRb);
        $quadri->usaModulo(1);
    }

    /**
     * @param RigoTerreno[] $righi
     */
    private function proponiTerreni(QuadriDichiarazione $quadri, array $righi): void
    {
        $calcolo = new CalcoloTerreniEnc();
        $calcolo->calcola($righi);
        foreach ($righi as $indice => $rigo) {
            $quadri->usaModulo(intdiv($indice, self::RIGHI_RA) + 1);
            $codice = fn(int $colonna) => sprintf('RA%03d%03d', $indice % self::RIGHI_RA + 1, $colonna);
            $quadri->proponi($codice(1), $rigo->dominicale ?: null);
            $quadri->proponi($codice(2), $rigo->titolo);
            $quadri->proponi($codice(3), $rigo->agrario ?: null);
            $quadri->proponi($codice(4), $rigo->giorni);
            $quadri->proponi($codice(5), $rigo->percentuale);
            $quadri->proponi($codice(6), $rigo->canone);
            $quadri->proponi($codice(7), $rigo->casiParticolari);
            $quadri->proponi($codice(8), $rigo->continuazione ?: null);
            foreach ($rigo->redditi as $colonna => $importo) {
                $quadri->proponi($codice($colonna), $importo);
            }
            $quadri->proponi($codice(11), $rigo->produzioneVegetali);
        }
        $quadri->usaModulo(1);
        foreach ($calcolo->totali($righi) as $colonna => $importo) {
            $quadri->proponi(sprintf('RA052%03d', $colonna), $importo ?: null);
        }
    }

    /**
     * Quadro RB: le spese di manutenzione ordinaria della colonna 7 si
     * indicano a mano e riducono il canone fino al 15 per cento (non per gli
     * immobili storici); sezione II per i contratti con l'utilizzo 14.
     *
     * @param RigoFabbricato[] $righi
     */
    private function proponiFabbricati(QuadriDichiarazione $quadri, array $righi): void
    {
        $posizione = fn(int $indice) => [intdiv($indice, self::RIGHI_RB) + 1, sprintf('RB%03d', $indice % self::RIGHI_RB + 1)];
        foreach ($righi as $indice => $rigo) {
            [$modulo, $numero] = $posizione($indice);
            $spese = $quadri->isManuale($numero . '007', $modulo) ? $quadri->getImporto($numero . '007', $modulo) : 0;
            if ($spese === 0) {
                continue;
            }
            if ($rigo->storico || ($rigo->canone === null)) {
                $this->avvisi[] = sprintf('Quadro RB, rigo %d del modulo %d: spese di manutenzione ordinaria non deducibili (%s), non considerate.', $indice % self::RIGHI_RB + 1, $modulo, $rigo->storico ? 'immobile storico' : 'senza canone');
                continue;
            }
            $massimo = (int) floor($rigo->canone * self::SPESE_MANUTENZIONE);
            if ($spese > $massimo) {
                $this->avvisi[] = sprintf('Quadro RB, rigo %d del modulo %d: spese di manutenzione ordinaria oltre il 15 per cento del canone, considerate fino a %s euro.', $indice % self::RIGHI_RB + 1, $modulo, number_format($massimo, 0, ',', '.'));
            }
            $rigo->spese = min($spese, $massimo);
        }
        $calcolo = new CalcoloFabbricatiEnc();
        $calcolo->calcola($righi);
        $contratti = [];
        foreach ($righi as $indice => $rigo) {
            [$modulo, $numero] = $posizione($indice);
            $quadri->usaModulo($modulo);
            $quadri->proponi($numero . '001', $rigo->rendita ?: null);
            $quadri->proponi($numero . '002', $rigo->utilizzo);
            $quadri->proponi($numero . '003', $rigo->giorni);
            $quadri->proponi($numero . '004', $rigo->percentuale);
            $quadri->proponi($numero . '005', $rigo->storico ?: null);
            $quadri->proponi($numero . '006', $rigo->canone);
            $quadri->proponi($numero . '008', $rigo->casiParticolari);
            $quadri->proponi($numero . '009', $rigo->continuazione ?: null);
            $quadri->proponi($numero . '010', $rigo->redditi[10] ?? null);
            $quadri->proponi($numero . '011', $rigo->codiceComune);
            if (($rigo->utilizzo === 14) && ($rigo->contratto !== null)) {
                $contratti[] = ['rigo' => $indice % self::RIGHI_RB + 1, 'modulo' => $modulo, 'contratto' => $rigo->contratto];
            }
        }
        $this->proponiContratti($quadri, $contratti);
        $quadri->usaModulo(1);
        $quadri->proponi('RB035010', $calcolo->totale($righi) ?: null);
        if (count(array_filter($righi, fn(RigoFabbricato $rigo) => $rigo->utilizzo === 9)) > 0) {
            $this->avvisi[] = 'Quadro RB: i fabbricati con l\'utilizzo 9 (tra cui quelli dell\'attività istituzionale) si dichiarano salvo le esclusioni delle istruzioni (unità destinate al culto non locate, musei e biblioteche aperti al pubblico senza reddito, immobili delle organizzazioni di volontariato, degli enti filantropici e delle associazioni di promozione sociale destinati in via esclusiva all\'attività non commerciale): svuota a mano i righi esclusi.';
        }
    }

    /**
     * Sezione II del quadro RB (righi RB36-RB38): estremi dei contratti con
     * l'utilizzo 14.
     */
    private function proponiContratti(QuadriDichiarazione $quadri, array $contratti): void
    {
        foreach ($contratti as $indice => ['rigo' => $rigo, 'modulo' => $modulo, 'contratto' => $contratto]) {
            $quadri->usaModulo(intdiv($indice, self::RIGHI_CONTRATTI) + 1);
            $codice = fn(string $colonna) => sprintf('RB%03d%s', 36 + $indice % self::RIGHI_CONTRATTI, $colonna);
            $quadri->proponi($codice('001'), $rigo);
            $quadri->proponi($codice('002'), $modulo);
            if ($contratto->codiceIdentificativo !== null) {
                $quadri->proponi($codice('007'), $contratto->codiceIdentificativo);
            } elseif ($contratto->dataRegistrazione !== null) {
                $quadri->proponi($codice('003'), $contratto->dataRegistrazione->format('Y-m-d'));
                $quadri->proponi($codice('004'), $contratto->serieRegistrazione);
                [$numero, $sottonumero] = array_pad(explode('/', (string) $contratto->numeroRegistrazione, 2), 2, null);
                $quadri->proponi($codice('A05'), $numero === '' ? null : $numero);
                $quadri->proponi($codice('B05'), $sottonumero);
                $quadri->proponi($codice('006'), $contratto->codiceUfficio);
            } else {
                $quadri->proponi($codice('008'), true);
                $this->avvisi[] = 'Quadro RB, sezione II: contratto ' . $contratto->getDescrizione() . ' senza estremi di registrazione, segnato come non superiore a 30 giorni.';
            }
        }
    }

    /**
     * Totali del quadro RL con le formule dei controlli delle specifiche ENC:
     * redditi di capitale e ritenute (RL3, colonne 2 e 3), redditi diversi
     * (RL20, colonna 1) e spese (colonna 2), reddito netto RL21 se positivo.
     */
    private function proponiTotaliRl(QuadriDichiarazione $quadri): void
    {
        $quadri->proponi('RL003002', $quadri->somma('RL001002', 'RL002002') ?: null);
        $quadri->proponi('RL003003', $quadri->getImporto('RL002003') ?: null);
        $redditi = $quadri->somma('RL005001', 'RL006001', 'RL006A01', 'RL007001', 'RL008001', 'RL009001', 'RL010001', 'RL011001', 'RL012001', 'RL013001',
                'RL014001', 'RL015001', 'RL016001', 'RL017001', 'RL018001', 'RL019001');
        $spese = $quadri->somma('RL005002', 'RL006A02', 'RL006002', 'RL007002', 'RL008002', 'RL012002', 'RL013002', 'RL014002', 'RL015002', 'RL016002',
                'RL017002', 'RL018002', 'RL019002');
        $quadri->proponi('RL020001', $redditi ?: null);
        $quadri->proponi('RL020002', $spese ?: null);
        $quadri->proponi('RL021001', max(0, $redditi - $spese) ?: null);
    }

    /**
     * Quadro RN (istruzioni, par. 15.1) e rigo RX1.
     */
    private function proponiIres(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, bool $isa): void
    {
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $this->proponiTotaliRl($quadri);
        // redditi dei quadri (RH e RT compilati a mano)
        $redditi = [
            'RN001001' => $quadri->getImporto('RA052009') + $quadri->getImporto('RA052010'),
            'RN002001' => $quadri->getImporto('RB035010'),
            'RN006001' => max(0, $quadri->getImporto('RF065001')),
            'RN007001' => $quadri->getImporto('RG033001'),
            'RN008001' => $quadri->getImporto('RH016001'),
            'RN009001' => $quadri->getImporto('RL003002') + $quadri->getImporto('RL004002'),
            'RN010001' => $quadri->getImporto('RL021001'),
            'RN011001' => $quadri->getImporto('RT082001') + $quadri->getImporto('RT082002') + $quadri->getImporto('RT082003') + $quadri->getImporto('RT086001'),
        ];
        foreach ($redditi as $codice => $importo) {
            $quadri->proponi($codice, $importo ?: null);
        }
        $somma = array_sum(array_map(fn(int $rigo) => $quadri->getImporto(sprintf('RN%03d001', $rigo)), range(1, 12)));
        $complessivo = $somma - $quadri->getImporto('RN013003') - $quadri->getImporto('RN013004') - $quadri->getImporto('RN013005');
        $quadri->proponi('RN013006', $complessivo ?: null);
        $quadri->proponi('RN014003', ($quadri->getImporto('RN014001') + $quadri->getImporto('RN014002')) ?: null);
        $lordo = $complessivo + $quadri->getImporto('RN014003');
        // oneri deducibili del prospetto del quadro RS (rigo RS31, a mano) fino a concorrenza del reddito
        $quadri->proponi('RN015001', min(max(0, $lordo), $quadri->getImporto('RS031001')) ?: null);
        $quadri->proponi('RN015003', ($quadri->getImporto('RN015001') + $quadri->getImporto('RN015002')) ?: null);
        $imponibile = max(0, $lordo - $quadri->getImporto('RN015003'));
        $quadri->proponi('RN016001', $imponibile ?: null);
        $ordinario = max(0, $imponibile - $quadri->getImporto('RN018001') - $quadri->getImporto('RN018A01') - $quadri->getImporto('RN018A02'));
        $quadri->proponi('RN017001', $ordinario ?: null);
        $quadri->proponi('RN017002', (int) round($ordinario * PropostaRedditiSocieta::ALIQUOTA_IRES) ?: null);
        $imposta = $quadri->getImporto('RN017002') + $quadri->getImporto('RN018003') + $quadri->getImporto('RN018A05');
        $quadri->proponi('RN019001', $imposta ?: null);
        $netta = max(0, $imposta - $quadri->getImporto('RN019A01') - min($imposta, $quadri->getImporto('RN020006')));
        $quadri->proponi('RN021001', $netta ?: null);
        $ritenute = (int) round((new PartiteAperte($this->dataMapper))->getRitenute($soggetto, $anno));
        // ritenute d'acconto: della contabilità (partite aperte), altre ritenute (col. 1) e del quadro RL
        $quadri->proponi('RN026003', ($ritenute + $quadri->somma('RN026001', 'RL003003', 'RL004005', 'RL022001')) ?: null);
        $crediti = $quadri->getImporto('RN023001') + $quadri->getImporto('RN024001') + $quadri->getImporto('RN025010') + $quadri->getImporto('RN026003') - $quadri->getImporto('RN026002');
        $quadri->proponi('RN027001', $crediti ?: null);
        $dovuta = $netta + $quadri->getImporto('RN022001') - $crediti;
        $quadri->proponi('RN028001', $dovuta ?: null);
        // eccedenza IRES della dichiarazione dell'anno prima, se è nella suite (RX1 col. 5)
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::redditiEnc, $anno - 1);
        if ($precedente !== null) {
            $quadri->proponi('RN030001', (new QuadriDichiarazione($precedente, $this->dataMapper))->getImporto('RX001005', 1) ?: null);
        }
        $acconti = 0.0;
        foreach (VersamentiRedditi::getVersatiPerCodici($this->dataMapper, $soggetto, $anno, PropostaRedditiSocieta::ACCONTI_IRES) as $importo) {
            $acconti += $importo;
        }
        $quadri->proponi('RN033003', (int) round($acconti) ?: null);
        $totaleAcconti = $quadri->getImporto('RN033001') + $quadri->getImporto('RN033002') + $quadri->getImporto('RN033003') - $quadri->getImporto('RN033004') + $quadri->getImporto('RN033005');
        $quadri->proponi('RN033006', $totaleAcconti ?: null);
        $risultato = $dovuta - $quadri->getImporto('RN029001') - $quadri->getImporto('RN030001') + $quadri->getImporto('RN031001') + $quadri->getImporto('RN032001') - $totaleAcconti;
        $saldo = $risultato - $quadri->getImporto('RN034001') - $quadri->getImporto('RN034002');
        $quadri->proponi('RN034003', $saldo > 0 ? $saldo : null);
        $quadri->proponi('RN035001', $risultato < 0 ? -$risultato : null);
        $credito = max(0, $quadri->getImporto('RN035001') - $quadri->getImporto('RN036001') - $quadri->getImporto('RN037001'));
        $quadri->proponi('RN038001', $credito ?: null);
        $quadri->proponi('RX001001', $saldo > 0 ? $saldo : null);
        $quadri->proponi('RX001002', $credito ?: null);
        $quadri->proponi('RX001005', $credito > 0 ? ($credito - $quadri->getImporto('RX001004')) ?: null : null);
        [$primo, $secondo] = PropostaRedditiSocieta::getAccontiIres((float) max(0, $dovuta), $isa);
        if ($primo + $secondo > 0) {
            $this->avvisi[] = sprintf('Acconti IRES %d: %s euro (100%% dell\'IRES dovuta, rigo RN28, metodo storico)%s.', $anno + 1, number_format($primo + $secondo, 0, ',', '.'),
                    $primo > 0 ? sprintf(', in due rate da %s e %s euro', number_format($primo, 0, ',', '.'), number_format($secondo, 0, ',', '.')) : ' in unica rata a novembre');
        }
        $this->avvisi[] = 'Quadro RN: righi dei redditi di capitale e diversi (quadro RL, i totali si calcolano), oneri deducibili (RS31) e detraibili (RS43, rigo RN20), aliquote agevolate (RN18) e crediti d\'imposta da indicare a mano; reddito imponibile e IRES si ricalcolano alla riproposta.';
    }

    /**
     * Quadro RO: amministratori e rappresentanti in carica alla fine
     * dell'anno dai legami dell'Anagrafica (rappresentante legale,
     * amministratore unico, presidente e consiglieri), con la qualifica B
     * (amministratore non socio); componenti dell'organo di controllo
     * (qualifica C) dai legami di sindaco e revisore.
     */
    private function proponiAmministratori(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $fine = new SismaDate($anno . '-12-31');
        $persone = [];
        foreach ((new LegameModel($this->dataMapper))->getCollegati($soggetto) as $legame) {
            if ($legame->isVigenteAl($fine) === false) {
                continue;
            }
            $qualifica = match ($legame->ruolo) {
                RuoloLegame::legaleRappresentante, RuoloLegame::amministratoreUnico, RuoloLegame::presidenteCda, RuoloLegame::consigliere => 'B',
                RuoloLegame::sindaco, RuoloLegame::revisore => 'C',
                default => null,
            };
            if ($qualifica !== null) {
                $persone[$legame->soggettoCollegato->codiceFiscale] ??= [$legame->soggettoCollegato, $qualifica];
            }
        }
        $numero = 1;
        foreach (array_slice($persone, 0, 9) as [$persona, $qualifica]) {
            $rigo = sprintf('RO%03d', $numero++);
            $quadri->proponi($rigo . '001', $persona->codiceFiscale);
            if ($persona->isPersonaFisica()) {
                [$luogo, $sigla] = DatiContribuente::getLuogoNascita($persona);
                $quadri->proponi($rigo . '02A', mb_strtoupper((string) $persona->cognome));
                $quadri->proponi($rigo . '02B', mb_strtoupper((string) $persona->nome));
                $quadri->proponi($rigo . '003', $persona->dataNascita?->format('Y-m-d'));
                $quadri->proponi($rigo . '004', $persona->sesso?->value);
                $quadri->proponi($rigo . '005', $luogo === null ? null : mb_strtoupper($luogo));
                $quadri->proponi($rigo . '006', in_array($sigla, ['EE', '', null], true) ? null : $sigla);
            } else {
                $quadri->proponi($rigo . '02A', mb_strtoupper($persona->nominativo));
            }
            $quadri->proponi($rigo . '007', $qualifica);
        }
        if (count($persone) > 9) {
            $this->avvisi[] = 'Quadro RO: più di nove amministratori e componenti dell\'organo di controllo, i successivi vanno indicati a mano in un altro modulo.';
        }
        if (count($persone) === 0) {
            $this->avvisi[] = 'Quadro RO: nessun amministratore nei legami dell\'Anagrafica (rappresentante legale, presidente, consiglieri).';
        }
    }

    /**
     * Riepilogo per la scheda della dichiarazione (importi non nulli).
     *
     * @return array<string, int>
     */
    public static function getRiepilogo(QuadriDichiarazione $quadri): array
    {
        return array_filter([
            'Reddito d\'impresa (RF65)' => $quadri->getImporto('RF065001', 1),
            'Reddito d\'impresa (RG33)' => $quadri->getImporto('RG033001', 1),
            'Reddito complessivo (RN13)' => $quadri->getImporto('RN013006', 1),
            'Reddito imponibile (RN16)' => $quadri->getImporto('RN016001', 1),
            'IRES (RN19)' => $quadri->getImporto('RN019001', 1),
            'IRES dovuta (RN28)' => $quadri->getImporto('RN028001', 1),
            'IRES a debito (RX1, col. 1)' => $quadri->getImporto('RX001001', 1),
            'IRES a credito (RX1, col. 2)' => $quadri->getImporto('RX001002', 1),
        ]);
    }
}
