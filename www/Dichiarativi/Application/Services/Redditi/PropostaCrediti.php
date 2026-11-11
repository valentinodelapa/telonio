<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Models\CanoneNonPercepitoModel;
use Anagrafica\Application\Models\ContrattoLocazioneModel;
use Anagrafica\Application\Models\PossessoModel;
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
 *   la quota di canone (al netto della deduzione forfetaria del 5 per
 *   cento), entro l'imposta netta di quell'anno;
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
                    $credito += $this->riliquidazione($soggetto, $canone->anno, $canone->importo * 0.95 * $quota, $immobile->getIdentificativo());
                }
            }
        }
        if ($credito > 0) {
            $this->avvisi[] = 'Rigo CR8: credito per i canoni non percepiti calcolato sull\'IRPEF lorda delle dichiarazioni degli anni dei canoni (o sulla cedolare secca); verifica la rendita catastale (il reddito non scende sotto la rendita), le detrazioni e le addizionali.';
        }
        return (int) round($credito);
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
        ];
        $capienza = $q('RN005001') - $q('RN022001');
        $totale = $q('RN023001') + $q('RN024007');
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
}
