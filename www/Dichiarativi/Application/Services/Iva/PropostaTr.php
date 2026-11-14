<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Enumerations\EsigibilitaIva;
use Contabilita\Application\Enumerations\NaturaIva;
use Contabilita\Application\Enumerations\QualificaOperazione;
use Contabilita\Application\Enumerations\TipoRegistroIva;
use Contabilita\Application\Models\GruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\ProrataIvaModel;
use Contabilita\Application\Models\RigaContabileModel;
use Contabilita\Application\Models\RigaIvaModel;
use Contabilita\Application\Services\AttivitaSeparate;
use Contabilita\Application\Services\CalcoloLiquidazioneIva;
use Contabilita\Application\Services\LiquidazioneGruppo;
use Contabilita\Application\Services\PeriodiIva;
use Contabilita\Application\Services\RegimeIva;
use Contabilita\Application\Services\VentilazioneCorrispettivi;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Entities\ModuloDichiarazione;
use Dichiarativi\Application\Enumerations\TipoModulo;
use Dichiarativi\Application\Models\ModuloDichiarazioneModel;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta del modello IVA TR (richiesta di rimborso o utilizzo in
 * compensazione del credito IVA trimestrale, art. 38-bis, c. 2, D.P.R.
 * 633/1972; art. 8 D.P.R. 542/1999; istruzioni e specifiche IVT25) dai
 * registri del trimestre:
 *
 * - TA: operazioni attive esigibili nel trimestre per aliquota (con
 *   l'imposta calcolata e la differenza con i registri nel TA23),
 *   inversione contabile (TA20), scissione dei pagamenti (TA21), non
 *   imponibili (TA30), altre operazioni (TA31), cessioni di beni
 *   ammortizzabili (TA33); corrispettivi ventilati con la ventilazione del
 *   trimestre;
 * - TB: acquisti e importazioni imponibili per aliquota con la sola quota
 *   detraibile, la quota indetraibile nel TB30, pro-rata provvisorio
 *   (TB31-TB32, per attività con le attività separate), acquisti di beni
 *   ammortizzabili dai conti delle immobilizzazioni (TB33);
 * - TC: IVA a debito, comprese le integrazioni dell'inversione contabile
 *   (TC2), detraibile e credito del trimestre (TC7);
 * - TD: presupposti dell'art. 30, c. 2 con le formule delle istruzioni
 *   (aliquota media, non imponibili oltre il 25%, beni ammortizzabili oltre
 *   i 2/3 degli acquisti, operazioni non soggette oltre il 50%), importo in
 *   compensazione proposto (il rimborso si indica nel TD6), erogazione
 *   prioritaria, esonero dalla garanzia, attestazione patrimoniale, visto
 *   di conformità e organo di controllo.
 *
 * La controllante dell'IVA di gruppo presenta il prospetto TE con il saldo
 * trasferito da ciascuna società nel trimestre e un modulo per ogni società
 * con i requisiti (rigo TD9); le controllate non presentano il modello.
 */
class PropostaTr
{

    /** Credito minimo del trimestre per il rimborso o la compensazione */
    public const SOGLIA = 2582.28;

    /** Righi delle aliquote prestampate nei quadri TA e TB */
    public const RIGHI_ALIQUOTA = ['4' => 1, '5' => 2, '10' => 3, '22' => 4];

    /** Lettere dei presupposti nel quadro TE per le caselle del TD */
    public const PRESUPPOSTI = ['TD001001' => 'A', 'TD002001' => 'B', 'TD003001' => 'C', 'TD004001' => 'E', 'TD005001' => 'F'];

    public const QUADRI = ['TA', 'TB', 'TC', 'TD'];

    /** Erogazione prioritaria per codice ATECO 2007 o 2025 */
    private const PRIORITA_ATECO = ['38.32.10' => '2', '38.21.20' => '2', '24.43.00' => '3', '24.42.00' => '4', '30.30.09' => '5', '59.14.00' => '7'];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[] avvisi
     */
    public function proponi(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null): array
    {
        $this->avvisi = [];
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $gruppo = (new GruppoIvaModel($this->dataMapper))->getPerControllante($dichiarazione->soggetto, $dichiarazione->anno);
        if ($gruppo !== null) {
            $this->proponiGruppo($quadri, $dichiarazione, $gruppo);
        } else {
            foreach (array_diff($quadri->getModuli(), [1]) as $soppresso) {
                $quadri->eliminaModulo($soppresso);
            }
            $quadri->usaModulo(1)->avviaProposta(...self::QUADRI);
            $dati = $this->proponiModulo($quadri, $dichiarazione->soggetto, $dichiarazione->anno, (int) $dichiarazione->trimestre, '');
            $this->proponiImporti($quadri, $dati);
            $this->proponiFirme($quadri, $dichiarazione, 1);
        }
        $quadri->usaModulo(1);
        array_push($this->avvisi, ...$quadri->getDifferenzeManuali());
        $quadri->salva();
        $dichiarazione->dataElaborazione = new SismaDateTime();
        $dichiarazione->avvisi = count($this->avvisi) > 0 ? implode("\n", $this->avvisi) : null;
        $this->dataMapper->save($dichiarazione);
        return $this->avvisi;
    }

    /**
     * Quadri TA, TB, TC e TD (sezione 1 e TD8) del modulo corrente dalle
     * righe IVA del trimestre del soggetto.
     *
     * @return array{credito: float, debito: float, ammortizzabili: float, presupposti: string[], scissione: float}
     */
    public function proponiModulo(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno, int $trimestre, string $etichetta): array
    {
        return $this->proponiPeriodo($quadri, $soggetto, $anno, PeriodiIva::getInizio(PeriodicitaIva::trimestrale, $anno, $trimestre), PeriodiIva::getFine(PeriodicitaIva::trimestrale, $anno, $trimestre), $etichetta);
    }

    /**
     * Quadri TA, TB, TC e TD del modulo corrente per un periodo qualsiasi
     * dell'anno (anche l'intero anno, per i presupposti del rimborso
     * annuale).
     *
     * @return array{credito: float, debito: float, ammortizzabili: float, presupposti: string[], scissione: float}
     */
    public function proponiPeriodo(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno, SismaDate $dal, SismaDate $al, string $etichetta): array
    {
        $righe = iterator_to_array((new RigaIvaModel($this->dataMapper))->getPerPeriodo($soggetto, $dal, $al), false);
        $attive = $passive = $inversione = [];
        $impostaAttive = $impostaPassive = $integrazioni = $indetraibile = $scissione = $impostaScissione = 0.0;
        $altre = ['TA020001' => 0.0, 'TA030001' => 0.0, 'TA031001' => 0.0, 'TA033001' => 0.0, 'TA033003' => 0.0, 'nonStabiliti' => 0.0];
        $ventilare = false;
        $acquisti = [];
        foreach ($righe as $riga) {
            $codice = $riga->codiceIva;
            if ($riga->registroIva->tipo === TipoRegistroIva::acquisti) {
                if ($codice->inversioneContabile) {
                    $integrazioni += $riga->imposta;
                }
                if (($codice->natura !== null) || ($codice->aliquota <= 0)) {
                    continue;
                }
                $detraibile = $riga->imponibile * $riga->detraibilita / 100;
                $this->accumula($passive, $codice->aliquota, $detraibile);
                $impostaPassive += $riga->getImpostaDetraibile();
                $indetraibile += $riga->imponibile - $detraibile;
                $acquisti[$riga->registrazioneContabile->id] = $riga->registrazioneContabile;
                continue;
            }
            if ($codice->ventilazione) {
                $ventilare = true;
                continue;
            }
            $esigibilita = $riga->registrazioneContabile->esigibilita;
            if (($codice->natura === null) && ($codice->aliquota > 0)) {
                if ($esigibilita === EsigibilitaIva::scissionePagamenti) {
                    $scissione += $riga->imponibile;
                    $impostaScissione += $riga->imposta;
                    continue;
                }
                if ($esigibilita->isSospesa()) {
                    $this->avvisi[] = $etichetta . 'operazioni a esigibilità differita escluse dal quadro TA (esigibili all\'incasso): verificare.';
                    continue;
                }
                $this->accumula($attive, $codice->aliquota, $riga->imponibile);
                $impostaAttive += $riga->imposta;
                if ($riga->qualifica === QualificaOperazione::beneAmmortizzabile) {
                    $altre['TA033001'] += $riga->imponibile;
                    $altre['TA033003'] += $riga->imposta;
                }
                continue;
            }
            $natura = $codice->natura?->value ?? '';
            if (str_starts_with($natura, 'N6')) {
                $altre['TA020001'] += $riga->imponibile;
                $inversione[$natura] = true;
            } elseif (str_starts_with($natura, 'N3') && ($codice->natura !== NaturaIva::nonImponibiliAltre)) {
                // rigo TA30: artt. 8, 8-bis, 9, 71, 72 e artt. 41 e 58 D.L. 331/1993; le altre non imponibili nel TA31
                $altre['TA030001'] += $riga->imponibile;
            } elseif ($natura !== 'N1') {
                $altre['TA031001'] += $riga->imponibile;
                if ($riga->qualifica === QualificaOperazione::prestazioneNonStabiliti) {
                    $altre['nonStabiliti'] += $riga->imponibile;
                }
            }
        }
        if ($ventilare) {
            try {
                foreach ((new VentilazioneCorrispettivi($this->dataMapper))->calcola($soggetto, $dal, $al)->righe as $ventilata) {
                    $this->accumula($attive, $ventilata['codice']->aliquota, $ventilata['imponibile']);
                    $impostaAttive += $ventilata['imposta'];
                }
            } catch (\DomainException $errore) {
                $this->avvisi[] = $etichetta . 'corrispettivi da ventilare non ripartiti: ' . $errore->getMessage();
            }
        }
        // TA: righi per aliquota (TA1-TA4 prestampate, TA5-TA19 con l'aliquota nella colonna 2)
        $impostaCalcolata = $this->proponiAliquote($quadri, 'TA', $attive, $etichetta);
        $quadri->proponi('TA020001', $altre['TA020001']);
        $quadri->proponi('TA021001', $scissione);
        $quadri->proponi('TA022001', array_sum($attive) + $altre['TA020001'] + $scissione);
        $quadri->proponi('TA022003', $impostaCalcolata);
        $quadri->proponi('TA023001', round($impostaAttive - $impostaCalcolata, 2));
        $quadri->proponi('TA024003', round($quadri->getDecimale('TA022003') + $quadri->getDecimale('TA023001'), 2));
        $quadri->proponi('TA030001', $altre['TA030001']);
        $quadri->proponi('TA031001', $altre['TA031001']);
        $quadri->proponi('TA032001', round($quadri->getDecimale('TA022001') + $altre['TA030001'] + $altre['TA031001'], 2));
        $quadri->proponi('TA033001', $altre['TA033001']);
        $quadri->proponi('TA033003', $altre['TA033003']);
        // TB: acquisti detraibili per aliquota
        $impostaCalcolataPassive = $this->proponiAliquote($quadri, 'TB', $passive, $etichetta);
        $quadri->proponi('TB020001', array_sum($passive));
        $quadri->proponi('TB020003', $impostaCalcolataPassive);
        $quadri->proponi('TB021001', round($impostaPassive - $impostaCalcolataPassive, 2));
        $quadri->proponi('TB022003', round($quadri->getDecimale('TB020003') + $quadri->getDecimale('TB021001'), 2));
        $quadri->proponi('TB030001', round($indetraibile, 2));
        [$ammortizzabili, $impostaAmmortizzabili] = $this->getBeniAmmortizzabili($acquisti, $righe);
        $quadri->proponi('TB033001', $ammortizzabili);
        $quadri->proponi('TB033003', $impostaAmmortizzabili);
        $detraibile = $this->getImpostaDetraibile($quadri, $soggetto, $anno, $dal, $al, $etichetta);
        $quadri->proponi('TB032001', $detraibile);
        // TC: determinazione del credito
        $quadri->proponi('TC001001', $quadri->getDecimale('TA024003'));
        $quadri->proponi('TC002001', round($integrazioni, 2));
        $quadri->proponi('TC003001', round($quadri->getDecimale('TC001001') + $quadri->getDecimale('TC002001'), 2));
        $quadri->proponi('TC004002', $quadri->getDecimale('TB032001'));
        $quadri->proponi('TC006002', round($quadri->getDecimale('TC004002') + $quadri->getDecimale('TC005002'), 2));
        $saldo = round($quadri->getDecimale('TC006002') - $quadri->getDecimale('TC003001'), 2);
        $quadri->proponi('TC007002', max(0.0, $saldo));
        $presupposti = $this->proponiPresupposti($quadri, $altre, (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $anno)?->nonResidenteIva ?? false);
        // TD8: erogazione prioritaria
        $priorita = match (true) {
            $scissione > 0 => '6',
            isset($inversione['N6.3']) => '1',
            isset($inversione['N6.7']) => '8',
            default => $this->getPrioritaAttivita($soggetto, $anno, $etichetta),
        };
        $quadri->proponi('TD008001', $priorita);
        $quadri->proponi('TD008002', $priorita === '6' ? round($impostaScissione, 2) : null);
        return ['credito' => max(0.0, $saldo), 'debito' => max(0.0, -$saldo), 'ammortizzabili' => $impostaAmmortizzabili, 'presupposti' => $presupposti, 'scissione' => $impostaScissione];
    }

    /**
     * Erogazione prioritaria per codice di attività: le istruzioni usano
     * ATECOFIN 2004 e ATECO 2007, qui con il raccordo ufficiale ISTAT verso
     * ATECO 2007 e 2025. Dove il raccordo è parziale solo un avviso.
     */
    private function getPrioritaAttivita(Soggetto $soggetto, int $anno, string $etichetta): ?string
    {
        $codice = (new RegimeIva($this->dataMapper))->getDatiFiscali($soggetto, $anno)?->codiceAteco?->codice;
        $verifica = ['30.31.00' => '5', '30.32.00' => '5', '38.31.10' => '2', '38.21.12' => '2'];
        if ($codice === null) {
            return null;
        }
        if (isset($verifica[$codice])) {
            $this->avvisi[] = sprintf('%sATECO %s: il raccordo con i codici delle istruzioni è parziale, verificare se spetta l\'erogazione prioritaria con il codice %s.', $etichetta, $codice, $verifica[$codice]);
        }
        return self::PRIORITA_ATECO[$codice] ?? null;
    }

    /**
     * Righi per aliquota del quadro (TA o TB): aliquote prestampate nei
     * righi 1-4, le altre nei righi 5-19 con l'aliquota nella colonna 2.
     *
     * @param array<string, float> $imponibili
     */
    private function proponiAliquote(QuadriDichiarazione $quadri, string $quadro, array $imponibili, string $etichetta): float
    {
        $libero = 5;
        $imposta = 0.0;
        ksort($imponibili, SORT_NUMERIC);
        foreach ($imponibili as $aliquota => $imponibile) {
            $rigo = self::RIGHI_ALIQUOTA[(string) $aliquota] ?? null;
            if ($rigo === null) {
                if ($libero > 19) {
                    $this->avvisi[] = $etichetta . 'quadro ' . $quadro . ': troppe aliquote, indicare a mano l\'aliquota ' . $aliquota . '%.';
                    continue;
                }
                $rigo = $libero++;
                $quadri->proponi(sprintf('%s%03d002', $quadro, $rigo), $aliquota);
            }
            $calcolata = round($imponibile * (float) $aliquota / 100, 2);
            $quadri->proponi(sprintf('%s%03d001', $quadro, $rigo), round($imponibile, 2));
            $quadri->proponi(sprintf('%s%03d003', $quadro, $rigo), $calcolata);
            $imposta += $calcolata;
        }
        return round($imposta, 2);
    }

    /**
     * Beni ammortizzabili acquistati nel trimestre: importi in dare sui conti
     * delle immobilizzazioni, con l'imposta detraibile in proporzione.
     *
     * @return array{0: float, 1: float}
     */
    private function getBeniAmmortizzabili(array $registrazioni, array $righe): array
    {
        $righeContabili = new RigaContabileModel($this->dataMapper);
        $imponibile = $imposta = 0.0;
        foreach ($registrazioni as $registrazione) {
            $ammortizzabile = 0.0;
            foreach ($righeContabili->getRighe($registrazione) as $rigaContabile) {
                if (PropostaIva::isImmobilizzazione($rigaContabile->conto->voceCee?->value)) {
                    $ammortizzabile += $rigaContabile->dare - $rigaContabile->avere;
                }
            }
            if ($ammortizzabile <= 0) {
                continue;
            }
            $imponibileRegistrazione = $impostaRegistrazione = 0.0;
            foreach ($righe as $riga) {
                if (($riga->registrazioneContabile->id === $registrazione->id) && ($riga->codiceIva->aliquota > 0)) {
                    $imponibileRegistrazione += $riga->imponibile;
                    $impostaRegistrazione += $riga->getImpostaDetraibile();
                }
            }
            if ($imponibileRegistrazione <= 0) {
                continue;
            }
            $quota = min(1.0, $ammortizzabile / $imponibileRegistrazione);
            $imponibile += $imponibileRegistrazione * $quota;
            $imposta += $impostaRegistrazione * $quota;
        }
        return [round($imponibile, 2), round($imposta, 2)];
    }

    /**
     * Imposta ammessa in detrazione (TB32): con il pro-rata provvisorio la
     * percentuale (TB31), con le attività separate la somma della detrazione
     * delle attività, nei regimi speciali agricoli quella del regime, con
     * l'IVA teorica delle cessioni non imponibili nel TC5.
     */
    private function getImpostaDetraibile(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno, SismaDate $dal, SismaDate $al, string $etichetta): float
    {
        $separate = new AttivitaSeparate($this->dataMapper);
        $attivita = $separate->getAttivita($soggetto, $anno);
        if ((count($attivita) === 0) && ($separate->getRegimeDetrazione($soggetto, $anno) !== null)) {
            $risultato = (new CalcoloLiquidazioneIva($this->dataMapper))->calcola($soggetto, $dal, $al);
            $quadri->proponi('TC005002', $risultato->ivaTeorica);
            return round($risultato->ivaDetraibile - $risultato->ivaTeorica, 2);
        }
        if (count($attivita) > 0) {
            $calcolo = new CalcoloLiquidazioneIva($this->dataMapper);
            $detraibile = $teorica = 0.0;
            foreach ($attivita as $singola) {
                $risultato = $calcolo->calcola($soggetto, $dal, $al, $singola);
                $detraibile += $risultato->ivaDetraibile - $risultato->ivaTeorica;
                $teorica += $risultato->ivaTeorica;
            }
            $quadri->proponi('TC005002', round($teorica, 2));
            $this->avvisi[] = $etichetta . 'attività separate: rigo TB32 con la somma dell\'imposta detraibile delle attività (con il pro-rata di ciascuna).';
            return round($detraibile, 2);
        }
        $prorata = (new ProrataIvaModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        if (($prorata !== null) && ($prorata->percentuale < 100)) {
            $quadri->proponi('TB031001', $prorata->percentuale);
            return $prorata->applica($quadri->getDecimale('TB022003'));
        }
        return $quadri->getDecimale('TB022003');
    }

    /**
     * Sezione 1 del quadro TD con le formule delle istruzioni.
     *
     * @return string[] caselle dei presupposti barrate
     */
    private function proponiPresupposti(QuadriDichiarazione $quadri, array $altre, bool $nonResidente): array
    {
        $presupposti = [];
        // aliquota media delle operazioni attive maggiorata del 10% e delle passive, alla seconda cifra decimale
        $baseAttive = $quadri->getDecimale('TA022001') - $quadri->getDecimale('TA033001');
        $basePassive = $quadri->getDecimale('TB020001') - $quadri->getDecimale('TB033001');
        if (($baseAttive > 0) && ($basePassive > 0)) {
            $mediaAttive = round(($quadri->getDecimale('TA022003') + $quadri->getDecimale('TA023002') - $quadri->getDecimale('TA033003')) * 100 * 1.1 / $baseAttive, 2);
            $mediaPassive = round(($quadri->getDecimale('TB020003') + $quadri->getDecimale('TB021002') - $quadri->getDecimale('TB033003')) * 100 / $basePassive, 2);
            if ($mediaPassive > $mediaAttive) {
                $quadri->proponi('TD001001', true);
                $quadri->proponi('TD001002', $mediaAttive);
                $quadri->proponi('TD001003', $mediaPassive);
                $presupposti[] = 'TD001001';
            }
        }
        // operazioni non imponibili oltre il 25% del totale, percentuale arrotondata all'unità superiore
        $totale = $quadri->getDecimale('TA032001');
        if (($totale > 0) && ($altre['TA030001'] > 0)) {
            $percentuale = (int) ceil(round($altre['TA030001'] * 100 / $totale, 6));
            if ($percentuale > 25) {
                $quadri->proponi('TD002001', true);
                $quadri->proponi('TD002002', $percentuale);
                $presupposti[] = 'TD002001';
            }
        }
        // acquisti di beni ammortizzabili oltre i 2/3 degli acquisti imponibili
        if (($quadri->getDecimale('TB033001') > 0) && ($quadri->getDecimale('TB033001') > $quadri->getDecimale('TB020001') * 2 / 3)) {
            $quadri->proponi('TD003001', true);
            $presupposti[] = 'TD003001';
        }
        // prestazioni dell'art. 30, c. 2, lett. d) verso soggetti non stabiliti oltre il 50%, dalla qualifica delle righe IVA
        if (($totale > 0) && ($altre['nonStabiliti'] > $totale / 2)) {
            $quadri->proponi('TD005001', true);
            $presupposti[] = 'TD005001';
        }
        // non residenti identificati direttamente o con il rappresentante fiscale (art. 30, c. 2, lett. e)
        if ($nonResidente) {
            $quadri->proponi('TD004001', true);
            $presupposti[] = 'TD004001';
        }
        // casella barrata a mano: il presupposto vale comunque
        foreach (array_keys(self::PRESUPPOSTI) as $codice) {
            if (($quadri->get($codice) !== null) && (in_array($codice, $presupposti, true) === false)) {
                $presupposti[] = $codice;
            }
        }
        return $presupposti;
    }

    /**
     * Rimborso e compensazione (TD6, TD7) del modello di una società non in
     * gruppo: in mancanza di indicazioni il credito si propone in
     * compensazione (con il solo presupposto del TD3 nei limiti dell'imposta
     * sui beni ammortizzabili).
     *
     * @param array{credito: float, ammortizzabili: float, presupposti: string[]} $dati
     */
    private function proponiImporti(QuadriDichiarazione $quadri, array $dati): void
    {
        $limite = $dati['presupposti'] === ['TD003001'] ? min($dati['credito'], $dati['ammortizzabili']) : $dati['credito'];
        if ($dati['credito'] <= self::SOGLIA) {
            $this->avvisi[] = sprintf('Credito del trimestre di € %s non superiore a € 2.582,28: il modello IVA TR non si può presentare.', number_format($dati['credito'], 2, ',', '.'));
            return;
        }
        if (count($dati['presupposti']) === 0) {
            $this->avvisi[] = 'Nessun presupposto dell\'art. 30, c. 2 risulta dai registri del trimestre: il credito trimestrale non si può chiedere a rimborso né compensare (barrare a mano il presupposto se ricorre, es. soggetti non residenti).';
            return;
        }
        if ($quadri->isManuale('TD006001') || $quadri->isManuale('TD007001')) {
            return;
        }
        $quadri->proponi('TD007001', $limite);
        $this->avvisi[] = sprintf('Credito di € %s proposto in compensazione (TD7): per chiederlo a rimborso indicarlo nel TD6.', number_format($limite, 2, ',', '.'));
    }

    /**
     * Esonero dalla garanzia, attestazione patrimoniale, visto di
     * conformità e sottoscrizione dell'organo di controllo (TD8).
     */
    private function proponiFirme(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, int $modulo): void
    {
        $sottoscrizioni = isset($dichiarazione->id) ? (new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione) : [];
        $esonero = $dichiarazione->getCodiceEsoneroGaranzie(['visto' => '1', 'collaborativo' => '4', 'isa' => '5', 'concordato' => '6'], count($sottoscrizioni) > 0);
        // nelle situazioni di rischio il visto con l'attestazione non esonera dalla garanzia
        $quadri->proponi('TD008003', ($esonero === '1') && (count((new RimborsoIva($this->dataMapper))->getRischi($dichiarazione)) > 0) ? null : $esonero, $modulo);
        if ($dichiarazione->attestazionePatrimoniale) {
            foreach (['TD008004', 'TD00804A', 'TD00804B', 'TD00804C'] as $codice) {
                $quadri->proponi($codice, true, $modulo);
            }
        }
        if ($dichiarazione->vistoProfessionista !== null) {
            $quadri->proponi('TD008007', $dichiarazione->vistoProfessionista->codiceFiscale, $modulo);
            $quadri->proponi('TD008008', true, $modulo);
        } elseif ($dichiarazione->vistoCaf !== null) {
            $quadri->proponi('TD008005', $dichiarazione->vistoResponsabileCaf?->codiceFiscale, $modulo);
            $quadri->proponi('TD008006', $dichiarazione->vistoCaf->codiceFiscale, $modulo);
            $quadri->proponi('TD008008', true, $modulo);
        } else {
            foreach (array_slice(array_values($sottoscrizioni), 0, 5) as $indice => $sottoscrizione) {
                $rigo = 9 + $indice;
                $quadri->proponi(sprintf('TD%03d%02dA', 8, $rigo), $sottoscrizione->tipoSoggetto, $modulo);
                $quadri->proponi(sprintf('TD%03d%02dB', 8, $rigo), $sottoscrizione->soggetto->codiceFiscale, $modulo);
                if ($sottoscrizione->tipoSoggetto !== 3) {
                    $quadri->proponi(sprintf('TD%03d%02dC', 8, $rigo), true, $modulo);
                }
            }
        }
    }

    /**
     * Modello della controllante: prospetto TE con il saldo trasferito da
     * ogni società nel trimestre e un modulo (Mod. n. 1, 2, ...) per ogni
     * società in possesso dei requisiti, con la partita IVA nel TD9.
     */
    private function proponiGruppo(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, GruppoIva $gruppo): void
    {
        $anno = $dichiarazione->anno;
        $trimestre = (int) $dichiarazione->trimestre;
        $servizio = new LiquidazioneGruppo($this->dataMapper);
        $regime = new RegimeIva($this->dataMapper);
        $liquidazioni = new LiquidazioneIvaModel($this->dataMapper);
        $moduli = new ModuloDichiarazioneModel($this->dataMapper);
        $esistenti = [];
        foreach ($moduli->getModuli($dichiarazione) as $modulo) {
            $esistenti[$modulo->soggettoFonte?->id] = $modulo;
        }
        $quadri->usaModulo(1)->avviaProposta('TE');
        $rigo = 0;
        $crediti = $debiti = $creditiModuli = 0.0;
        $numero = 0;
        $conRequisiti = [];
        foreach ($servizio->getSocieta($gruppo, $anno) as ['soggetto' => $societa, 'partecipante' => $partecipante]) {
            $periodicita = $regime->getPeriodicita($societa, $anno);
            $periodi = $periodicita === PeriodicitaIva::mensile ? range($trimestre * 3 - 2, $trimestre * 3) : [$trimestre];
            if (($partecipante !== null) && ($partecipante->isPeriodoControllato($anno, $periodicita, end($periodi)) === false)) {
                continue;
            }
            // saldo trasferito alla controllante nel trimestre
            $saldo = 0.0;
            foreach ($periodi as $periodo) {
                $saldo += $liquidazioni->getPerPeriodo($societa, $anno, $periodo)?->getSaldoTrasferito() ?? 0.0;
            }
            $separata = $periodicita === PeriodicitaIva::mensile ? $liquidazioni->getPerPeriodo($societa, $anno, $trimestre, true) : null;
            $saldo += $separata?->getSaldoTrasferito() ?? 0.0;
            $rigo++;
            [$prospetto, $riga] = PropostaGruppoIva::getPosizione($rigo - 1, 39, 1);
            $quadri->proponi(sprintf('TE%03d001', $riga), $societa->partitaIva, $prospetto);
            $quadri->proponi(sprintf('TE%03d005', $riga), max(0.0, -round($saldo, 2)), $prospetto);
            $quadri->proponi(sprintf('TE%03d006', $riga), max(0.0, round($saldo, 2)), $prospetto);
            $crediti += max(0.0, -$saldo);
            $debiti += max(0.0, $saldo);
            // modulo della società con il credito del trimestre e i requisiti
            // prova del modulo (senza avvisi né salvataggio) per il credito e i requisiti della società
            $prova = clone $quadri;
            $numeroProva = 900 + $rigo;
            $prova->usaModulo($numeroProva);
            $avvisi = $this->avvisi;
            $dati = $this->proponiModulo($prova, $societa, $anno, $trimestre, $societa->nominativo . ': ');
            $this->avvisi = $avvisi;
            if (($dati['credito'] <= 0) || (count($dati['presupposti']) === 0)) {
                continue;
            }
            $numero++;
            $modulo = $esistenti[$societa->id] ?? new ModuloDichiarazione($this->dataMapper);
            unset($esistenti[$societa->id]);
            $modulo->dichiarazione = $dichiarazione;
            $modulo->numero = $numero;
            $modulo->tipo = TipoModulo::societaGruppo;
            $modulo->soggettoFonte = $societa;
            $modulo->descrizione = $societa->nominativo;
            $conRequisiti[$numero] = [$modulo, $societa, $dati];
            $quadri->proponi(sprintf('TE%03d002', $riga), implode('', array_map(fn(string $codice) => self::PRESUPPOSTI[$codice], $dati['presupposti'])), $prospetto);
            $quadri->proponi(sprintf('TE%03d003', $riga), $prova->get('TD008001', $numeroProva), $prospetto);
            $creditiModuli += $dati['credito'];
        }
        // moduli delle società con i requisiti, nella numerazione del prospetto
        foreach ($esistenti as $vecchio) {
            $this->dataMapper->delete($vecchio);
        }
        foreach (array_diff($quadri->getModuli(), array_keys($conRequisiti), [1]) as $soppresso) {
            $quadri->eliminaModulo($soppresso, 'TE');
        }
        foreach ($conRequisiti as $numero => [$modulo, $societa, $dati]) {
            $modulo->numero = 1000 + $numero;
            $this->dataMapper->save($modulo);
        }
        foreach ($conRequisiti as $numero => [$modulo, $societa, $dati]) {
            $modulo->numero = $numero;
            $this->dataMapper->save($modulo);
            $quadri->usaModulo($numero)->avviaProposta(...self::QUADRI);
            $this->proponiModulo($quadri, $societa, $anno, $trimestre, 'Modulo ' . $numero . ' (' . $societa->nominativo . '): ');
            $quadri->proponi('TD009001', $societa->partitaIva);
            $quadri->proponi('TD006001', null);
            $quadri->proponi('TD007001', null);
            $this->proponiFirme($quadri, $dichiarazione, $numero);
        }
        $quadri->usaModulo(1);
        $quadri->proponi('TE040001', round($crediti, 2));
        $quadri->proponi('TE041001', round($debiti, 2));
        $quadri->proponi('TE042001', max(0.0, round($crediti - $debiti, 2)));
        $quadri->proponi('TE043001', round($creditiModuli, 2));
        $limite = min($quadri->getDecimale('TE042001'), $quadri->getDecimale('TE043001'));
        if ($limite <= self::SOGLIA) {
            $this->avvisi[] = sprintf('Gruppo IVA: eccedenza di credito del trimestre rimborsabile o compensabile di € %s, non superiore a € 2.582,28: il modello IVA TR non si può presentare.', number_format($limite, 2, ',', '.'));
        } elseif (($quadri->isManuale('TE050001') === false) && ($quadri->isManuale('TE051001') === false)) {
            $quadri->proponi('TE051001', $limite);
            $this->avvisi[] = sprintf('Gruppo IVA: credito di € %s proposto in compensazione (TE51), il minore tra l\'eccedenza del gruppo (TE42) e i crediti delle società con i requisiti (TE43); per il rimborso indicarlo nel TE50 e, nei moduli, nel TD9 campo 2.', number_format($limite, 2, ',', '.'));
        }
        $this->avvisi[] = 'Gruppo IVA: prospetto TE con il saldo trasferito da ogni società nel trimestre e un modulo per ogni società con i requisiti dell\'art. 30, c. 2.';
    }

    private function accumula(array &$perAliquota, float $aliquota, float $importo): void
    {
        $chiave = rtrim(rtrim(number_format($aliquota, 2, '.', ''), '0'), '.');
        $perAliquota[$chiave] = ($perAliquota[$chiave] ?? 0.0) + $importo;
    }
}
