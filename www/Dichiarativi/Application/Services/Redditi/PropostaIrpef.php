<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Models\AddizionaleComunaleModel;
use Dichiarativi\Application\Models\AddizionaleRegionaleModel;
use Dichiarativi\Application\Models\CertificazioneUnicaModel;
use Dichiarativi\Application\Models\ValoreCertificazioneUnicaModel;
use Dichiarativi\Application\Services\Iva\RegioniVt;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;
use Dichiarativi\Application\Services\Redditi\PuntiCertificazioneUnica as Punti;

/**
 * Proposta dei quadri RC (dalle Certificazioni Uniche), RN (IRPEF), RV
 * (addizionali) e RX (risultato) del modello Redditi PF, sui redditi già
 * proposti (RA, RB, LC) e sui valori inseriti a mano negli altri quadri
 * (familiari a carico FA, oneri del quadro RP, crediti, eccedenze e
 * acconti), con le regole delle specifiche tecniche (CalcoloIrpef,
 * CalcoloAddizionali).
 */
class PropostaIrpef
{

    public const QUADRI = ['RC', 'RN', 'RV', 'RX'];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * I quadri sono già in proposta (PropostaRedditi::proponi()).
     *
     * @return string[]
     */
    public function proponi(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $this->avvisi = [];
        $quadri->usaModulo(1);
        $this->proponiLavoro($quadri, $dichiarazione->soggetto, $dichiarazione->anno);
        $quadri->usaModulo(1);
        $this->proponiIrpef($quadri, $dichiarazione->soggetto, $dichiarazione->anno);
        $this->proponiAddizionali($quadri, $dichiarazione->soggetto, $dichiarazione->anno);
        $this->proponiRisultato($quadri);
        return $this->avvisi;
    }

    /**
     * Quadro RC dalle Certificazioni Uniche da dichiarare (righi da RC1 a
     * RC3 per tempo indeterminato, determinato e pensione; RC7 e RC8 per
     * gli altri assimilati e gli assegni del coniuge; RC10 e RC14).
     */
    private function proponiLavoro(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $valoreModel = new ValoreCertificazioneUnicaModel($this->dataMapper);
        $righi = [];
        $assimilati = [];
        $somme = [];
        $codici = [Punti::CODICE_TRATTAMENTO_INTEGRATIVO => [], Punti::TIPOLOGIA_RIDUZIONE => [], Punti::CODICE_SOMMA_ESENTE => []];
        foreach ((new CertificazioneUnicaModel($this->dataMapper))->getDaDichiarare($soggetto, $anno) as $certificazione) {
            $valori = $valoreModel->getValori($certificazione);
            foreach ([Punti::INDETERMINATO => [2, 1], Punti::DETERMINATO => [2, 2], Punti::PENSIONE => [1, null]] as $punto => [$tipologia, $durata]) {
                if (($valori[$punto] ?? 0) > 0) {
                    $righi[] = [$tipologia, $durata, (int) round($valori[$punto])];
                }
            }
            foreach ([Punti::ALTRI_ASSIMILATI => false, Punti::ASSEGNI_CONIUGE => true] as $punto => $coniuge) {
                if (($valori[$punto] ?? 0) > 0) {
                    $assimilati[] = [$coniuge, (int) round($valori[$punto])];
                }
            }
            foreach ($valori as $punto => $valore) {
                if (isset($codici[$punto])) {
                    $codici[$punto][] = (int) $valore;
                } else {
                    $somme[$punto] = ($somme[$punto] ?? 0.0) + $valore;
                }
            }
        }
        if ((count($righi) === 0) && (count($assimilati) === 0)) {
            return;
        }
        foreach ($righi as $indice => [$tipologia, $durata, $importo]) {
            $quadri->usaModulo(intdiv($indice, 3) + 1);
            $codice = fn(int $colonna) => sprintf('RC%03d%03d', $indice % 3 + 1, $colonna);
            $quadri->proponi($codice(1), $tipologia);
            $quadri->proponi($codice(2), $durata);
            $quadri->proponi($codice(3), $importo);
        }
        foreach ($assimilati as $indice => [$coniuge, $importo]) {
            $quadri->usaModulo(intdiv($indice, 2) + 1);
            $codice = fn(int $colonna) => sprintf('RC%03d%03d', 7 + $indice % 2, $colonna);
            $quadri->proponi($codice(1), $coniuge ?: null);
            $quadri->proponi($codice(2), $importo);
        }
        $quadri->usaModulo(1);
        $intero = fn(int $punto) => (int) round($somme[$punto] ?? 0.0);
        $giorni = function (int $punto) use ($intero): ?int {
            $totale = $intero($punto);
            if ($totale > 365) {
                $this->avvisi[] = 'Quadro RC: i giorni delle Certificazioni Uniche (punto ' . $punto . ') superano 365, i periodi contemporanei si contano una volta: verifica il rigo RC6.';
            }
            return $totale > 0 ? min(365, $totale) : null;
        };
        $quadri->proponi('RC005006', array_sum(array_column($righi, 2)) ?: null);
        $quadri->proponi('RC006001', $giorni(Punti::GIORNI_LAVORO));
        $quadri->proponi('RC006002', $giorni(Punti::GIORNI_PENSIONE));
        $quadri->proponi('RC009002', array_sum(array_column($assimilati, 1)) ?: null);
        foreach ([1 => Punti::RITENUTE_IRPEF, 2 => Punti::ADDIZIONALE_REGIONALE, 3 => Punti::ACCONTO_COMUNALE, 4 => Punti::SALDO_COMUNALE, 5 => Punti::ACCONTO_COMUNALE_SUCCESSIVO] as $colonna => $punto) {
            $quadri->proponi(sprintf('RC010%03d', $colonna), $intero($punto) ?: null);
        }
        if (count(array_filter($righi, fn(array $rigo) => $rigo[0] === 2)) > 0) {
            $codice = fn(array $valori) => count($valori) === 0 ? null : (in_array(1, $valori, true) ? 1 : 2);
            $quadri->proponi('RC014001', $codice($codici[Punti::CODICE_TRATTAMENTO_INTEGRATIVO]));
            $quadri->proponi('RC014002', $intero(Punti::TRATTAMENTO_INTEGRATIVO) ?: null);
            $tipologia = $codice($codici[Punti::TIPOLOGIA_RIDUZIONE]);
            $quadri->proponi('RC014005', $tipologia);
            if ($tipologia === 1) {
                $quadri->proponi('RC014006', $intero(Punti::REDDITI_ART_49) ?: null);
                $quadri->proponi('RC014007', $intero(Punti::REDDITI_SPORTIVI) ?: null);
                $quadri->proponi('RC014008', $giorni(Punti::GIORNI_RIDUZIONE));
                $quadri->proponi('RC014009', $codice($codici[Punti::CODICE_SOMMA_ESENTE]) ?? 2);
                $quadri->proponi('RC014010', $intero(Punti::SOMMA_ESENTE) ?: null);
            }
        }
    }

    /**
     * Quadro RN: reddito complessivo, imposta lorda, detrazioni, ritenute,
     * trattamento integrativo e somma esente, saldo e acconti.
     */
    private function proponiIrpef(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $fondiari = $q('RA023011') + $q('RA023012') + $q('RB010013') + $q('RB010019');
        // redditi d'impresa e di lavoro autonomo (perdite di lavoro autonomo in diminuzione; quelle d'impresa a mano nei righi delle perdite)
        $impresa = $q('RE025001') + $q('RG036001') + $q('RF101001');
        $complessivo = $fondiari + $q('RC005006') + $q('RC009002') + $impresa;
        if (($complessivo === 0) && ($q('RB010014') + $q('RB010015') + $q('RB010016') + $q('LM038001') === 0)) {
            return;
        }
        $riferimento = $complessivo + $q('RB010014') + $q('RB010015') + $q('RB010016') + $q('LM038001');
        $quadri->proponi('RN001001', $riferimento);
        $quadri->proponi('RN001005', $complessivo);
        $quadri->proponi('RN002001', $q('RB010019') ?: null);
        // quadro RP: totali dei righi RP15 e RP39 e rigo RN23
        $oneri = new CalcoloOneri();
        $oneri->proponiTotali($quadri, (float) $riferimento, (float) ($q('RA023011') + $q('RA023012')));
        array_push($this->avvisi, ...$oneri->getAvvisi());
        $quadri->proponi('RN003001', $q('RP039001') ?: null);
        $imponibile = max(0, $q('RN001005') - $q('RN002001') - $q('RN003001'));
        $quadri->proponi('RN004001', $imponibile);
        $lorda = $this->isEsente($quadri, $fondiari) ? 0 : (int) round(CalcoloIrpef::impostaLorda($imponibile));
        $quadri->proponi('RN005001', $lorda ?: null);

        // detrazioni per familiari a carico dal prospetto FA (proposto dai legami familiari)
        $redditoDetrazioni = max(0.0, (float) $q('RN001001') - $q('RN002001'));
        $familiari = $this->leggiFamiliari($quadri);
        $quadri->proponi('RN006001', (int) round(CalcoloIrpef::detrazioneConiuge($redditoDetrazioni, $familiari['coniuge'])) ?: null);
        $numeroFigli = $familiari['numeroFigli'] + $q('FA007001');
        $detrazioneFigli = CalcoloIrpef::detrazioneFigli($redditoDetrazioni, $familiari['figli'], $numeroFigli);
        if ($familiari['primoFiglioC'] !== null) {
            // coniuge mancante (specifiche 20.2.4): la più favorevole tra la detrazione del primo figlio al 100% e quella del coniuge per i mesi della col. 10
            $detrazioneFigli += max(CalcoloIrpef::detrazioneFigli($redditoDetrazioni, [$familiari['primoFiglioC']], $numeroFigli),
                    CalcoloIrpef::detrazioneConiuge($redditoDetrazioni, $familiari['primoFiglioC']['mesi']));
        }
        $quadri->proponi('RN006002', (int) round($detrazioneFigli) ?: null);
        $quadri->proponi('RN006004', (int) round(CalcoloIrpef::detrazioneAltriFamiliari($redditoDetrazioni, $familiari['altri'])) ?: null);

        // detrazioni per lavoro dipendente, pensione e altri redditi
        $tipologie = [];
        $determinato = false;
        foreach ($quadri->getModuli() as $modulo) {
            for ($rigo = 1; $rigo <= 3; $rigo++) {
                if ($quadri->get(sprintf('RC%03d001', $rigo), $modulo) !== null) {
                    $tipologie[] = $quadri->getImporto(sprintf('RC%03d001', $rigo), $modulo);
                    $determinato = $determinato || ($quadri->getImporto(sprintf('RC%03d002', $rigo), $modulo) === 2);
                }
            }
        }
        // altri redditi con detrazione (specifiche 21.4): assimilati diversi dagli assegni del coniuge, lavoro autonomo (RE), impresa minore (RG)
        $altriRedditi = (($quadri->get('RC007002', 1) !== null) && ($quadri->get('RC007001', 1) === null)) || (($quadri->get('RC008002', 1) !== null) && ($quadri->get('RC008001', 1) === null))
                || ($quadri->get('RE006001', 1) !== null) || ($quadri->get('RE020001', 1) !== null) || ($quadri->get('RG012001', 1) !== null) || ($quadri->get('RG024001', 1) !== null);
        $lavoro = CalcoloIrpef::detrazioniLavoro($redditoDetrazioni, $q('RC006001'), $determinato, $q('RC006002'), $altriRedditi,
                count(array_intersect($tipologie, [2, 3, 4, 8, 9])) > 0, count(array_intersect($tipologie, [1, 7])) > 0);
        $quadri->proponi('RN007001', (int) round($lavoro[1]) ?: null);
        $quadri->proponi('RN007002', (int) round($lavoro[2]) ?: null);
        $quadri->proponi('RN007004', (int) round($lavoro[4]) ?: null);
        $riferimentoCuneo = $redditoDetrazioni + $q('RC014003') + $q('RC014004');
        $cuneo = $q('RC014005') === 1;
        $quadri->proponi('RN007005', $cuneo ? ((int) round(CalcoloIrpef::ulterioreDetrazione($riferimentoCuneo, $q('RC014008'))) ?: null) : null);
        // oneri detraibili della sezione I del quadro RP (specifiche 23.1), con il riordino oltre 75.000 euro (art. 16-ter Tuir)
        $riordino = max(0, $q('RN001001') - $q('RN002001'));
        $altreSezioni = count(array_filter(array_merge(...array_map('array_keys', $quadri->getValori() ?: [[]])), fn(string $codice) => preg_match('/^RP0(4[1-9]|[5-8][0-9])/', $codice) === 1)) > 0;
        if (($riordino > 75000) && $altreSezioni) {
            $this->avvisi[] = 'Rigo RN13: reddito oltre 75.000 euro con le sezioni da III a VI del quadro RP (rate dei bonus edilizi, arredi, altre detrazioni): il limite del riordino (art. 16-ter Tuir) va ripartito anche sui righi RN12 e da RN14 a RN17, controlla gli importi spettanti.';
        }
        $quadri->proponi('RN013001', $oneri->rn13($quadri, (float) $riordino, $familiari['figliRiordino'], $familiari['disabile']) ?: null);
        array_push($this->avvisi, ...array_diff($oneri->getAvvisi(), $this->avvisi));
        $familiariELavoro = $q('RN006001') + $q('RN006002') + $q('RN006004') + $q('RN007001') + $q('RN007002') + $q('RN007004') + $q('RN007005');
        $quadri->proponi('RN008001', $familiariELavoro ?: null);
        $totaleDetrazioni = $familiariELavoro + $q('RN012003') + $q('RN013001') + $q('RN014001') + $q('RN015001') + $q('RN016001') + $q('RN017001') + $q('RN017002')
                + $q('RN018002') + $q('RN019002') + $q('RN020002') + $q('RN021002') + $q('RN021003');
        $quadri->proponi('RN022001', $totaleDetrazioni ?: null);
        $netta = max(0, $q('RN005001') - $q('RN022001') - $q('RN023001') - $q('RN025001'));
        $quadri->proponi('RN026001', $netta ?: null);

        // ritenute: lavoro dipendente (RC10, RC11) e cedolare a credito per le ritenute sulle locazioni brevi (LC1, col. 5)
        $cedolareNegativa = max(0, $q('LC001004') - $q('LC001003'));
        $ritenute = $q('RC010001') + $q('RC010006') + $q('RC011001') + $q('RE026001') + $q('RG037006') + $q('RF102006') + $cedolareNegativa;
        $quadri->proponi('RN033004', $ritenute ?: null);
        $differenza = $q('RN026001') - $q('RN027001') - $q('RN028001') - $q('RN029002') - $q('RN031002') - $q('RN033004');
        $quadri->proponi('RN034001', $differenza ?: null);

        // trattamento integrativo (RN43) e somma che non concorre al reddito (RN44)
        $redditiLavoro = 0;
        foreach ($quadri->getModuli() as $modulo) {
            for ($rigo = 1; $rigo <= 3; $rigo++) {
                if ($quadri->getImporto(sprintf('RC%03d001', $rigo), $modulo) === 2) {
                    $redditiLavoro += $quadri->getImporto(sprintf('RC%03d003', $rigo), $modulo);
                }
            }
        }
        $capienza = $q('RN006001') + $q('RN006002') + $q('RN006004') + $q('RN007001');
        $spettante = (int) round(CalcoloIrpef::trattamentoIntegrativo($riferimentoCuneo, $redditiLavoro, $q('RC006001'), $determinato, $capienza, $q('RN005001')));
        $erogato = $q('RC014002');
        $quadri->proponi('RN043001', $spettante ?: null);
        $quadri->proponi('RN043002', $spettante > $erogato ? $spettante - $erogato : null);
        $quadri->proponi('RN043003', $erogato > $spettante ? $erogato - $spettante : null);
        $somma = $cuneo ? (int) round(CalcoloIrpef::sommaEsente($riferimentoCuneo, $q('RC014006') + $q('RC014003') + $q('RC014004'), $q('RC014008'))) : 0;
        $erogata = $q('RC014010');
        $quadri->proponi('RN044001', $somma ?: null);
        $quadri->proponi('RN044002', $somma > $erogata ? $somma - $erogata : null);
        $quadri->proponi('RN044003', $erogata > $somma ? $erogata - $somma : null);

        // acconti versati per l'anno in Deleghe (deleghe non in bozza)
        $acconti = VersamentiRedditi::getAccontiVersati($this->dataMapper, $soggetto, $anno);
        $quadri->proponi('RN038006', (int) round($acconti['irpef']) ?: null);
        // saldo (specifiche, par. 30)
        $saldo = $q('RN034001') - $q('RN035001') - $q('RN036002') + $q('RN037001') + $q('RN038003') - $q('RN038006') + $q('RN041001') + $q('RN041002')
                - $q('RN042001') + $q('RN042002') - $q('RN043002') + $q('RN043003') + $q('RL034002') - $q('RN044002') + $q('RN044003');
        $quadri->proponi('RN045002', $saldo > 0 ? $saldo : null);
        $quadri->proponi('RN046001', $saldo < 0 ? -$saldo : null);

        // acconto 2026 (RN62): dovuto da 52 euro; 40% (50% per i soggetti ISA) alla prima rata se supera 103 euro
        $base = $quadri->get('RN061001', 1) !== null ? (int) $quadri->getImporto('RN061004', 1) : $q('RN034001');
        if ($base >= 52) {
            $isa = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'))?->soggettoIsa ?? false;
            $primo = (int) round($base * ($isa ? 0.5 : 0.4));
            $quadri->proponi('RN062001', $primo > 103 ? $primo : null);
            $quadri->proponi('RN062002', $primo > 103 ? $base - $primo : $base);
        }
        $quadri->proponi('RN050001', $q('RB010017') ?: null);
        $quadri->proponi('RN050002', ($q('RB010018') + $q('RA023013')) ?: null);
    }

    /**
     * Imposta non dovuta (specifiche 19.1 e 19.2): solo redditi fondiari
     * fino a 500 euro, o pensioni fino a 7.500 euro annui con abitazione
     * principale e terreni fino a 186 euro.
     */
    private function isEsente(QuadriDichiarazione $quadri, int $fondiari): bool
    {
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $totaleFondiari = $q('RA023011') + $q('RA023012') + $q('RB010013') + $q('RB010014') + $q('RB010015') + $q('RB010016') + $q('RB010019');
        if (($totaleFondiari <= 500) && ($q('RN001005') === $fondiari) && ($q('RN001002') === 0)) {
            return true;
        }
        $pensioni = 0;
        $altri = 0;
        for ($rigo = 1; $rigo <= 3; $rigo++) {
            $importo = $q(sprintf('RC%03d003', $rigo));
            in_array($q(sprintf('RC%03d001', $rigo)), [1, 7], true) ? $pensioni += $importo : $altri += $importo;
        }
        $giorni = $q('RC006002');
        return ($pensioni > 0) && ($giorni > 0) && ($altri === 0) && ($q('RC009002') === 0) && ($q('RB010013') === 0)
                && (($pensioni - $q('RC005003')) * 365 / $giorni <= 7500) && ($q('RA023011') + $q('RA023012') <= 186);
    }

    /**
     * Familiari dal prospetto FA: coniuge (rigo 1), figli (F1, F, D) con i
     * mesi da 21 anni (col. 10) e la percentuale (il primo figlio con la
     * percentuale C a parte, per il confronto con la detrazione del
     * coniuge), ascendenti conviventi (G) con i mesi a carico; gli altri
     * familiari (P) non danno detrazioni dal 2025.
     *
     * @return array{coniuge: int, figli: list<array{mesi: int, percentuale: float}>, primoFiglioC: ?array{mesi: int, percentuale: float}, numeroFigli: int, altri: list<array{mesi: int, percentuale: float}>}
     */
    private function leggiFamiliari(QuadriDichiarazione $quadri): array
    {
        $familiari = ['coniuge' => $quadri->get('FA001001', 1) !== null ? $quadri->getImporto('FA001006', 1) : 0, 'figli' => [], 'primoFiglioC' => null, 'numeroFigli' => 0, 'altri' => [], 'figliRiordino' => 0, 'disabile' => false];
        $tuttiFigli = [];
        $codiciFigli = [];
        foreach ($quadri->getModuli() as $modulo) {
            for ($rigo = 2; $rigo <= 6; $rigo++) {
                $relazione = strtoupper((string) $quadri->get(sprintf('FA%03d001', $rigo), $modulo));
                if ($relazione === '') {
                    continue;
                }
                $percentuale = $quadri->get(sprintf('FA%03dA07', $rigo), $modulo) !== null ? $quadri->getDecimale(sprintf('FA%03dA07', $rigo), $modulo) : 100.0;
                if (in_array($relazione, ['F1', 'F', 'D'], true)) {
                    // figli per il riordino delle detrazioni (par. 10.3): tutti i codici fiscali dei righi F, F1 e D
                    $tuttiFigli[$quadri->get(sprintf('FA%03d005', $rigo), $modulo) ?? 'rigo' . $modulo . '-' . $rigo] = true;
                    $familiari['disabile'] = $familiari['disabile'] || ($relazione === 'D');
                    // numero dei figli per l'incremento: codici fiscali dei righi con la col. 10 (specifiche 20.2.1, punto 2)
                    if ($quadri->get(sprintf('FA%03d010', $rigo), $modulo) === null) {
                        continue;
                    }
                    $codiciFigli[$quadri->get(sprintf('FA%03d005', $rigo), $modulo) ?? 'rigo' . $modulo . '-' . $rigo] = true;
                    $figlio = ['mesi' => $quadri->getImporto(sprintf('FA%03d010', $rigo), $modulo), 'percentuale' => $percentuale];
                    if (strtoupper((string) $quadri->get(sprintf('FA%03dB07', $rigo), $modulo)) === 'C') {
                        $familiari['primoFiglioC'] = $figlio;
                    } else {
                        $familiari['figli'][] = $figlio;
                    }
                } elseif ($relazione === 'G') {
                    $familiari['altri'][] = ['mesi' => $quadri->getImporto(sprintf('FA%03d006', $rigo), $modulo), 'percentuale' => $percentuale];
                }
            }
        }
        $familiari['numeroFigli'] = count(array_filter(array_keys($codiciFigli), fn($codice) => !str_starts_with((string) $codice, 'rigo')));
        $familiari['figliRiordino'] = count(array_filter(array_keys($tuttiFigli), fn($codice) => !str_starts_with((string) $codice, 'rigo'))) + $quadri->getImporto('FA007001', 1);
        return $familiari;
    }

    /**
     * Quadro RV: addizionale regionale (domicilio fiscale al 1° gennaio
     * dell'anno), comunale (stesso domicilio) e acconto comunale (domicilio
     * al 1° gennaio dell'anno dopo, aliquote dell'anno d'imposta).
     */
    private function proponiAddizionali(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        if (($q('RN005001') - $q('RN022001') <= 10.33) && ($q('RC010002') + $q('RC010003') + $q('RC010004') + $q('RC010005') === 0)) {
            return;
        }
        $imponibile = max(0, $q('RN001005') - $q('RN002001') - $q('RN003001'));
        $dovute = $q('RN005001') - $q('RN022001') > 10.33;
        $calcolo = new CalcoloAddizionali();
        $quadri->proponi('RV001001', $imponibile ?: null);
        $comune = VersamentiRedditi::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable($anno . '-01-01'));
        // regionale
        $rigoVt = RegioniVt::getRigo($comune?->siglaProvincia);
        $regionale = $rigoVt === null ? null : (new AddizionaleRegionaleModel($this->dataMapper))->getPerRegione(sprintf('%02d', $rigoVt - 1), $anno);
        if ($dovute && ($regionale === null)) {
            $this->avvisi[] = 'Quadro RV: domicilio fiscale al 1° gennaio ' . $anno . ' non trovato nell\'Anagrafica o tabella regionale ' . $anno . ' assente: addizionale regionale da calcolare a mano.';
        }
        $esitoRegionale = ($dovute && ($regionale !== null)) ? $calcolo->regionale($regionale, $imponibile, $q('RN001005')) : ['importo' => 0.0, 'avvisi' => []];
        array_push($this->avvisi, ...array_map(fn(string $avviso) => 'Addizionale regionale, casi particolari da verificare. ' . $avviso, $esitoRegionale['avvisi']));
        $quadri->proponi('RV002002', (int) round($esitoRegionale['importo']) ?: null);
        $quadri->proponi('RV003003', ($q('RC010002') + $q('RC012001')) ?: null);
        $saldoRegionale = $q('RV002002') - $q('RV003003') - $q('RV004003') + $q('RV005001') - $q('RV006001') + $q('RV006002');
        $quadri->proponi('RV007001', $saldoRegionale > 0 ? $saldoRegionale : null);
        $quadri->proponi('RV008001', $saldoRegionale < 0 ? -$saldoRegionale : null);
        // comunale
        $comunaleModel = new AddizionaleComunaleModel($this->dataMapper);
        $tabella = ($dovute && ($comune !== null)) ? $comunaleModel->getPerComune($comune->codiceCatastale, $anno) : null;
        if ($tabella !== null) {
            $quadri->proponi('RV009001', $tabella->isPerScaglioni() ?: null);
            $quadri->proponi('RV009002', $tabella->isPerScaglioni() ? null : $tabella->aliquotaUnica);
            $esito = $calcolo->comunale($tabella, $imponibile);
            array_push($this->avvisi, ...$esito['avvisi']);
            $quadri->proponi('RV010002', (int) round($esito['importo']) ?: null);
        }
        $quadri->proponi('RV011001', ($q('RC010003') + $q('RC010004')) ?: null);
        $quadri->proponi('RV011002', (int) round(VersamentiRedditi::getAccontiVersati($this->dataMapper, $soggetto, $anno)['comunale']) ?: null);
        $quadri->proponi('RV011006', ($q('RV011001') + $q('RV011002') + $q('RV011004')) ?: null);
        $saldoComunale = $q('RV010002') - $q('RV011006') - $q('RV012003') + $q('RV013001') - $q('RV014001') + $q('RV014002');
        $quadri->proponi('RV015001', $saldoComunale > 0 ? $saldoComunale : null);
        $quadri->proponi('RV016001', $saldoComunale < 0 ? -$saldoComunale : null);
        // acconto comunale dell'anno successivo
        $comuneSuccessivo = VersamentiRedditi::getDomicilio($this->dataMapper, $soggetto, new \DateTimeImmutable(($anno + 1) . '-01-01'));
        $tabellaAcconto = ($dovute && ($comuneSuccessivo !== null)) ? $comunaleModel->getPerComune($comuneSuccessivo->codiceCatastale, $anno) : null;
        if ($tabellaAcconto !== null) {
            $quadri->proponi('RV017002', $imponibile ?: null);
            $quadri->proponi('RV017003', $tabellaAcconto->isPerScaglioni() ?: null);
            $quadri->proponi('RV017004', $tabellaAcconto->isPerScaglioni() ? null : $tabellaAcconto->aliquotaUnica);
            $quadri->proponi('RV017005', (int) round($calcolo->accontoComunale($tabellaAcconto, $imponibile)['importo']) ?: null);
        }
        $quadri->proponi('RV017006', $q('RC010005') ?: null);
        $acconto = $q('RV017005') - $q('RV017006') - $q('RV017007');
        $quadri->proponi('RV017008', $acconto > 0 ? $acconto : null);
    }

    /**
     * Quadro RX: debiti e crediti di IRPEF, addizionali e cedolare secca;
     * i crediti sono proposti in compensazione (col. 5).
     */
    private function proponiRisultato(QuadriDichiarazione $quadri): void
    {
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        foreach ([1 => ['RN045002', 'RN046001'], 2 => ['RV007001', 'RV008001'], 3 => ['RV015001', 'RV016001'], 4 => ['LC001012', 'LC001013']] as $rigo => [$debito, $credito]) {
            $quadri->proponi(sprintf('RX%03d001', $rigo), $q($debito) ?: null);
            $quadri->proponi(sprintf('RX%03d002', $rigo), $q($credito) ?: null);
            $rimborso = $q(sprintf('RX%03d004', $rigo));
            $quadri->proponi(sprintf('RX%03d005', $rigo), max(0, $q($credito) + $q(sprintf('RX%03d003', $rigo)) - $rimborso) ?: null);
        }
    }
}
