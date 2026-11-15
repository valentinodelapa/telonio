<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\ContrattoLocazione;
use Anagrafica\Application\Entities\Immobile;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\CanoneNonPercepitoModel;
use Anagrafica\Application\Models\ContrattoLocazioneModel;
use Anagrafica\Application\Models\DatiCatastaliModel;
use Anagrafica\Application\Models\PossessoModel;
use Anagrafica\Application\Services\CanoniLocazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Crediti d'imposta del quadro CR e loro utilizzo nel quadro RN, secondo
 * le istruzioni del quadro CR (fascicolo 1) e le specifiche tecniche
 * (righi RN24 e RN25, paragrafo 31 per i residui del rigo RN47):
 *
 * - residui della dichiarazione dell'anno prima nella suite (colonne del
 *   rigo RN47) nella colonna "residuo" dei righi CR7, CR9, CR10, CR12,
 *   CR13, CR14, CR15, CR17 e CR31 (un modulo per codice);
 * - credito per i canoni non percepiti (rigo CR8, art. 26 Tuir) dai
 *   canoni dei contratti dell'Anagrafica con la convalida di sfratto
 *   nell'anno: per gli anni con la cedolare secca l'aliquota della
 *   cedolare sulla quota di canone, per gli altri la riliquidazione
 *   dell'IRPEF lorda della dichiarazione di quell'anno nella suite senza
 *   la quota di canone non percepita (al netto della deduzione forfetaria
 *   del 5 per cento, con il reddito non inferiore alla rendita rivalutata),
 *   entro l'imposta netta di quell'anno;
 * - righi RN24 (credito residuo di ciascun credito), RN25 e residui del
 *   rigo RN47, colonne 11, 44, 12, 13, 14 e 15, usando i crediti
 *   nell'ordine delle specifiche fino all'imposta lorda meno le
 *   detrazioni (RN5 - RN22).
 */
class PropostaCrediti
{

    /** Colonna del rigo RN47 dell'anno prima => campo del residuo nel quadro CR */
    public const RESIDUI = ['RN047011' => 'CR007001', 'RN047012' => 'CR009001', 'RN047021' => 'CR010005', 'RN047013' => 'CR012004',
        'RN047044' => 'CR013001', 'RN047026' => 'CR014002', 'RN047027' => 'CR015001', 'RN047028' => 'CR017001'];

    /** Codice del rigo CR31 => colonna del rigo RN47 dell'anno prima con il residuo */
    public const RESIDUI_CR31 = [3 => 39, 4 => 40, 9 => 45, 10 => 46, 11 => 48, 12 => 51, 13 => 52, 14 => 53, 15 => 54, 16 => 14, 17 => 15, 18 => 56, 21 => 42];

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
     * Residui dell'anno prima e credito per i canoni non percepiti.
     */
    public function proponiCrediti(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        $this->avvisi = [];
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::redditiPf, $anno - 1);
        if ($precedente !== null) {
            self::proponiResidui($quadri, new QuadriDichiarazione($precedente, $this->dataMapper));
        }
        $credito = $this->getCreditoCanoni($soggetto, $anno);
        $quadri->usaModulo(1);
        $quadri->proponi('CR008001', $credito > 0 ? $credito : null);
    }

    public static function proponiResidui(QuadriDichiarazione $quadri, QuadriDichiarazione $precedenti): void
    {
        $quadri->usaModulo(1);
        foreach (self::RESIDUI as $colonnaRn47 => $campo) {
            $quadri->proponi($campo, $precedenti->getImporto($colonnaRn47, 1) ?: null);
        }
        $modulo = 0;
        foreach (self::RESIDUI_CR31 as $codice => $colonna) {
            $residuo = $precedenti->getImporto(sprintf('RN047%03d', $colonna), 1);
            if ($residuo <= 0) {
                continue;
            }
            $modulo++;
            $quadri->usaModulo($modulo);
            $quadri->proponi('CR031001', $codice);
            $quadri->proponi('CR031003', $residuo);
        }
        $quadri->usaModulo(1);
    }

    /**
     * Credito d'imposta del rigo CR8 per i canoni con la convalida di
     * sfratto nell'anno.
     */
    private function getCreditoCanoni(Soggetto $soggetto, int $anno): int
    {
        $possessoModel = new PossessoModel($this->dataMapper);
        $immobili = [];
        foreach ($possessoModel->getStoricoSoggetto($soggetto) as $possesso) {
            $immobili[$possesso->immobile->id] = $possesso->immobile;
        }
        $credito = 0.0;
        $canoneModel = new CanoneNonPercepitoModel($this->dataMapper);
        foreach ($immobili as $immobile) {
            foreach ((new ContrattoLocazioneModel($this->dataMapper))->getElenco($immobile) as $contratto) {
                foreach ($canoneModel->getElenco($contratto) as $canone) {
                    if ((int) $canone->dataConvalida->format('Y') !== $anno) {
                        continue;
                    }
                    $fine = new \DateTimeImmutable($canone->anno . '-12-31');
                    $quota = 0.0;
                    foreach ($possessoModel->getVigentiAl($immobile, $fine) as $possesso) {
                        if ($possesso->soggetto->id === $soggetto->id) {
                            $quota += $possesso->quota / 100;
                        }
                    }
                    if ($quota <= 0) {
                        continue;
                    }
                    if ($contratto->isCedolareAl($fine)) {
                        $credito += $canone->importo * $quota * $contratto->getAliquotaCedolare() / 100;
                        continue;
                    }
                    $credito += $this->riliquidazione($soggetto, $canone->anno, $this->redditoEscluso($contratto, $canone->anno, $canone->importo, $this->getRendita($immobile, $fine)) * $quota, $immobile->getIdentificativo());
                }
            }
        }
        if ($credito > 0) {
            $this->avvisi[] = 'Rigo CR8: credito per i canoni non percepiti calcolato sull\'IRPEF lorda delle dichiarazioni degli anni dei canoni (o sulla cedolare secca), con il reddito non inferiore alla rendita catastale rivalutata; verifica le detrazioni e le addizionali.';
        }
        return (int) round($credito);
    }

    /**
     * Reddito del fabbricato da togliere nella riliquidazione: differenza
     * tra il reddito dichiarato (il maggiore tra la rendita rivalutata del 5
     * per cento e il canone dell'anno meno la deduzione forfetaria del 5 per
     * cento) e quello senza i canoni non percepiti; la rendita resta il
     * minimo (istruzioni, appendice, "Crediti di imposta per canoni di
     * locazione non percepiti").
     */
    private function redditoEscluso(ContrattoLocazione $contratto, int $anno, float $nonPercepiti, float $rendita): float
    {
        $canone = (new CanoniLocazione($this->dataMapper))->getCanonePeriodo($contratto, new \DateTimeImmutable($anno . '-01-01'), new \DateTimeImmutable($anno . '-12-31'));
        return self::differenzaReddito($canone, $nonPercepiti, $rendita);
    }

    public static function differenzaReddito(float $canone, float $nonPercepiti, float $rendita): float
    {
        $rivalutata = $rendita * 1.05;
        return max($rivalutata, $canone * 0.95) - max($rivalutata, max(0.0, $canone - $nonPercepiti) * 0.95);
    }

    private function getRendita(Immobile $immobile, \DateTimeInterface $data): float
    {
        return (float) ((new DatiCatastaliModel($this->dataMapper))->getVigenteAl($immobile, $data)?->rendita ?? 0.0);
    }

    /**
     * IRPEF lorda in più pagata nell'anno sulla quota di canone non
     * percepita, entro l'imposta netta di quell'anno.
     */
    private function riliquidazione(Soggetto $soggetto, int $anno, float $reddito, string $immobile): float
    {
        $dichiarazione = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::redditiPf, $anno);
        if ($dichiarazione === null) {
            $this->avvisi[] = 'Rigo CR8: canoni non percepiti del ' . $anno . ' (' . $immobile . ') senza la dichiarazione di quell\'anno nella suite: credito da calcolare a mano riliquidando la dichiarazione.';
            return 0.0;
        }
        $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $imponibile = (float) $quadri->getImporto('RN004001', 1);
        $differenza = CalcoloIrpef::impostaLorda($imponibile, $anno) - CalcoloIrpef::impostaLorda(max(0.0, $imponibile - $reddito), $anno);
        return min($differenza, (float) $quadri->getImporto('RN026001', 1));
    }

    /**
     * Righi RN24 e RN25 e residui del rigo RN47 (specifiche, righi RN24 e
     * paragrafo 31): i crediti si usano nell'ordine prima casa, prima casa
     * under 36, occupazione, fondi pensione, mediazioni, negoziazione e
     * arbitrato fino all'imposta lorda meno le detrazioni.
     */
    public static function proponiUtilizzo(QuadriDichiarazione $quadri): void
    {
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $somma = function (string $codice) use ($quadri): int {
            $totale = 0;
            foreach ($quadri->getModuli() as $modulo) {
                $totale += $quadri->getImporto($codice, $modulo);
            }
            return $totale;
        };
        $cr31 = function (int $codiceCredito) use ($quadri): int {
            $totale = 0;
            foreach ($quadri->getModuli() as $modulo) {
                if ($quadri->getImporto('CR031001', $modulo) === $codiceCredito) {
                    $totale += $quadri->getImporto('CR031002', $modulo) + $quadri->getImporto('CR031003', $modulo) - $quadri->getImporto('CR031006', $modulo);
                }
            }
            return $totale;
        };
        // colonna del rigo RN24 => [credito residuo, colonna del rigo RN47], nell'ordine di utilizzo
        $crediti = [
            1 => [$q('CR007001') + $q('CR007002') - $q('CR007003') - $q('LM040001'), 11],
            6 => [$q('CR013001') + $q('CR013002') - $q('CR013003') - $q('CR013004') - $q('LM040015'), 44],
            2 => [$q('CR009001') - $q('CR009002'), 12],
            3 => [$somma('CR012005') + $q('CR012004') - $q('CR012006') - $q('LM040006'), 13],
            4 => [$cr31(16) - $q('LM040007'), 14],
            5 => [$cr31(17) - $q('LM040008'), 15],
            // contributo unificato (paragrafo 31.7): col. 2 di tutti i moduli, col. 3 e 6 del primo
            7 => [self::cr31($quadri, 18, 2) + self::cr31Primo($quadri, 18, 3) - self::cr31Primo($quadri, 18, 6) - $q('LM040014'), 56],
        ];
        $capienza = $q('RN005001') - $q('RN022001');
        $totale = $q('RN023001');
        foreach ($crediti as $colonna => [$credito, $colonnaRn47]) {
            $credito = max(0, $credito);
            $quadri->proponi(sprintf('RN024%03d', $colonna), $credito ?: null);
            $totale += $credito;
            $usato = max(0, min($credito, $capienza));
            $capienza -= $usato;
            $quadri->proponi(sprintf('RN047%03d', $colonnaRn47), ($credito - $usato) ?: null);
        }
        $quadri->proponi('RN025001', $totale ?: null);
    }

    /**
     * Colonne calcolate dei righi CR10 (rata annuale: un ventesimo del
     * credito) e CR11 (rata: credito diviso il numero delle rate) di tutti
     * i moduli.
     */
    public static function proponiRate(QuadriDichiarazione $quadri): void
    {
        foreach ($quadri->getModuli() as $modulo) {
            $quadri->usaModulo($modulo);
            if ($quadri->getImporto('CR010003', $modulo) > 0) {
                $quadri->proponi('CR010004', (int) round($quadri->getImporto('CR010003', $modulo) / 20) ?: null);
            }
            $rate = $quadri->getImporto('CR011004', $modulo);
            if (($rate > 0) && ($quadri->getImporto('CR011005', $modulo) > 0)) {
                $quadri->proponi('CR011006', (int) round($quadri->getImporto('CR011005', $modulo) / $rate) ?: null);
            }
        }
        $quadri->usaModulo(1);
    }

    /**
     * Crediti d'imposta dell'anno dopo l'imposta netta (righi RN27, RN28,
     * RN30 e RN32) e loro residui nel rigo RN47, secondo i paragrafi 25 e 27
     * delle specifiche e le colonne dei righi RN27 e RN28: i crediti si usano
     * uno dopo l'altro, nell'ordine delle specifiche, fino all'imposta netta
     * meno i crediti già usati (sisma Abruzzo, imposte estere del rigo RN29
     * e zone montane del rigo RN32, col. 21, a mano).
     *
     * @return string[] avvisi
     */
    public static function proponiCreditiAnno(QuadriDichiarazione $quadri): array
    {
        $avvisi = [];
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        $somma = function (string $codice) use ($quadri): int {
            $totale = 0;
            foreach ($quadri->getModuli() as $modulo) {
                $totale += $quadri->getImporto($codice, $modulo);
            }
            return $totale;
        };
        foreach ($quadri->getModuli() as $modulo) {
            if ($quadri->get('CR016001', $modulo) !== null) {
                $avvisi['CR16'] = 'Rigo CR16 (zone montane): in attesa dei decreti attuativi della L. 131/2025 le specifiche non ne consentono la compilazione; il rigo RN32, col. 21, resta a mano.';
            }
        }
        $imponibile = (float) $q('RN004001');
        $netta = $q('RN026001');
        // sisma Abruzzo: altri immobili (RN27) e abitazione principale (RN28)
        $altriImmobili = max(0, $somma('CR011006') - $q('LM040004'));
        $quadri->proponi('RN027001', min($netta, $altriImmobili, max(0, 16000 - $q('LM040004'))) ?: null);
        $creditoSisma = max(0, $somma('CR010004') + $q('CR010005') - $q('LM040005'));
        $capienza = max(0, $netta - $q('RN027001') - $q('RN032021'));
        $usato = min($creditoSisma, $capienza);
        $quadri->proponi('RN028001', $usato ?: null);
        $quadri->proponi('RN047021', ($creditoSisma - $usato) ?: null);
        $capienza = max(0, $netta - $q('RN027001') - $q('RN028001') - $q('RN029002') - $q('RN032021'));
        // credito => [totale, campo del credito usato, colonna del rigo RN47 o null]
        $usa = function (int $credito, string $usatoCodice, ?string $residuoCodice) use ($quadri, &$capienza): void {
            $credito = max(0, $credito);
            $usato = min($credito, $capienza);
            $capienza -= $usato;
            $quadri->proponi($usatoCodice, $usato ?: null);
            if ($residuoCodice !== null) {
                $quadri->proponi($residuoCodice, ($credito - $usato) ?: null);
            }
        };
        // erogazioni per la cultura (paragrafo 25.2): rata del 65 per cento della spesa, entro il 15 per cento dell'imponibile, in tre anni
        $rataCultura = (int) round(min($q('CR014001') * 0.65, $imponibile * 0.15) / 3);
        $quadri->proponi('RN030001', $rataCultura ?: null);
        $cultura = $rataCultura + $q('CR014002') + $q('CR014003') + $q('CR014004') + (int) round($q('CR014005') / 3) - $q('LM040009');
        $quadri->proponi('RN030002', max(0, $cultura) ?: null);
        $usa($cultura, 'RN030003', 'RN047026');
        // erogazioni per la scuola (25.3) e videosorveglianza (25.4)
        $scuola = $q('CR015001') - $q('LM040010');
        $quadri->proponi('RN030005', max(0, $scuola) ?: null);
        $usa($scuola, 'RN030006', 'RN047027');
        $videosorveglianza = $q('CR017001') - $q('CR017002') - $q('LM040011');
        $quadri->proponi('RN030007', max(0, $videosorveglianza) ?: null);
        $usa($videosorveglianza, 'RN030008', 'RN047028');
        $c = fn(int $codice, int $colonna) => self::cr31($quadri, $codice, $colonna);
        // sport bonus (27.2)
        $sport = $c(3, 3) + (int) round($c(3, 7) / 3) - $q('LM040012');
        $quadri->proponi('RN032004', max(0, $sport) ?: null);
        $usa($sport, 'RN032005', 'RN047039');
        // bonifica ambientale (27.3): 65 per cento delle erogazioni entro il 20 per cento dell'imponibile, in tre anni
        $rataBonifica = (int) round(min($c(4, 2) * 0.65, $imponibile * 0.20) / 3);
        $quadri->proponi('RN032006', $rataBonifica ?: null);
        $bonifica = $rataBonifica + $c(4, 3) + $c(4, 4) + $c(4, 5) + (int) round($c(4, 7) / 3) - $q('LM040013');
        $quadri->proponi('RN032007', max(0, $bonifica) ?: null);
        $usa($bonifica, 'RN032008', 'RN047040');
        // riscatto degli alloggi sociali (27.4)
        $usa($c(21, 3) + $c(21, 7), 'RN032010', 'RN047042');
        // sanificazione (27.5) e depuratori (27.6)
        $usa(max(0, $c(9, 3) + $c(9, 7) - $c(9, 6)) - $q('LM040016'), 'RN032011', 'RN047045');
        $usa(max(0, $c(10, 3) + $c(10, 7) - $c(10, 6)) - $q('LM040017'), 'RN032012', 'RN047046');
        // social bonus (27.7): 65 per cento entro il 15 per cento dell'imponibile, in tre anni
        $rataSocial = (int) round(min($c(11, 2) * 0.65, $imponibile * 0.15) / 3);
        $quadri->proponi('RN032013', $rataSocial ?: null);
        $social = $rataSocial + (int) round($c(11, 7) / 3) + $c(11, 3) + $c(11, 5) - $q('LM040020');
        $quadri->proponi('RN032014', max(0, $social) ?: null);
        $usa($social, 'RN032015', 'RN047048');
        // attività fisica adattata (27.8) e sistemi di accumulo (27.9)
        $usa($c(12, 3) - $q('LM040024'), 'RN032016', 'RN047051');
        $usa($c(13, 3) - $q('LM040021'), 'RN032017', 'RN047052');
        // ITS Academy al 30 e al 60 per cento (27.10 e 27.11), in tre anni
        foreach ([14 => [0.30, 'LM040022', 'RN032018', 'RN047053'], 15 => [0.60, 'LM040023', 'RN032019', 'RN047054']] as $codice => [$percentuale, $lm, $campo, $residuo]) {
            $its = (int) round($c($codice, 2) * $percentuale / 3) + (int) round($c($codice, 4) * $percentuale / 3) + (int) round($c($codice, 5) * $percentuale / 3)
                    + $c($codice, 3) + (int) round($c($codice, 7) / 3) - $c($codice, 6);
            $usa(max(0, $its) - $q($lm), $campo, $residuo);
        }
        return array_values($avvisi);
    }

    /**
     * Somma di una colonna dei righi CR31 con il codice, in tutti i moduli.
     */
    private static function cr31(QuadriDichiarazione $quadri, int $codice, int $colonna): int
    {
        $totale = 0;
        foreach ($quadri->getModuli() as $modulo) {
            if ($quadri->getImporto('CR031001', $modulo) === $codice) {
                $totale += $quadri->getImporto(sprintf('CR031%03d', $colonna), $modulo);
            }
        }
        return $totale;
    }

    /**
     * Colonna del primo rigo CR31 con il codice.
     */
    private static function cr31Primo(QuadriDichiarazione $quadri, int $codice, int $colonna): int
    {
        foreach ($quadri->getModuli() as $modulo) {
            if ($quadri->getImporto('CR031001', $modulo) === $codice) {
                return $quadri->getImporto(sprintf('CR031%03d', $colonna), $modulo);
            }
        }
        return 0;
    }
}
