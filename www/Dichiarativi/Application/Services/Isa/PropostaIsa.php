<?php

namespace Dichiarativi\Application\Services\Isa;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Enumerations\TipoIndirizzo;
use Anagrafica\Application\Models\IndirizzoModel;
use Anagrafica\Application\Models\LegameModel;
use Contabilita\Application\Services\BeniStrumentali;
use Contabilita\Application\Services\ComponentiReddito;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta dei quadri del modello ISA dalla dichiarazione Redditi a cui è
 * allegato:
 *
 * - frontespizio (Y): codice attività del quadro contabile di Redditi e
 *   comune del domicilio fiscale alla fine del periodo d'imposta;
 * - dati contabili d'impresa (F) dal quadro RG (contabilità semplificata) o
 *   RF (ordinaria), con le corrispondenze dei controlli di coerenza tra
 *   modelli ISA e Redditi (allegato 2 al provvedimento del 27 febbraio
 *   2026: F01 = RG2 col. 2, F03 = RG5 col. 1 o RF12 col. 1, F06 + F08 =
 *   RG13, F07 + F09 = RG38, F17 campo 4 = maggiorazioni del rigo RG22 o
 *   RF55, F20 = RG28 col. 2 o RF60 col. 2, F29 regime contabile) e gli
 *   altri righi del quadro RG con lo stesso contenuto (F02, F10, F13 campo
 *   4, F14, F15); i righi dei costi per natura dalla contabilità
 *   (ComponentiReddito per voce del conto economico CEE, con lo stesso
 *   criterio di cassa o di competenza del quadro e la parte deducibile):
 *   altri proventi (F05, voce A5), servizi (F12, B7), godimento di beni di
 *   terzi (F13, B8), accantonamenti (F16, B12 e B13), oneri diversi (F17,
 *   B14), gestione finanziaria (F18, C15 e C16; F19, C17); con la
 *   contabilità ordinaria anche ricavi (F01, A1), acquisti (F10, B6),
 *   personale (F14, B9) e ammortamenti (F15, B10); volume d'affari (F23,
 *   VE50) e IVA sulle operazioni imponibili (F25, VE26) dalla dichiarazione
 *   IVA dell'anno, se è nella suite; valore dei beni strumentali (F21) e
 *   quote di ammortamento dei beni mobili (F15 campo 2) dal registro dei
 *   cespiti (BeniStrumentali);
 * - dati contabili di lavoro autonomo (H) dal quadro RE, rigo per rigo come
 *   nei controlli di coerenza (H02-H20), e il valore dei beni strumentali in
 *   proprietà (H01) dal registro dei cespiti;
 * - personale (A): numero dei collaboratori dell'impresa familiare (A06,
 *   colonna 1) dai legami "Collaboratore familiare" vigenti nell'anno; la
 *   percentuale dell'apporto di lavoro (colonna 2) non è negli archivi;
 * - unità locali (B): numero complessivo (B00) e comune di ciascuna (i
 *   campi "Comune" del quadro, in ordine) dalle sedi operative
 *   dell'anagrafica vigenti nell'anno; gli altri dati delle unità
 *   (superfici, tipologie), diversi da un ISA all'altro, restano a mano.
 *
 * Si propongono solo i campi presenti nel modello dell'ISA. I quadri del
 * personale, delle unità locali e degli elementi specifici (A, B, C, D,
 * E) e i righi F non ricavabili da Redditi restano da compilare a mano:
 * gli avvisi lo ricordano.
 */
class PropostaIsa
{

    /** Codici degli altri componenti negativi (RG22) e delle variazioni in diminuzione (RF55) che sono maggiorazioni fiscali (F17 campo 4) */
    public const MAGGIORAZIONI_RG = [27, 28, 29, 36, 37, 38, 42, 43, 44, 47, 53, 54];
    public const MAGGIORAZIONI_RF = [50, 55, 56, 57, 58, 59, 66, 67, 75, 76, 79, 86];

    /**
     * Righi F dal quadro RG: [rigo F, campo] => codici RG da sommare.
     */
    private const DA_RG = [
        '1-1' => ['RG002002'],
        '2-1' => ['RG003001'],
        '3-1' => ['RG005001'],
        '6-1' => ['RG013002'],
        '7-1' => ['RG038003'],
        '8-1' => ['RG013001'],
        '9-1' => ['RG038002'],
        '10-1' => ['RG015001'],
        '13-4' => ['RG020001'],
        '14-1' => ['RG016001'],
        '15-1' => ['RG018001', 'RG019001'],
        '15-3' => ['RG019001'],
        '20-1' => ['RG028002'],
    ];

    /**
     * Righi H dal quadro RE: [rigo H, campo] => [codici RE da sommare, codici RE da sottrarre].
     */
    private const DA_RE = [
        '2-1' => [['RE002002'], []],
        '3-1' => [['RE003001'], []],
        '4-1' => [['RE004001'], []],
        '5-1' => [['RE005001'], []],
        '6-1' => [['RE006001'], []],
        '7-1' => [['RE007002'], []],
        '7-2' => [['RE007001'], []],
        '8-1' => [['RE008002'], []],
        '8-2' => [['RE008001'], []],
        '9-1' => [['RE009001'], []],
        '10-1' => [['RE010001'], []],
        '11-1' => [['RE011003'], ['RE011001', 'RE011002']],
        '12-1' => [['RE012001'], []],
        '13-1' => [['RE013001'], []],
        '14-1' => [['RE014001'], []],
        '15-1' => [['RE015001'], []],
        '16-1' => [['RE016003'], []],
        '17-1' => [['RE017004'], []],
        '18-1' => [['RE018001'], []],
        '19-1' => [['RE019004'], []],
        '19-2' => [['RE019001'], []],
        '19-3' => [['RE019002'], []],
        '19-4' => [['RE019003'], []],
        '20-1' => [['RE020001'], []],
    ];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Codice del campo ISA dal rigo e dal campo (es. F, 20, 1 => F0102001).
     */
    public static function codice(string $quadro, int $rigo, int $campo): string
    {
        return sprintf('%s01%03d%02d', $quadro, $rigo, $campo);
    }

    /**
     * Quadro contabile di Redditi da cui si propone il modello: RG o RF per
     * gli ISA d'impresa (quadro F), RE per quelli di lavoro autonomo
     * (quadro H); null se nessuno è compilato.
     */
    public static function getQuadroRedditi(QuadriDichiarazione $isa, QuadriDichiarazione $redditi): ?string
    {
        $compilati = $redditi->getQuadriCompilati(1);
        if ($isa->haCampo(self::codice('F', 1, 1))) {
            foreach (['RG', 'RF'] as $quadro) {
                if (in_array($quadro, $compilati, true)) {
                    return $quadro;
                }
            }
        }
        if ($isa->haCampo(self::codice('H', 2, 1)) && in_array('RE', $compilati, true)) {
            return 'RE';
        }
        return null;
    }

    /**
     * @param QuadriDichiarazione|null $redditi quadri della dichiarazione Redditi collegata (se già caricati)
     * @return string[] avvisi della proposta
     */
    public function proponi(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, bool $salva = true, ?QuadriDichiarazione $redditi = null): array
    {
        $this->avvisi = [];
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $redditi ??= $dichiarazione->dichiarazioneRedditi === null ? null : new QuadriDichiarazione($dichiarazione->dichiarazioneRedditi, $this->dataMapper);
        $quadri->usaModulo(1)->avviaProposta('Y', 'A', 'B', 'F', 'H');
        $quadroRedditi = $redditi === null ? null : self::getQuadroRedditi($quadri, $redditi);
        if ($quadroRedditi === null) {
            $this->avvisi[] = 'Nessun quadro contabile (RE, RF o RG) compilato nella dichiarazione Redditi collegata: i quadri del modello ISA vanno compilati a mano.';
        } else {
            $redditi->usaModulo(1);
            $this->proponi1($quadri, 'Y0100101', $redditi->get($quadroRedditi . '001001'));
            match ($quadroRedditi) {
                'RG', 'RF' => $this->proponiImpresa($quadri, $redditi, $quadroRedditi, $dichiarazione),
                'RE' => $this->proponiAutonomo($quadri, $redditi, $dichiarazione),
            };
            if ($quadroRedditi !== 'RE') {
                $collaboratori = $this->contaCollaboratori($dichiarazione->soggetto, $dichiarazione->anno);
                $this->proponi1($quadri, self::codice('A', 6, 1), $collaboratori ?: null);
                if ($collaboratori > 0) {
                    $this->avvisi[] = 'Personale: indicare nel rigo A06, colonna 2, la percentuale dell\'apporto di lavoro dei collaboratori dell\'impresa familiare.';
                }
            }
        }
        $domicilio = VersamentiRedditi::getDomicilio($this->dataMapper, $dichiarazione->soggetto, new \DateTimeImmutable($dichiarazione->anno . '-12-31'));
        if ($domicilio === null) {
            $this->avvisi[] = 'Domicilio fiscale al 31/12/' . $dichiarazione->anno . ' non trovato in anagrafica: indicare il comune nel frontespizio (Y).';
        }
        $this->proponi1($quadri, 'Y0100301', $domicilio?->codiceCatastale);
        $this->proponiUnitaLocali($quadri, $dichiarazione);
        // senza le variabili precalcolate importate (PrecalcolateIsa) la tipologia di precalcolo è "non disponibile"
        if (count(array_filter(array_keys($quadri->getValori()[1] ?? []), fn(string $codice) => str_starts_with($codice, 'IDF'))) === 0) {
            $this->proponi1($quadri, 'IFU00101', 3);
            $this->avvisi[] = 'Variabili precalcolate non importate: importare il file XML fornito dall\'Agenzia delle Entrate (Cassetto fiscale o richiesta dell\'intermediario), obbligatorio per il calcolo trasmesso.';
        }
        $this->avvisi[] = 'Compilare a mano i quadri del personale (A), delle unità locali (B) e degli elementi specifici dell\'attività (C, D, E), secondo le istruzioni del modello ' . $dichiarazione->isa . '.';
        array_push($this->avvisi, ...$quadri->getDifferenzeManuali());
        if ($salva) {
            $quadri->salva();
            $dichiarazione->dataElaborazione = new SismaDateTime();
            $dichiarazione->avvisi = implode("\n", array_unique($this->avvisi));
            $this->dataMapper->save($dichiarazione);
        }
        return array_values(array_unique($this->avvisi));
    }

    private function proponiImpresa(QuadriDichiarazione $quadri, QuadriDichiarazione $redditi, string $quadro, Dichiarazione $dichiarazione): void
    {
        // componenti della contabilità per voce CEE, con il criterio del quadro (RG per cassa, RF per competenza)
        $componenti = new ComponentiReddito($this->dataMapper);
        $quadro === 'RG' ? $componenti->perCassa($dichiarazione->soggetto, $dichiarazione->anno, false) : $componenti->perCompetenza($dichiarazione->soggetto, $dichiarazione->anno);
        $perVoce = $componenti->getPerVoceCee();
        $voci = function (string ...$prefissi) use ($perVoce): ?int {
            $totale = 0.0;
            foreach ($perVoce as $voce => $importo) {
                foreach ($prefissi as $prefisso) {
                    if (($voce === $prefisso) || str_starts_with($voce, $prefisso . '.')) {
                        $totale += $importo;
                    }
                }
            }
            return (int) round($totale) ?: null;
        };
        // componenti per rigo del quadro F (rigo ISA del conto o, senza, dalla voce CEE), con i campi "di cui"
        $perRigo = $componenti->getPerRigoIsa();
        $righi = fn(string $chiave): ?int => (int) round($perRigo[$chiave] ?? 0.0) ?: null;
        $maggiorazioni = $quadro === 'RG' ? self::sommaCodici($redditi, 'RG022', 32, self::MAGGIORAZIONI_RG) : self::sommaCodici($redditi, 'RF055', 38, self::MAGGIORAZIONI_RF);
        foreach (['F01', 'F02', 'F10', 'F14', 'F15'] as $chiave) {
            $this->proponi1($quadri, self::codice('F', (int) substr($chiave, 1), 1), $righi($chiave));
        }
        foreach (['F05', 'F12', 'F13', 'F16', 'F18', 'F19'] as $chiave) {
            $this->proponi1($quadri, self::codice('F', (int) substr($chiave, 1), 1), $righi($chiave));
        }
        $this->proponi1($quadri, self::codice('F', 17, 1), ((int) $righi('F17') + $maggiorazioni) ?: null);
        foreach (['F12:2', 'F13:2', 'F13:3', 'F13:4', 'F14:2', 'F14:3', 'F14:4', 'F17:2', 'F17:8'] as $chiave) {
            [$rigo, $campo] = explode(':', substr($chiave, 1));
            $this->proponi1($quadri, self::codice('F', (int) $rigo, (int) $campo), $righi($chiave));
        }
        $this->proponiIva($quadri, $dichiarazione);
        // beni strumentali dal registro dei cespiti: valore (F21) e quote dei beni mobili (F15 campo 2)
        $beni = new BeniStrumentali($this->dataMapper);
        $this->proponi1($quadri, self::codice('F', 21, 1), (int) round($beni->getValore($dichiarazione->soggetto, $dichiarazione->anno)) ?: null);
        $this->proponi1($quadri, self::codice('F', 15, 2), (int) round($beni->getQuoteBeniMobili($dichiarazione->soggetto, $dichiarazione->anno)) ?: null);
        if ($quadro === 'RG') {
            // dal quadro RG, come nella dichiarazione; ai righi dei ricavi, degli acquisti, del lavoro e degli ammortamenti si
            // aggiungono (o tolgono) i componenti dei conti che il rigo ISA sposta rispetto alla voce CEE (es. consulenze in F14)
            $spostati = ['1-1' => ['F01', ['E.A.1']], '10-1' => ['F10', ['E.B.6']], '14-1' => ['F14', ['E.B.9']], '15-1' => ['F15', ['E.B.10.a', 'E.B.10.b']]];
            foreach (self::DA_RG as $rigoCampo => $codici) {
                [$rigo, $campo] = array_map('intval', explode('-', $rigoCampo));
                $valore = $redditi->somma(...$codici);
                if (isset($spostati[$rigoCampo])) {
                    [$chiave, $prefissi] = $spostati[$rigoCampo];
                    $valore += (int) $righi($chiave) - (int) $voci(...$prefissi);
                }
                $this->proponi1($quadri, self::codice('F', $rigo, $campo), $valore ?: null);
            }
            $this->proponi1($quadri, self::codice('F', 17, 4), $maggiorazioni ?: null);
            $this->proponi1($quadri, self::codice('F', 29, 2), true);
        } else {
            $this->proponi1($quadri, self::codice('F', 3, 1), $redditi->getImporto('RF012001') ?: null);
            $this->proponi1($quadri, self::codice('F', 20, 1), $redditi->get('RF060002'));
            $this->proponi1($quadri, self::codice('F', 17, 4), $maggiorazioni ?: null);
            $this->proponi1($quadri, self::codice('F', 29, 1), true);
            $this->avvisi[] = 'Quadro F, contabilità ordinaria: indicare a mano esistenze iniziali e rimanenze finali (F06-F09).';
        }
        $this->avvisi[] = 'Quadro F proposto dal quadro ' . $quadro . ' e dalla contabilità: i righi seguono il rigo ISA dei conti (o, senza, la voce di bilancio): verificarli e completare a mano quelli che la contabilità non distingue (F04, F11, F17 campi 3 e 5-7, F21 beni in locazione finanziaria o in comodato, F24, F26-F28).';
        array_push($this->avvisi, ...array_values($componenti->getAvvisi()));
    }

    /**
     * Volume d'affari (F23) e IVA sulle operazioni imponibili (F25) dalla
     * dichiarazione IVA annuale dello stesso anno, se è nella suite.
     */
    private function proponiIva(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): void
    {
        $iva = (new DichiarazioneModel($this->dataMapper))->getPerModello($dichiarazione->soggetto, ModelloDichiarativo::iva, $dichiarazione->anno);
        if ($iva === null) {
            $this->avvisi[] = 'Nessuna dichiarazione IVA ' . ($dichiarazione->anno + 1) . ' nella suite: indicare a mano volume d\'affari e IVA del quadro F (F22-F27).';
            return;
        }
        $quadriIva = new QuadriDichiarazione($iva, $this->dataMapper);
        $this->proponi1($quadri, self::codice('F', 23, 1), $quadriIva->getImporto('VE050001', 1) ?: null);
        $this->proponi1($quadri, self::codice('F', 25, 1), max(0, $quadriIva->getImporto('VE026002', 1)) ?: null);
    }

    private function proponiAutonomo(QuadriDichiarazione $quadri, QuadriDichiarazione $redditi, Dichiarazione $dichiarazione): void
    {
        // valore dei beni strumentali in proprietà dal registro dei cespiti
        $this->proponi1($quadri, self::codice('H', 1, 1), (int) round((new BeniStrumentali($this->dataMapper))->getValore($dichiarazione->soggetto, $dichiarazione->anno, true)) ?: null);
        foreach (self::DA_RE as $rigoCampo => [$piu, $meno]) {
            [$rigo, $campo] = array_map('intval', explode('-', $rigoCampo));
            $valore = $redditi->somma(...$piu) - (count($meno) > 0 ? $redditi->somma(...$meno) : 0);
            $this->proponi1($quadri, self::codice('H', $rigo, $campo), $valore ?: null);
        }
        $this->avvisi[] = 'Quadro H proposto dal quadro RE e, per il valore dei beni strumentali (H01), dal registro dei cespiti: completare a mano gli altri dati richiesti dal modello.';
    }

    /**
     * Unità locali dalle sedi operative vigenti in qualche giorno
     * dell'anno: numero (B00) e comune di ciascuna nei campi "Comune" del
     * quadro B, in ordine di codice.
     */
    private function proponiUnitaLocali(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): void
    {
        if ($quadri->haCampo('B0100001') === false) {
            return;
        }
        $sedi = [];
        foreach ((new IndirizzoModel($this->dataMapper))->getStorico($dichiarazione->soggetto) as $indirizzo) {
            if (($indirizzo->tipoIndirizzo === TipoIndirizzo::sedeOperativa) && ($indirizzo->validoDal->format('Y-m-d') <= $dichiarazione->anno . '-12-31')
                    && (($indirizzo->validoAl === null) || ($indirizzo->validoAl->format('Y-m-d') >= $dichiarazione->anno . '-01-01'))) {
                $sedi[] = $indirizzo;
            }
        }
        if (count($sedi) === 0) {
            $this->avvisi[] = 'Nessuna sede operativa in anagrafica per l\'anno: indicare a mano le unità locali (quadro B).';
            return;
        }
        $comuni = array_values(array_filter(array_keys($quadri->getCatalogo()), fn(string $codice) => str_starts_with($codice, 'B') && ($quadri->getCampo($codice)->formato?->value === 'CAT')));
        sort($comuni);
        $this->proponi1($quadri, 'B0100001', min(count($sedi), max(1, count($comuni))));
        foreach (array_slice($sedi, 0, count($comuni)) as $indice => $sede) {
            $this->proponi1($quadri, $comuni[$indice], $sede->comune?->codiceCatastale);
        }
        if (count($sedi) > count($comuni)) {
            $this->avvisi[] = 'Sedi operative più numerose delle unità locali del modello: indicare a mano le altre (quadro B).';
        }
        $this->avvisi[] = 'Unità locali (quadro B) proposte dalle sedi operative dell\'anagrafica: completare gli altri dati di ciascuna (superfici, tipologia) secondo le istruzioni del modello.';
    }

    /**
     * Collaboratori dell'impresa familiare: soggetti con un legame
     * "Collaboratore familiare" vigente in qualche giorno dell'anno.
     */
    private function contaCollaboratori(Soggetto $soggetto, int $anno): int
    {
        $collaboratori = [];
        foreach ((new LegameModel($this->dataMapper))->getBySoggetto($soggetto) as $legame) {
            if (($legame->ruolo === RuoloLegame::collaboratoreFamiliare) && ($legame->validoDal->format('Y-m-d') <= $anno . '-12-31')
                    && (($legame->validoAl === null) || ($legame->validoAl->format('Y-m-d') >= $anno . '-01-01'))) {
                $collaboratori[$legame->soggettoCollegato->id] = true;
            }
        }
        return count($collaboratori);
    }

    /**
     * Somma degli importi delle coppie codice/importo di un rigo (es. RG22)
     * con il codice tra quelli indicati.
     */
    private static function sommaCodici(QuadriDichiarazione $redditi, string $rigo, int $coppie, array $codici): int
    {
        $totale = 0;
        for ($coppia = 0; $coppia < $coppie; $coppia++) {
            $codice = sprintf('%s%03d', $rigo, (2 * $coppia) + 1);
            if (!$redditi->haCampo($codice)) {
                break;
            }
            if (in_array((int) $redditi->get($codice), $codici, true)) {
                $totale += $redditi->getImporto(sprintf('%s%03d', $rigo, (2 * $coppia) + 2));
            }
        }
        return $totale;
    }

    /**
     * Proposta del campo se è nel modello dell'ISA.
     */
    private function proponi1(QuadriDichiarazione $quadri, string $codice, string|int|bool|null $valore): void
    {
        if ($quadri->haCampo($codice)) {
            $quadri->proponi($codice, $valore);
        }
    }
}
