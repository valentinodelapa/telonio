<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\DatiFiscali;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\TipoAttivita;
use Contabilita\Application\Models\PartecipanteGruppoIvaModel;
use Contabilita\Application\Services\RegimeIva;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\AttoAccertamentoModel;
use Dichiarativi\Application\Models\CampoModelloModel;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Rimborso annuale del credito IVA (rigo VX4 e quadro VS, campi 9-12, del
 * prospetto IVA 26 PR), dalle istruzioni della dichiarazione IVA:
 * - causale (VX4 campo 3): 1 cessazione dell'attività; 11 passaggio al
 *   regime forfetario dall'anno successivo; 2 aliquota media e 4 beni
 *   ammortizzabili dalle righe IVA dell'anno, come i presupposti del
 *   modello IVA TR; 3 operazioni non imponibili oltre il 25% e 5 operazioni
 *   non soggette prevalenti dai righi VE (oltre 2.582,28 euro di credito);
 *   6 non residenti dai dati fiscali (con lo stesso limite); 8 minore
 *   eccedenza detraibile del triennio dalle dichiarazioni dei due anni
 *   precedenti presenti nella suite, 10 se è il credito non trasferibile di
 *   chi partecipa all'IVA di gruppo; 9 se ricorrono la 8 e la 4; 13 ingresso
 *   in un Gruppo IVA dall'anno successivo; 7 produttori agricoli per l'IVA
 *   teorica delle cessioni non imponibili (VF54);
 * - erogazione prioritaria (campo 4): come nel modello IVA TR, 6 per le
 *   operazioni in scissione dei pagamenti (con l'imposta nel campo 5), 1 e
 *   8 per il reverse charge del subappalto edile e dei servizi connessi
 *   agli edifici, 2, 3, 4, 5 e 7 per codice di attività;
 * - esonero dalle garanzie (campo 7) e attestazione patrimoniale (campo
 *   10) per i rimborsi oltre 30.000 euro, salvo le situazioni di rischio
 *   dell'art. 38-bis, c. 4, in cui la garanzia è comunque dovuta.
 */
class RimborsoIva
{

    /** Credito oltre cui competono i rimborsi dell'art. 30, c. 2 */
    public const SOGLIA = 2582.28;

    /** Rimborsi oltre cui servono garanzia o visto e attestazione patrimoniale (art. 38-bis) */
    public const LIMITE_GARANZIA = 30000;

    /** Procedura semplificata e compensazioni dell'anno (art. 34, c. 1, L. 388/2000) */
    public const LIMITE_COMPENSAZIONI = 2000000;

    public const AVVISO_VERIFICA = 'Rimborso oltre 30.000 euro: verificare gli accertamenti notificati nei due anni precedenti (art. 38-bis, c. 4, lett. b) e spuntare la verifica nel frontespizio.';

    /** Causali per presupposto del modello IVA TR */
    private const CAUSALI_TR = ['TD001001' => '2', 'TD002001' => '3', 'TD005001' => '5'];

    /** @var string[] */
    private array $avvisi = [];

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
     * Causale, erogazione prioritaria, esonero e attestazione del rigo VX4
     * quando è indicato l'importo da chiedere a rimborso; altrimenti un
     * avviso se il credito si potrebbe chiedere a rimborso.
     */
    public function proponiVx4(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): void
    {
        $credito = $quadri->getImporto('VX002001', 1);
        $rimborso = $quadri->getImporto('VX004001', 1);
        if ($credito <= 0) {
            return;
        }
        $presupposti = $this->getPresupposti($dichiarazione->soggetto, $dichiarazione->anno, $credito, $quadri);
        // ingresso in un Gruppo IVA: quanto non è chiesto a rimborso si trasferisce al gruppo
        if ($presupposti['causale'] === '13') {
            $quadri->proponi('VX002002', max(0, $credito - $rimborso), 1);
        }
        if ($rimborso <= 0) {
            if ($presupposti['causale'] !== null) {
                $this->avvisi[] = sprintf('Il credito di € %s si può chiedere a rimborso (causale %s%s): indicare l\'importo nel rigo VX4.', number_format($credito, 0, ',', '.'),
                        $presupposti['causale'], $presupposti['limite'] === null ? '' : ', fino a € ' . number_format($presupposti['limite'], 0, ',', '.'));
            }
            return;
        }
        $quadri->proponi('VX004003', $presupposti['causale'], 1);
        $quadri->proponi('VX004004', $presupposti['priorita'], 1);
        $quadri->proponi('VX004005', $presupposti['priorita'] === '6' ? $presupposti['scissione'] : null, 1);
        if ($presupposti['causale'] === null) {
            $this->avvisi[] = 'Rigo VX4: nessuna causale di rimborso risulta dalla contabilità e dalle dichiarazioni nella suite; indicarla a mano (campo 3).';
        } elseif (($presupposti['limite'] !== null) && ($rimborso > $presupposti['limite'])) {
            $this->avvisi[] = sprintf('Rigo VX4: con la causale %s il rimborso compete fino a € %s.', $presupposti['causale'], number_format($presupposti['limite'], 0, ',', '.'));
        }
        array_push($this->avvisi, ...$presupposti['avvisi']);
        // oltre 30.000 euro: esonero dalla garanzia con visto e attestazione patrimoniale
        $oltreLimite = $rimborso > self::LIMITE_GARANZIA;
        $sottoscritta = $this->isSottoscritta($dichiarazione);
        $esonero = $oltreLimite ? $dichiarazione->getCodiceEsoneroGaranzie(['visto' => '1', 'collaborativo' => '4'], $sottoscritta) : null;
        $rischi = $oltreLimite ? $this->getRischi($dichiarazione) : [];
        $esoneroProposto = ($esonero === '1') && (count($rischi) > 0) ? null : $esonero;
        $quadri->proponi('VX004007', $esoneroProposto, 1);
        if (count($rischi) > 0) {
            $this->avvisi[] = 'Rimborso oltre 30.000 euro: garanzia dovuta (art. 38-bis, c. 4) per ' . implode(', ', $rischi) . '.';
        }
        if ($oltreLimite && $this->isVerificaDaFare($dichiarazione)) {
            $this->avvisi[] = self::AVVISO_VERIFICA;
        }
        // senza il quadro VK la firma dell'attestazione richiede l'esonero con il codice 1 (specifiche, VX00410D)
        $attestazione = $oltreLimite && $dichiarazione->attestazionePatrimoniale && ($dichiarazione->hasVisto() || $sottoscritta)
                && (($esoneroProposto === '1') || in_array('VK', $quadri->getQuadriCompilati(1), true));
        foreach (['VX00410A', 'VX00410B', 'VX00410C', 'VX00410D'] as $codice) {
            $quadri->proponi($codice, $attestazione ? true : null, 1);
        }
    }

    /**
     * Causale e erogazione prioritaria del rimborso di una società del
     * gruppo (quadro VS, campi 9, 11 e 12), con la sua eccedenza di credito.
     *
     * @return array{causale: ?string, limite: ?int, priorita: ?string, scissione: int, avvisi: string[]}
     */
    public function getPresuppostiSocieta(Soggetto $soggetto, int $anno, int $credito, ?QuadriDichiarazione $quadri = null): array
    {
        $presupposti = $this->getPresupposti($soggetto, $anno, $credito, $quadri);
        // nel quadro VS le causali sono quelle dell'art. 30, c. 2 (1-7)
        if (in_array($presupposti['causale'], ['1', '2', '3', '4', '5', '6', '7'], true) === false) {
            $presupposti['causale'] = $presupposti['tr'] ?? null;
        }
        return $presupposti;
    }

    /**
     * Presupposti del rimborso annuale dalle righe IVA dell'anno e dalle
     * dichiarazioni precedenti.
     *
     * @return array{causale: ?string, limite: ?int, priorita: ?string, scissione: int, avvisi: string[], tr: ?string}
     */
    public function getPresupposti(Soggetto $soggetto, int $anno, int $credito, ?QuadriDichiarazione $quadri): array
    {
        $risultato = ['causale' => null, 'limite' => null, 'priorita' => null, 'scissione' => 0, 'avvisi' => [], 'tr' => null];
        $fine = new SismaDate($anno . '-12-31');
        if (($soggetto->dataCessazione !== null) && ((int) $soggetto->dataCessazione->format('Y') === $anno) && $soggetto->isCessato($fine)) {
            $risultato['causale'] = '1';
            return $risultato;
        }
        $regime = new RegimeIva($this->dataMapper);
        $datiFiscali = $regime->getDatiFiscali($soggetto, $anno);
        $successivi = $regime->getDatiFiscali($soggetto, $anno + 1);
        if ($soggetto->isPersonaFisica() && self::isPassaggioForfetario($datiFiscali, $successivi)) {
            $risultato['causale'] = '11';
            return $risultato;
        }
        if (self::isIngressoGruppoIva($datiFiscali, $successivi)) {
            $risultato['causale'] = '13';
            return $risultato;
        }
        $prova = $this->getProva($soggetto, $anno);
        $causali = [];
        $ammortizzabili = null;
        if ($prova !== null) {
            $dati = (new PropostaTr($this->dataMapper))->proponiPeriodo($prova, $soggetto, $anno, new SismaDate($anno . '-01-01'), $fine, '');
            foreach (self::CAUSALI_TR as $presupposto => $causale) {
                if (in_array($presupposto, $dati['presupposti'], true)) {
                    $causali[] = $causale;
                }
            }
            // con la dichiarazione valgono i righi VE, come dicono le istruzioni per le causali 3 e 5
            if ($quadri !== null) {
                $causali = [...array_diff($causali, ['3', '5']), ...self::getCausaliDaiRighiVe($quadri)];
            }
            if ($dati['ammortizzabili'] > 0) {
                $ammortizzabili = (int) round($dati['ammortizzabili']);
            }
            $risultato['priorita'] = $prova->get('TD008001');
            $risultato['scissione'] = (int) round($dati['scissione']);
        } else {
            $risultato['avvisi'][] = 'Presupposti del rimborso non calcolati: manca il catalogo del modello IVA TR (eseguire sisma aggiorna-database).';
        }
        if ($datiFiscali?->nonResidenteIva ?? false) {
            $causali[] = '6';
        }
        sort($causali);
        $sopraSoglia = $credito > self::SOGLIA;
        $risultato['tr'] = $sopraSoglia ? ($causali[0] ?? ($ammortizzabili !== null ? '4' : null)) : null;
        $gruppo = (new PartecipanteGruppoIvaModel($this->dataMapper))->getPerControllata($soggetto, $anno - 1) !== null;
        $triennio = $quadri === null ? null : $this->getMinoreEccedenza($soggetto, $anno, $credito, $quadri, $gruppo);
        $teorica = 0;
        foreach ($quadri?->getModuli() ?? [] as $modulo) {
            $teorica += $quadri->getImporto('VF054002', $modulo);
        }
        if ($sopraSoglia && ($teorica > 0)) {
            // art. 34, c. 9: per i produttori agricoli prevale sulle operazioni non imponibili
            $risultato['causale'] = '7';
            $risultato['limite'] = $teorica;
        } elseif ($sopraSoglia && (count($causali) > 0)) {
            $risultato['causale'] = $causali[0];
        } elseif ($gruppo && ($triennio !== null)) {
            // credito che chi partecipa al gruppo non ha potuto trasferire
            $risultato['causale'] = '10';
            $risultato['limite'] = $triennio;
        } elseif ($sopraSoglia && ($ammortizzabili !== null)) {
            // beni ammortizzabili: nei limiti dell'imposta sugli acquisti, con la minore eccedenza del triennio codice 9
            $risultato['causale'] = $triennio !== null ? '9' : '4';
            $risultato['limite'] = $triennio !== null ? $ammortizzabili + $triennio : $ammortizzabili;
        } elseif ($triennio !== null) {
            $risultato['causale'] = '8';
            $risultato['limite'] = $triennio;
        }
        if (($risultato['causale'] !== null) && ($risultato['limite'] !== null)) {
            $risultato['limite'] = min($risultato['limite'], $credito);
        }
        return $risultato;
    }

    /**
     * Causali 3 e 5 dalla somma dei moduli: operazioni degli artt. 8, 8-bis
     * e 9 e degli artt. 41 e 58 D.L. 331/1993 (VE30 e VE31) oltre il 25%
     * della somma in valore assoluto dei righi VE40 e VE50, con la
     * percentuale arrotondata all'unità superiore; per la prevalenza delle
     * operazioni non soggette degli artt. da 7 a 7-septies (VE34) si
     * considerano in aggiunta le stesse operazioni.
     *
     * @return string[]
     */
    public static function getCausaliDaiRighiVe(QuadriDichiarazione $quadri): array
    {
        $totale = $nonImponibili = $nonSoggette = 0;
        foreach ($quadri->getModuli() ?: [1] as $modulo) {
            $totale += abs($quadri->getImporto('VE040001', $modulo)) + abs($quadri->getImporto('VE050001', $modulo));
            $nonImponibili += $quadri->getImporto('VE030001', $modulo) + $quadri->getImporto('VE031001', $modulo);
            $nonSoggette += $quadri->getImporto('VE034001', $modulo);
        }
        if ($totale <= 0) {
            return [];
        }
        $causali = [];
        if ((int) ceil(round($nonImponibili * 100 / $totale, 6)) > 25) {
            $causali[] = '3';
        }
        if (($nonSoggette > 0) && (($nonSoggette + $nonImponibili) * 2 > $totale)) {
            $causali[] = '5';
        }
        return $causali;
    }

    /**
     * Ultimo anno con l'imposta applicata nei modi ordinari prima del
     * regime forfetario (causale 11).
     */
    public static function isPassaggioForfetario(?DatiFiscali $anno, ?DatiFiscali $successivo): bool
    {
        return ($successivo?->regimeFiscale === RegimeFiscale::forfetario) && ($anno !== null) && ($anno->regimeFiscale !== RegimeFiscale::forfetario);
    }

    /**
     * Ultimo anno prima dell'ingresso in un Gruppo IVA (causale 13).
     */
    public static function isIngressoGruppoIva(?DatiFiscali $anno, ?DatiFiscali $successivo): bool
    {
        return ($successivo?->gruppoIvaArt70bis === true) && ($anno !== null) && ($anno->gruppoIvaArt70bis === false);
    }

    /**
     * Situazioni di rischio note alla suite in cui la garanzia è dovuta per
     * i rimborsi oltre 30.000 euro (art. 38-bis, c. 4): attività d'impresa
     * da meno di due anni alla richiesta, salvo le start-up innovative, e
     * cessazione dell'attività, atti di accertamento dell'archivio
     * notificati nei due anni precedenti oltre le soglie.
     *
     * @return string[]
     */
    public function getRischi(Dichiarazione $dichiarazione): array
    {
        $soggetto = $dichiarazione->soggetto;
        $datiFiscali = (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $dichiarazione->anno);
        $rischi = [];
        if (($datiFiscali?->tipoAttivita !== TipoAttivita::lavoroAutonomo) && (($datiFiscali?->startupInnovativa ?? false) === false)) {
            $inizio = $datiFiscali?->dataInizioAttivita ?? $soggetto->dataCostituzione;
            $richiesta = $dichiarazione->dataPresentazione ?? $dichiarazione->getScadenza();
            if ($inizio === null) {
                $rischi[] = 'data di inizio dell\'attività mancante nei dati fiscali, da indicare';
            } elseif ((new \DateTimeImmutable($inizio->format('Y-m-d')))->modify('+2 years')->format('Y-m-d') > $richiesta->format('Y-m-d')) {
                $rischi[] = 'attività d\'impresa da meno di due anni';
            }
        }
        if (($soggetto->dataCessazione !== null) && ((int) $soggetto->dataCessazione->format('Y') === $dichiarazione->anno)) {
            $rischi[] = 'cessazione dell\'attività';
        }
        $richiesta = $dichiarazione->dataPresentazione ?? $dichiarazione->getScadenza();
        foreach ((new AttoAccertamentoModel($this->dataMapper))->getOltreSoglia($soggetto, $richiesta) as $atto) {
            $rischi[] = sprintf('accertamento notificato il %s oltre le soglie', $atto->dataNotifica->format('d/m/Y'));
        }
        return $rischi;
    }

    /**
     * Manca la verifica degli atti notificati: non basta un archivio vuoto,
     * che può essere solo non aggiornato.
     */
    public function isVerificaDaFare(Dichiarazione $dichiarazione): bool
    {
        $richiesta = $dichiarazione->dataPresentazione ?? $dichiarazione->getScadenza();
        return ($dichiarazione->verificaAccertamenti === false) && (count((new AttoAccertamentoModel($this->dataMapper))->getOltreSoglia($dichiarazione->soggetto, $richiesta)) === 0);
    }

    /**
     * Minore eccedenza detraibile del triennio (causale 8): credito
     * riportato dai due anni precedenti (VX5 meno il credito compensato in
     * F24 nell'anno successivo, VL9) e credito dell'anno (VX2). Per l'ex
     * controllante che entra in un altro gruppo (causale 10) il credito del
     * primo anno è il VY5 del prospetto.
     */
    public function getMinoreEccedenza(Soggetto $soggetto, int $anno, int $credito, QuadriDichiarazione $quadri, bool $gruppo = false): ?int
    {
        $model = new DichiarazioneModel($this->dataMapper);
        $precedente = $model->getPerModello($soggetto, ModelloDichiarativo::iva, $anno - 1);
        $secondo = $model->getPerModello($soggetto, ModelloDichiarativo::iva, $anno - 2);
        if (($precedente === null) || ($secondo === null)) {
            return null;
        }
        $quadriPrecedente = new QuadriDichiarazione($precedente, $this->dataMapper);
        $quadriSecondo = new QuadriDichiarazione($secondo, $this->dataMapper);
        $eccedenze = [
            ($gruppo && ($quadriSecondo->getImporto('VY005001', 1) > 0) ? $quadriSecondo->getImporto('VY005001', 1) : $quadriSecondo->getImporto('VX005001', 1)) - $quadriPrecedente->getImporto('VL009001', 1),
            $quadriPrecedente->getImporto('VX005001', 1) - $quadri->getImporto('VL009001', 1),
            $credito,
        ];
        return min($eccedenze) > 0 ? min($eccedenze) : null;
    }

    /**
     * Quadri di prova del modello IVA TR (non salvati) per i presupposti
     * dell'anno.
     */
    private function getProva(Soggetto $soggetto, int $anno): ?QuadriDichiarazione
    {
        $catalogo = (new CampoModelloModel($this->dataMapper))->getCatalogo(ModelloDichiarativo::ivaTr, ModelloDichiarativo::getAnnoModello($anno, ModelloDichiarativo::ivaTr));
        if (isset($catalogo['TD001001']) === false) {
            return null;
        }
        $dichiarazione = new Dichiarazione($this->dataMapper);
        $dichiarazione->soggetto = $soggetto;
        $dichiarazione->modello = ModelloDichiarativo::ivaTr;
        $dichiarazione->anno = $anno;
        return new QuadriDichiarazione($dichiarazione, $this->dataMapper, $catalogo);
    }

    private function isSottoscritta(Dichiarazione $dichiarazione): bool
    {
        return isset($dichiarazione->id) && (count((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione)) > 0);
    }
}
