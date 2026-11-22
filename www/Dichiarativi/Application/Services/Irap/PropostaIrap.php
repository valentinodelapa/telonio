<?php

namespace Dichiarativi\Application\Services\Irap;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Contabilita\Application\Models\ContrattoLeasingModel;
use Contabilita\Application\Services\ComponentiReddito;
use Contabilita\Application\Services\Leasing;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use Dichiarativi\Application\Services\Redditi\VersamentiRedditi;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta del modello IRAP 2026 dalla contabilità (istruzioni e
 * specifiche tecniche del modello).
 *
 * Società di capitali, quadro IC, sezione I (imprese industriali e
 * commerciali, art. 5 D.Lgs. 446/1997, valore della produzione dal conto
 * economico per competenza): componenti positivi (voci A1-A5) e negativi
 * (B6, B7, B8, B10 lettere a e b, B11, B14) con i totali IC6 e IC14,
 * variazioni (IC43-IC57) inserite a mano con i totali IC52 e IC58, valore
 * della produzione lorda IC64, deduzioni (IC66-IC75) e valore netto IC76.
 *
 * Società di persone, quadro IP, sezione I (art. 5-bis, componenti
 * rilevanti per il reddito d'impresa, per cassa in contabilità
 * semplificata): ricavi (A1), costi di materie e merci (B6 e B11),
 * servizi (B7), ammortamenti (B10), canoni di locazione e noleggio (B8);
 * valore della produzione IP12, IP60, IP66, deduzioni e valore netto IP74.
 *
 * Deduzioni proposte: costo del personale dipendente (voce B9, deducibile
 * per il tempo indeterminato: da verificare) e ulteriore deduzione dell'art.
 * 11, comma 4-bis (tabella delle istruzioni; per le società di persone,
 * art. 3, c. 1, lett. b, importi aumentati).
 *
 * Enti non commerciali, quadro IE: sezione I (attività istituzionale,
 * metodo retributivo dell'art. 10, c. 1: retribuzioni della voce B9a e
 * compensi di lavoro autonomo occasionale ai percipienti con la causale CU
 * "M", delle righe dell'attività istituzionale e della parte non
 * commerciale delle righe comuni; per gli enti senza partita IVA tutta la contabilità; redditi
 * assimilati del rigo IE2 a mano), sezione II (attività commerciale in
 * contabilità ordinaria o semplificata, art. 5: voci CEE dei conti
 * commerciali e variazioni dei conti, come il quadro IC), sezione III
 * (regimi forfetari dell'art. 145 TUIR e della legge 398/1991, art. 17,
 * c. 2: reddito forfetario della dichiarazione Redditi ENC, retribuzioni e
 * interessi passivi dell'attività commerciale) e sezione VI (valore della
 * produzione netta, deduzione del personale a tempo indeterminato
 * dell'attività commerciale e ulteriore deduzione dell'art. 11, c. 4-bis).
 *
 * Quadro IR: regione del domicilio fiscale (sede legale), valore della
 * produzione (il negativo ripartito in un rigo a sé: IC64 - IC65 o
 * colonne 3 negative dei righi da IP60 a IP65) e base imponibile, codice
 * e aliquota dalla tabella delle aliquote regionali (AliquoteIrap: codice
 * 1, aliquota ordinaria della regione; codice modificabile nella colonna
 * 6), imposta lorda e netta, totale IR21, eccedenza del modello
 * IRAP dell'anno prima (IR23), acconti versati (codici 3812 e 3813, IR25),
 * imposta a debito (IR26) o a credito (IR27) da compensare (IR30).
 */
class PropostaIrap
{

    public const QUADRI = ['IC', 'IP', 'IE', 'IR'];

    /** Variazioni dei conti del quadro IC (RighiIrap) nei righi corrispondenti del quadro IE */
    private const VARIAZIONI_IE = ['IC43' => 'IE020001', 'IC44' => 'IE021001', 'IC45' => 'IE022001', 'IC46' => 'IE023001', 'IC48' => 'IE025001'];
    public const ALIQUOTA_ORDINARIA = 3.9;
    public const ACCONTI = ['3812', '3813'];

    /** Ulteriore deduzione (art. 11, c. 4-bis): base imponibile massima => deduzione; per le società di persone gli importi aumentati */
    private const DEDUZIONI = [18075991 => 8000, 18083991 => 6000, 18091991 => 4000, 18099991 => 2000];
    private const DEDUZIONI_PERSONE = [18075991 => 13000, 18083991 => 9750, 18091991 => 6500, 18099991 => 3250];

    /** @var string[] */
    private array $avvisi = [];

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
        $componenti = new ComponentiReddito($this->dataMapper);
        $persone = $soggetto->tipoSoggetto === TipoSoggetto::societaPersone;
        $ente = $dati?->enteNonCommerciale === true;
        if ($persone && ($dati?->regimeFiscale !== RegimeFiscale::ordinario)) {
            $componenti->perCassa($soggetto, $anno, false);
        } else {
            $componenti->perCompetenza($soggetto, $anno);
        }
        $voci = $componenti->getPerVoceCee();
        $voce = function (string ...$prefissi) use ($voci): int {
            $totale = 0.0;
            foreach ($voci as $codice => $importo) {
                foreach ($prefissi as $prefisso) {
                    if (($codice === $prefisso) || str_starts_with($codice, $prefisso . '.')) {
                        $totale += $importo;
                    }
                }
            }
            return (int) round($totale);
        };
        $variazioni = $persone ? [] : $componenti->getPerRigoIrap();
        if ($persone === false) {
            // quota interessi dei canoni di leasing (metodo forfetario del D.M. 24 aprile 1998): rigo IC44
            $interessi = 0.0;
            foreach ((new ContrattoLeasingModel($this->dataMapper))->getElenco($soggetto) as $contratto) {
                $interessi += Leasing::getQuotaInteressi($contratto, $anno);
            }
            if ($interessi > 0) {
                $variazioni['IC44'] = ($variazioni['IC44'] ?? 0.0) + $interessi;
                $this->avvisi[] = sprintf('Rigo IC44: quota interessi dei canoni dei contratti di leasing (%s euro, metodo forfetario del D.M. 24 aprile 1998), dall\'archivio dei contratti della contabilità.', number_format($interessi, 0, ',', '.'));
            }
        }
        $netto = match (true) {
            $ente => $this->proponiIe($quadri, $dichiarazione, $dati, $componenti, $voce, $variazioni),
            $persone => $this->proponiIp($quadri, $voce),
            default => $this->proponiIc($quadri, $voce, $variazioni),
        };
        $this->proponiIr($quadri, $dichiarazione, $netto, $dati?->soggettoIsa ?? false, AliquoteIrap::getCategoria($dati?->codiceAteco?->codice));
        array_push($this->avvisi, ...array_values($componenti->getAvvisi()));
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
     * Quadro IC, sezione I: valore della produzione netta.
     */
    private function proponiIc(QuadriDichiarazione $quadri, \Closure $voce, array $variazioni = []): int
    {
        $this->proponiVariazioniConti($quadri, $variazioni);
        $quadri->proponi('IC000001', 1);
        $positivi = ['IC001001' => $voce('E.A.1'), 'IC002001' => $voce('E.A.2'), 'IC003001' => $voce('E.A.3'), 'IC004001' => $voce('E.A.4'), 'IC005001' => $voce('E.A.5')];
        $negativi = ['IC007001' => $voce('E.B.6'), 'IC008001' => $voce('E.B.7'), 'IC009001' => $voce('E.B.8'), 'IC010001' => $voce('E.B.10.a'), 'IC011001' => $voce('E.B.10.b'),
            'IC012001' => $voce('E.B.11'), 'IC013001' => $voce('E.B.14')];
        foreach ($positivi + $negativi as $codice => $importo) {
            $quadri->proponi($codice, $importo ?: null);
        }
        $totalePositivi = array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), array_keys($positivi)));
        $totaleNegativi = array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), array_keys($negativi)));
        $quadri->proponi('IC006001', $totalePositivi ?: null);
        $quadri->proponi('IC014001', $totaleNegativi ?: null);
        // altre variazioni in aumento (coppie codice-importo IC51) e totali IC52 e IC58 con le formule dei controlli
        $altre = 0;
        for ($colonna = 2; $colonna <= 18; $colonna += 2) {
            $altre += $quadri->getImporto(sprintf('IC051%03d', $colonna));
        }
        $quadri->proponi('IC051019', $altre ?: null);
        $aumenti = $altre;
        foreach (['IC043001', 'IC044001', 'IC045001', 'IC046001', 'IC048001', 'IC049001', 'IC050002'] as $codice) {
            $aumenti += $quadri->getImporto($codice);
        }
        $quadri->proponi('IC052001', $aumenti ?: null);
        $altreDiminuzioni = 0;
        for ($colonna = 2; $colonna <= 36; $colonna += 2) {
            $altreDiminuzioni += $quadri->getImporto(sprintf('IC057%03d', $colonna));
        }
        $quadri->proponi('IC057037', $altreDiminuzioni ?: null);
        $diminuzioni = $quadri->getImporto('IC053001') + $quadri->getImporto('IC055001') + $quadri->getImporto('IC056003') + $quadri->getImporto('IC057037');
        $quadri->proponi('IC058001', $diminuzioni ?: null);
        $lordo = $totalePositivi - $totaleNegativi + $aumenti - $diminuzioni + $quadri->getImporto('IC062001') + $quadri->getImporto('IC063001');
        $quadri->proponi('IC064001', $lordo ?: null);
        $personale = $voce('E.B.9');
        $quadri->proponi('IC069001', $personale > 0 ? min($personale, max(0, $lordo)) : null);
        $deduzioni = ['IC066001', 'IC067001', 'IC069001', 'IC070001', 'IC071001', 'IC072001', 'IC073001', 'IC074001', 'IC074A01'];
        $base = $lordo - $quadri->getImporto('IC065001') - array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), $deduzioni));
        $ulteriore = min(max(0, $base), self::getUlterioreDeduzione($base, false));
        $quadri->proponi('IC075001', $ulteriore ?: null);
        $netto = max(0, $base - $ulteriore);
        $quadri->proponi('IC076001', $netto ?: null);
        if ($personale > 0) {
            $this->avvisi[] = sprintf('Quadro IC: deduzione del costo del personale dipendente (rigo IC69) proposta per l\'intero costo del personale (voce B9, %s euro): spetta per i lavoratori a tempo indeterminato; per gli altri indicare a mano le deduzioni dei righi IC66 e IC67.', number_format($personale, 0, ',', '.'));
        }
        $this->avvisi[] = 'Quadro IC: componenti dal conto economico per competenza (voci CEE); variazioni (righi da IC43 a IC57) dai conti con la variazione IRAP del piano dei conti, le altre a mano; i totali si ricalcolano alla riproposta.';
        return $netto;
    }

    /**
     * Quadro IE degli enti non commerciali: valore della produzione netta.
     */
    private function proponiIe(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, \Anagrafica\Application\Entities\DatiFiscali $dati, ComponentiReddito $componenti, \Closure $voce, array $variazioni): int
    {
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $commerciale = $dati->partitaIva !== null;
        // sezione I: senza partita IVA tutta l'attività è istituzionale
        $istituzionale = $commerciale ? $componenti->getPerVoceCeeIstituzionale() : $componenti->getPerVoceCee();
        $retribuzioni = (int) round(array_sum(array_filter($istituzionale, fn(string $codice) => ($codice === 'E.B.9.a') || str_starts_with($codice, 'E.B.9.a.'), ARRAY_FILTER_USE_KEY)));
        $quadri->proponi('IE001001', $retribuzioni ?: null);
        $quadri->proponi('IE003001', ((int) round($this->getCompensiOccasionali($soggetto, $anno, $componenti, $commerciale === false))) ?: null);
        $quadri->proponi('IE004001', ($quadri->getImporto('IE001001') + $quadri->getImporto('IE002001') + $quadri->getImporto('IE003001')) ?: null);
        $forfetario = in_array($dati->regimeFiscale, [RegimeFiscale::forfetarioEnc, RegimeFiscale::associazioniSportive], true);
        if ($commerciale && $forfetario) {
            $this->proponiIeForfetario($quadri, $soggetto, $anno, $voce);
        } elseif ($commerciale) {
            $this->proponiIeCommerciale($quadri, $voce, $variazioni);
        }
        // sezione VI: valore della produzione di ciascuna sezione (nessuna attività all'estero: colonna 3 uguale alla colonna 1)
        foreach (['IE047' => 'IE004001', 'IE048' => 'IE032001', 'IE049' => 'IE037001'] as $rigo => $sezione) {
            $valore = $quadri->getImporto($sezione);
            $quadri->proponi($rigo . '001', $valore ?: null);
            $quadri->proponi($rigo . '003', ($valore - $quadri->getImporto($rigo . '002')) ?: null);
        }
        $totale = 0;
        foreach (['IE047003', 'IE048003', 'IE049003', 'IE051003', 'IE052003'] as $codice) {
            $totale += max(0, $quadri->getImporto($codice));
        }
        $quadri->proponi('IE053001', $totale ?: null);
        // deduzione del personale a tempo indeterminato (art. 11, c. 4-octies): solo per il valore dell'attività commerciale (art. 5)
        $personale = $commerciale && ($forfetario === false) ? $voce('E.B.9') : 0;
        $quadri->proponi('IE057001', $personale > 0 ? min($personale, max(0, $quadri->getImporto('IE048003'))) ?: null : null);
        $base = $totale;
        foreach (['IE054001', 'IE055001', 'IE057001', 'IE058001', 'IE059001', 'IE059A01'] as $codice) {
            $base -= $quadri->getImporto($codice);
        }
        $ulteriore = min(max(0, $base), self::getUlterioreDeduzione($base, false));
        $quadri->proponi('IE060001', $ulteriore ?: null);
        $netto = max(0, $base - $ulteriore);
        $quadri->proponi('IE061001', $netto ?: null);
        $this->avvisi[] = sprintf('Quadro IE, sezione I (attività istituzionale): retribuzioni della voce B9a e compensi di lavoro autonomo occasionale (causale CU "M") %s; i redditi assimilati a quelli di lavoro dipendente (collaborazioni coordinate e continuative, rigo IE2) si indicano a mano.',
                $commerciale ? 'delle righe dell\'attività istituzionale e della parte non commerciale delle righe comuni' : 'di tutta la contabilità (ente senza partita IVA)');
        if ($personale > 0) {
            $this->avvisi[] = sprintf('Quadro IE: deduzione del costo del personale dipendente dell\'attività commerciale (rigo IE57) proposta per l\'intero costo (voce B9, %s euro): spetta per i lavoratori a tempo indeterminato.', number_format($personale, 0, ',', '.'));
        }
        return $netto;
    }

    /**
     * Quadro IE, sezione II (attività commerciale, art. 5): componenti del
     * conto economico delle righe commerciali, variazioni dei conti (righi
     * del quadro IC nei corrispondenti del quadro IE) e valore IE32.
     */
    private function proponiIeCommerciale(QuadriDichiarazione $quadri, \Closure $voce, array $variazioni): void
    {
        $positivi = ['IE006001' => $voce('E.A.1'), 'IE007001' => $voce('E.A.2'), 'IE008001' => $voce('E.A.3'), 'IE009001' => $voce('E.A.4'), 'IE010001' => $voce('E.A.5')];
        $negativi = ['IE012001' => $voce('E.B.6'), 'IE013001' => $voce('E.B.7'), 'IE014001' => $voce('E.B.8'), 'IE015001' => $voce('E.B.10.a'), 'IE016001' => $voce('E.B.10.b'),
            'IE017001' => $voce('E.B.11'), 'IE018001' => $voce('E.B.14')];
        foreach ($positivi + $negativi as $codice => $importo) {
            $quadri->proponi($codice, $importo ?: null);
        }
        $totalePositivi = array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), array_keys($positivi)));
        $totaleNegativi = array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), array_keys($negativi)));
        $quadri->proponi('IE011001', $totalePositivi ?: null);
        $quadri->proponi('IE019001', $totaleNegativi ?: null);
        foreach (self::VARIAZIONI_IE as $rigo => $codice) {
            $importo = (int) round($variazioni[$rigo] ?? 0);
            $quadri->proponi($codice, $importo > 0 ? $importo : null);
        }
        $this->proponiAltreVariazioni($quadri, $variazioni, ['IC51' => ['IE026', 9], 'IC57' => ['IE030', 18]]);
        if (($variazioni['IC49'] ?? 0) > 0) {
            $this->avvisi[] = 'Quadro IE: gli interessi passivi indeducibili (variazione IC49 dei conti) non hanno un rigo proprio nella sezione II: indicali tra le altre variazioni in aumento (rigo IE26) con il codice delle istruzioni.';
        }
        $altre = 0;
        for ($colonna = 2; $colonna <= 18; $colonna += 2) {
            $altre += $quadri->getImporto(sprintf('IE026%03d', $colonna));
        }
        $quadri->proponi('IE026019', $altre ?: null);
        $aumenti = $altre;
        foreach (self::VARIAZIONI_IE as $codice) {
            $aumenti += $quadri->getImporto($codice);
        }
        $quadri->proponi('IE027001', $aumenti ?: null);
        $altreDiminuzioni = 0;
        for ($colonna = 2; $colonna <= 36; $colonna += 2) {
            $altreDiminuzioni += $quadri->getImporto(sprintf('IE030%03d', $colonna));
        }
        // il totale della colonna 37 indicato dalle istruzioni non è nello schema del file: concorre solo al rigo IE31
        $diminuzioni = $quadri->getImporto('IE028001') + $altreDiminuzioni;
        $quadri->proponi('IE031001', $diminuzioni ?: null);
        $valore = $totalePositivi + $aumenti - $totaleNegativi - $diminuzioni;
        $quadri->proponi('IE032001', $valore ?: null);
        $this->avvisi[] = 'Quadro IE, sezione II (attività commerciale): componenti dal conto economico per competenza delle righe dell\'attività commerciale e parte commerciale delle righe comuni; variazioni dai conti con la variazione IRAP, le altre a mano.';
    }

    /**
     * Quadro IE, sezione III (regimi forfetari, art. 17, c. 2): reddito
     * forfetario (rigo RG31 della dichiarazione Redditi ENC dell'anno nella
     * suite), retribuzioni dell'attività commerciale (voce B9) e interessi
     * passivi (voce C17).
     */
    private function proponiIeForfetario(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno, \Closure $voce): void
    {
        $redditi = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::redditiEnc, $anno);
        if ($redditi === null) {
            $this->avvisi[] = 'Quadro IE, sezione III: dichiarazione Redditi ENC dell\'anno non trovata, reddito forfetario (rigo IE34) da indicare a mano.';
        } else {
            $quadri->proponi('IE034001', max(0, (new QuadriDichiarazione($redditi, $this->dataMapper))->getImporto('RG031001', 1)) ?: null);
        }
        $quadri->proponi('IE035001', $voce('E.B.9') ?: null);
        $quadri->proponi('IE036001', $voce('E.C.17') ?: null);
        $quadri->proponi('IE037001', ($quadri->getImporto('IE034001') + $quadri->getImporto('IE035001') + $quadri->getImporto('IE036001')) ?: null);
        $this->avvisi[] = 'Quadro IE, sezione III (regime forfetario): reddito d\'impresa forfetario, retribuzioni e compensi dell\'attività commerciale (rigo IE35: aggiungi a mano collaboratori coordinati e continuativi e lavoro autonomo occasionale) e interessi passivi.';
    }

    /**
     * Compensi di lavoro autonomo occasionale (art. 67, c. 1, lett. l, TUIR)
     * dell'attività istituzionale dell'anno: costi registrati con una
     * controparte di causale CU "M", per la parte non commerciale della riga
     * (righe istituzionali per intero, righe comuni per la parte non
     * commerciale, ComponentiReddito::getFattore); le righe commerciali non
     * concorrono.
     */
    private function getCompensiOccasionali(Soggetto $soggetto, int $anno, ComponentiReddito $componenti, bool $tuttoIstituzionale): float
    {
        $totale = 0.0;
        $movimenti = (new \Contabilita\Application\Models\RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, new SismaDate($anno . '-01-01'), new SismaDate($anno . '-12-31'));
        foreach ($movimenti as $riga) {
            if (($riga->conto->natura !== \Contabilita\Application\Enumerations\NaturaConto::costo) || ($riga->registrazioneContabile->controparte?->causaleCu !== \Contabilita\Application\Enumerations\CausaleCu::occasionale)) {
                continue;
            }
            // ente senza attività commerciale (senza partita IVA): tutte le righe sono istituzionali; altrimenti la parte non commerciale della riga
            $parte = $tuttoIstituzionale ? 1.0 : 1.0 - $componenti->getFattore($riga);
            $totale += ($riga->dare - $riga->avere) * $parte;
        }
        return $totale;
    }

    /**
     * Variazioni dei righi da IC43 a IC57 dai conti con la variazione IRAP
     * (Contabilita, RighiIrap): righi propri (IC43-IC49) e altre variazioni
     * con il codice (IC51 in aumento, IC57 in diminuzione) nella coppia con
     * lo stesso codice o nella prima libera.
     *
     * @param array<string, float> $variazioni rigo del conto => importo
     */
    private function proponiVariazioniConti(QuadriDichiarazione $quadri, array $variazioni): void
    {
        foreach (['IC43', 'IC44', 'IC45', 'IC46', 'IC48', 'IC49'] as $rigo) {
            $importo = (int) round($variazioni[$rigo] ?? 0);
            $quadri->proponi(sprintf('IC%03d001', (int) substr($rigo, 2)), $importo > 0 ? $importo : null);
        }
        $this->proponiAltreVariazioni($quadri, $variazioni, ['IC51' => ['IC051', 9], 'IC57' => ['IC057', 18]]);
        if (count($variazioni) > 0) {
            $this->avvisi[] = 'Quadro IC: variazioni dai conti con la variazione IRAP indicata nel piano dei conti (' . implode(', ', array_keys($variazioni)) . '); le altre si indicano a mano.';
        }
    }

    /**
     * Altre variazioni con il codice (in aumento e in diminuzione) nella
     * coppia con lo stesso codice o nella prima libera del rigo indicato.
     *
     * @param array<string, float> $variazioni rigo del conto => importo
     * @param array<string, array{0: string, 1: int}> $righi rigo del conto (IC51, IC57) => [prefisso del rigo del modello, coppie]
     */
    private function proponiAltreVariazioni(QuadriDichiarazione $quadri, array $variazioni, array $righi): void
    {
        foreach ($righi as $rigo => [$base, $coppie]) {
            $usate = [];
            foreach ($variazioni as $chiave => $importo) {
                if (str_starts_with($chiave, $rigo . ':') === false) {
                    continue;
                }
                $codice = (int) substr($chiave, strlen($rigo) + 1);
                $importo = (int) round($importo);
                if ($importo <= 0) {
                    continue;
                }
                $libera = null;
                for ($coppia = 1; $coppia <= $coppie; $coppia++) {
                    $campoCodice = sprintf('%s%03d', $base, $coppia * 2 - 1);
                    if (in_array($coppia, $usate, true)) {
                        continue;
                    }
                    if ((int) $quadri->get($campoCodice) === $codice) {
                        $libera = $coppia;
                        break;
                    }
                    if (($libera === null) && ($quadri->get($campoCodice) === null)) {
                        $libera = $coppia;
                    }
                }
                if ($libera === null) {
                    $this->avvisi[] = sprintf('Rigo %s: nessuna coppia libera per la variazione con il codice %d (%s euro), da indicare a mano.', $rigo, $codice, number_format($importo, 0, ',', '.'));
                    continue;
                }
                $usate[] = $libera;
                $quadri->proponi(sprintf('%s%03d', $base, $libera * 2 - 1), $codice);
                $quadri->proponi(sprintf('%s%03d', $base, $libera * 2), $importo);
            }
        }
    }

    /**
     * Quadro IP, sezione I (art. 5-bis): valore della produzione netta.
     */
    private function proponiIp(QuadriDichiarazione $quadri, \Closure $voce): int
    {
        $positivi = ['IP001001' => $voce('E.A.1')];
        $negativi = ['IP005001' => $voce('E.B.6', 'E.B.11'), 'IP006001' => $voce('E.B.7'), 'IP007001' => $voce('E.B.10.b'), 'IP008001' => $voce('E.B.10.a'), 'IP009001' => $voce('E.B.8')];
        foreach ($positivi + $negativi as $codice => $importo) {
            $quadri->proponi($codice, $importo ?: null);
        }
        $totalePositivi = $quadri->getImporto('IP001001') + $quadri->getImporto('IP002001') + $quadri->getImporto('IP003001');
        $quadri->proponi('IP004001', $totalePositivi ?: null);
        $totaleNegativi = array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), array_keys($negativi))) + $quadri->getImporto('IP010001');
        $quadri->proponi('IP010003', $totaleNegativi ?: null);
        $valore = $totalePositivi - $totaleNegativi - $quadri->getImporto('IP011001');
        $quadri->proponi('IP012001', $valore ?: null);
        $quadri->proponi('IP060001', $valore ?: null);
        $italia = $valore - $quadri->getImporto('IP060002');
        $quadri->proponi('IP060003', $italia ?: null);
        $totale = $italia + $quadri->getImporto('IP061003') + $quadri->getImporto('IP062003') + $quadri->getImporto('IP064003') + $quadri->getImporto('IP065003');
        $quadri->proponi('IP066001', $totale ?: null);
        $personale = $voce('E.B.9');
        $quadri->proponi('IP070001', $personale > 0 ? min($personale, max(0, $totale)) : null);
        $base = $totale - array_sum(array_map(fn(string $codice) => $quadri->getImporto($codice), ['IP067001', 'IP068001', 'IP070001', 'IP071001', 'IP072001', 'IP072A01']));
        $ulteriore = min(max(0, $base), self::getUlterioreDeduzione($base, true));
        $quadri->proponi('IP073001', $ulteriore ?: null);
        $netto = max(0, $base - $ulteriore);
        $quadri->proponi('IP074001', $netto ?: null);
        if ($personale > 0) {
            $this->avvisi[] = sprintf('Quadro IP: deduzione del costo del personale dipendente (rigo IP70) proposta per l\'intero costo del personale (voce B9, %s euro): spetta per i lavoratori a tempo indeterminato.', number_format($personale, 0, ',', '.'));
        }
        $this->avvisi[] = 'Quadro IP: valore della produzione con le regole del reddito d\'impresa (art. 5-bis), dalla contabilità; verificare i componenti non rilevanti ai fini IRAP.';
        return $netto;
    }

    /**
     * Ulteriore deduzione dell'art. 11, comma 4-bis, per la base imponibile
     * (in euro); per le società di persone (art. 3, c. 1, lett. b) gli
     * importi aumentati.
     */
    public static function getUlterioreDeduzione(int $base, bool $persone): int
    {
        foreach ($persone ? self::DEDUZIONI_PERSONE : self::DEDUZIONI as $limite => $deduzione) {
            if ($base * 100 <= $limite) {
                return $deduzione;
            }
        }
        return 0;
    }

    /**
     * Acconti IRAP per l'anno successivo con il metodo storico: 100 per
     * cento dell'imposta (rigo IR21 al netto dei crediti), non dovuti fino a
     * 20,66 euro per le società di capitali e 51,65 per le società di
     * persone; prima rata del 40 per cento (50 per i soggetti ISA), non
     * dovuta se non supera 103 euro.
     *
     * @return array{0: float, 1: float}
     */
    public static function getAcconti(float $base, bool $persone, bool $isa): array
    {
        if ($base <= ($persone ? 51.65 : 20.66)) {
            return [0.0, 0.0];
        }
        $primo = round($base * ($isa ? 0.5 : 0.4));
        return $primo > 103 ? [$primo, $base - $primo] : [0.0, $base];
    }

    private function proponiIr(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, int $netto, bool $isa, ?string $categoria = null): void
    {
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $regione = VersamentiRedditi::getCodiceRegioneDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($anno . '-12-31'));
        if ($regione === null) {
            $this->avvisi[] = 'Quadro IR: domicilio fiscale (sede legale) non trovato nell\'Anagrafica, regione da indicare a mano.';
        }
        // valore della produzione da ripartire: il positivo e, separatamente, i valori negativi (IC64 - IC65 se negativo; colonne 3 negative dei righi da IP60 a IP65)
        $persone = $soggetto->tipoSoggetto === TipoSoggetto::societaPersone;
        $negativo = $persone
                ? array_sum(array_map(fn(string $codice) => min(0, $quadri->getImporto($codice)), ['IP060003', 'IP061003', 'IP062003', 'IP063003', 'IP064003', 'IP065003']))
                : min(0, $quadri->getImporto('IC064001') - $quadri->getImporto('IC065001'));
        $valori = array_values(array_filter([$netto, $negativo]));
        $tabella = new AliquoteIrap();
        $annoModello = $dichiarazione->getAnnoModello();
        $imposta = -$quadri->getImporto('IR020001');
        for ($rigo = 1; $rigo <= 8; $rigo++) {
            $campo = fn(int $colonna) => sprintf('IR%03d%03d', $rigo, $colonna);
            if ($rigo <= max(1, count($valori))) {
                $quadri->proponi($campo(1), $regione);
                $quadri->proponi($campo(2), $valori[$rigo - 1] ?? null);
                // banche, enti finanziari e assicurazioni: codice della categoria da scegliere a mano tra quelli della regione
                $quadri->proponi($campo(6), ($regione === null) || ($categoria !== null) ? null : AliquoteIrap::CODICE_ORDINARIA);
            }
            if ($quadri->get($campo(1)) === null) {
                continue;
            }
            $base = max(0, $quadri->getImporto($campo(2)) + $quadri->getImporto($campo(3)) - $quadri->getImporto($campo(4)));
            $quadri->proponi($campo(5), $base ?: null);
            $codice = $quadri->get($campo(6));
            $aliquota = $codice === null ? null : $tabella->getAliquota($annoModello, (string) $quadri->get($campo(1)), (string) $codice);
            if (($aliquota === null) && ($codice !== null) && ($base > 0)) {
                $this->avvisi[] = sprintf('Quadro IR, rigo IR%d: codice aliquota %s non presente nella tabella della regione %s: indicare a mano l\'aliquota.', $rigo, $codice, $quadri->get($campo(1)));
            }
            $quadri->proponi($campo(7), ($base > 0) && ($aliquota !== null) ? $aliquota : null);
            $lorda = (int) round($base * (float) $quadri->get($campo(7)) / 100);
            $quadri->proponi($campo(8), $lorda ?: null);
            $nettaRegione = $lorda - $quadri->getImporto($campo(9)) - $quadri->getImporto($campo(10));
            $quadri->proponi($campo(11), $nettaRegione ?: null);
            $imposta += $quadri->getImporto($campo(11));
        }
        $quadri->proponi('IR021001', $imposta ?: null);
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::irap, $anno - 1);
        if ($precedente !== null) {
            $quadri->proponi('IR023001', (new QuadriDichiarazione($precedente, $this->dataMapper))->getImporto('IR030001', 1) ?: null);
        }
        $acconti = (int) round(array_sum(VersamentiRedditi::getVersatiPerCodici($this->dataMapper, $soggetto, $anno, self::ACCONTI)));
        $quadri->proponi('IR025002', $acconti ?: null);
        $risultato = $imposta - $quadri->getImporto('IR022004') - $quadri->getImporto('IR023001') + $quadri->getImporto('IR024001') - $quadri->getImporto('IR025002');
        $quadri->proponi('IR026001', $risultato > 0 ? $risultato : null);
        $quadri->proponi('IR027001', $risultato < 0 ? -$risultato : null);
        $credito = $risultato < 0 ? -$risultato - $quadri->getImporto('IR029001') : 0;
        $quadri->proponi('IR030001', $credito > 0 ? $credito : null);
        [$primo, $secondo] = self::getAcconti((float) ($imposta - $quadri->getImporto('IR022004')), $persone, $isa);
        if ($primo + $secondo > 0) {
            $this->avvisi[] = sprintf('Acconti IRAP %d: %s euro (100%% dell\'imposta dell\'anno, metodo storico)%s.', $anno + 1, number_format($primo + $secondo, 0, ',', '.'),
                    $primo > 0 ? sprintf(', in due rate da %s e %s euro', number_format($primo, 0, ',', '.'), number_format($secondo, 0, ',', '.')) : ' in unica rata a novembre');
        }
        if (($regione !== null) && ($categoria !== null)) {
            $candidati = $tabella->getCandidati($annoModello, $regione, $categoria);
            $this->avvisi[] = sprintf('Quadro IR: %s (art. 16, comma 1-bis, D.Lgs. 446/1997, dal codice ATECO): il codice aliquota non è proposto, indicalo a mano nella colonna 6 del rigo IR1 scegliendo tra i codici della regione%s (elenco completo nella scheda della dichiarazione); l\'aliquota si ricalcola alla riproposta.',
                    $categoria === AliquoteIrap::ASSICURAZIONI ? 'impresa di assicurazione dell\'art. 7' : 'banca o altro ente finanziario dell\'art. 6',
                    count($candidati) === 0 ? '' : ', per la categoria: ' . implode('; ', array_map(fn(string $codice, array $voce) => sprintf('%s (%s%%, %s)', $codice, number_format($voce['aliquota'], 2, ',', ''), mb_substr($voce['descrizione'], 0, 80)), array_keys($candidati), $candidati)));
        } elseif ($regione !== null) {
            $this->avvisi[] = sprintf('Quadro IR: codice aliquota %s della regione (%s, %s%%) dalla tabella del Dipartimento delle Finanze; per le aliquote agevolate o maggiorate del settore o della categoria indicare a mano il codice nella colonna 6 del rigo IR1: l\'aliquota si ricalcola alla riproposta.',
                    AliquoteIrap::CODICE_ORDINARIA, $tabella->getDescrizione($annoModello, $regione, AliquoteIrap::CODICE_ORDINARIA) ?? 'aliquota ordinaria',
                    number_format($tabella->getAliquota($annoModello, $regione, AliquoteIrap::CODICE_ORDINARIA) ?? self::ALIQUOTA_ORDINARIA, 2, ',', ''));
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
            'Valore della produzione lorda (IC64)' => $quadri->getImporto('IC064001', 1),
            'Valore della produzione (IP66)' => $quadri->getImporto('IP066001', 1),
            'Valore della produzione netta (IC76, IP74, IE61)' => $quadri->getImporto('IC076001', 1) + $quadri->getImporto('IP074001', 1) + $quadri->getImporto('IE061001', 1),
            'Imposta (IR21)' => $quadri->getImporto('IR021001', 1),
            'IRAP a debito (IR26)' => $quadri->getImporto('IR026001', 1),
            'IRAP a credito (IR27)' => $quadri->getImporto('IR027001', 1),
        ]);
    }
}
