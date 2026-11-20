<?php

namespace Dichiarativi\Application\Services\Irap;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RegimeFiscale;
use Anagrafica\Application\Enumerations\TipoSoggetto;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Contabilita\Application\Services\ComponentiReddito;
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

    public const QUADRI = ['IC', 'IP', 'IR'];
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
        $netto = $persone ? $this->proponiIp($quadri, $voce) : $this->proponiIc($quadri, $voce);
        $this->proponiIr($quadri, $dichiarazione, $netto, $dati?->soggettoIsa ?? false);
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
    private function proponiIc(QuadriDichiarazione $quadri, \Closure $voce): int
    {
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
        $this->avvisi[] = 'Quadro IC: componenti dal conto economico per competenza (voci CEE); variazioni in aumento e in diminuzione (righi da IC43 a IC57) da indicare a mano, i totali si ricalcolano alla riproposta.';
        return $netto;
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

    private function proponiIr(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, int $netto, bool $isa): void
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
                $quadri->proponi($campo(6), $regione === null ? null : AliquoteIrap::CODICE_ORDINARIA);
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
        if ($regione !== null) {
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
            'Valore della produzione netta (IC76, IP74)' => $quadri->getImporto('IC076001', 1) + $quadri->getImporto('IP074001', 1),
            'Imposta (IR21)' => $quadri->getImporto('IR021001', 1),
            'IRAP a debito (IR26)' => $quadri->getImporto('IR026001', 1),
            'IRAP a credito (IR27)' => $quadri->getImporto('IR027001', 1),
        ]);
    }
}
