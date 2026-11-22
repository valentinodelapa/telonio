<?php

namespace Dichiarativi\Application\Services\Sostituti;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Services\RitenuteOperate;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\VersamentoModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta del modello 770 (dichiarazione dei sostituti d'imposta, art.
 * 4, c. 1, D.P.R. 322/1998; istruzioni e specifiche tecniche 770/2026)
 * per le ritenute sui compensi di lavoro autonomo e sulle provvigioni:
 * prospetto ST, sezione I, con un rigo per mese di pagamento dei compensi
 * (periodo di riferimento), le ritenute operate registrate in contabilità
 * (RitenuteOperate), l'importo versato con il codice tributo 1040 e la
 * data del versamento dalle deleghe F24 di Deleghe (versamenti dell'anno
 * con il mese nella rateazione 00MM, generati dalla Contabilità o inseriti
 * a mano; data della quietanza, in mancanza la scadenza della delega, con
 * un avviso).
 *
 * I righi sono 18 per modulo (ST2-ST19). Ravvedimento, interessi, note e
 * crediti utilizzati a scomputo si indicano a mano; i versamenti tardivi
 * sono segnalati. Il frontespizio (tipologia di invio 1 con la casella
 * "Autonomo") è composto dal file telematico.
 */
class Proposta770
{

    public const QUADRI = ['ST'];
    public const RIGHI_PER_MODULO = 18;

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
        $righi = $this->getRighi($dichiarazione->soggetto, $dichiarazione->anno);
        $blocchi = array_chunk($righi, self::RIGHI_PER_MODULO);
        // moduli in più: restano i soli valori inseriti a mano
        foreach (array_diff($quadri->getModuli(), range(1, max(1, count($blocchi)))) as $soppresso) {
            $quadri->usaModulo($soppresso)->avviaProposta(...self::QUADRI);
        }
        foreach ($blocchi === [] ? [[]] : $blocchi as $indice => $blocco) {
            $quadri->usaModulo($indice + 1)->avviaProposta(...self::QUADRI);
            self::proponiRighi($quadri, $blocco);
        }
        if ($righi === []) {
            $this->avvisi[] = 'Nessuna ritenuta d\'acconto registrata nell\'anno: il prospetto ST si compila a mano.';
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
     * Righi del prospetto ST: ritenute del mese, importo e data del
     * versamento.
     *
     * @return list<array{mese: int, anno: int, ritenute: float, versato: float, data: ?string}>
     */
    public function getRighi(Soggetto $soggetto, int $anno): array
    {
        $righi = [];
        // versamenti 1040 dell'anno in una delega (generati dalla Contabilità o inseriti a mano), per mese di riferimento (rateazione 00MM)
        $perMese = [];
        foreach ((new VersamentoModel($this->dataMapper))->getPerPeriodo($soggetto->studio, new SismaDate($anno . '-01-01'), new SismaDate(($anno + 1) . '-12-31'), $soggetto) as $versamento) {
            if (($versamento->delega !== null) && ($versamento->codiceTributo?->codice === RitenuteOperate::CODICE_TRIBUTO) && ($versamento->annoRiferimento === $anno)
                    && (preg_match('/^00(0[1-9]|1[0-2])$/', (string) $versamento->rateazione) === 1)) {
                $perMese[(int) substr($versamento->rateazione, 2)][] = $versamento;
            }
        }
        foreach ((new RitenuteOperate($this->dataMapper))->getPerMese($soggetto, $anno) as $mese => $ritenute) {
            if ($ritenute['importo'] <= 0) {
                continue;
            }
            $versato = 0.0;
            $data = null;
            foreach ($perMese[$mese] ?? [] as $versamento) {
                $delega = $versamento->delega;
                $versato = round($versato + $versamento->importoDebito, 2);
                if ($delega->stato !== StatoDelega::quietanzata) {
                    $this->avvisi[] = sprintf('Ritenute di %02d/%d: delega F24 non quietanzata, proposta la sua scadenza come data del versamento.', $mese, $anno);
                }
                $dataVersamento = ($delega->dataQuietanza ?? $delega->scadenza)->format('Y-m-d');
                $data = max($data ?? $dataVersamento, $dataVersamento);
            }
            if ($versato === 0.0) {
                $this->avvisi[] = sprintf('Ritenute di %02d/%d (€ %s): nessun versamento in una delega F24 (Contabilità, ritenute operate).', $mese, $anno, number_format($ritenute['importo'], 2, ',', '.'));
            } elseif (abs($versato - $ritenute['importo']) >= 0.01) {
                $this->avvisi[] = sprintf('Ritenute di %02d/%d: versati € %s su € %s operate.', $mese, $anno, number_format($versato, 2, ',', '.'), number_format($ritenute['importo'], 2, ',', '.'));
            }
            if (($data !== null) && ($data > RitenuteOperate::getScadenza($anno, $mese)->format('Y-m-d'))) {
                $this->avvisi[] = sprintf('Ritenute di %02d/%d versate il %s, dopo la scadenza: indicare a mano ravvedimento (ST col. 9) e interessi (col. 8).', $mese, $anno, date('d/m/Y', strtotime($data)));
            }
            $righi[] = ['mese' => $mese, 'anno' => $anno, 'ritenute' => $ritenute['importo'], 'versato' => $versato, 'data' => $data];
        }
        return $righi;
    }

    /**
     * Totali del prospetto ST per il riepilogo della dichiarazione.
     *
     * @return array<string, float>
     */
    public static function getRiepilogo(QuadriDichiarazione $quadri): array
    {
        $totali = ['Ritenute operate (ST col. 2)' => 0.0, 'Importi versati (ST col. 7)' => 0.0, 'Interessi (ST col. 8)' => 0.0];
        foreach ($quadri->getModuli() as $modulo) {
            for ($rigo = 2; $rigo <= 19; $rigo++) {
                $totali['Ritenute operate (ST col. 2)'] += $quadri->getDecimale(sprintf('ST%03d002', $rigo), $modulo);
                $totali['Importi versati (ST col. 7)'] += $quadri->getDecimale(sprintf('ST%03d007', $rigo), $modulo);
                $totali['Interessi (ST col. 8)'] += $quadri->getDecimale(sprintf('ST%03d008', $rigo), $modulo);
            }
        }
        return array_filter(array_map(fn(float $importo) => round($importo, 2), $totali));
    }

    /**
     * @param list<array{mese: int, anno: int, ritenute: float, versato: float, data: ?string}> $righi al più 18
     */
    public static function proponiRighi(QuadriDichiarazione $quadri, array $righi): void
    {
        foreach (array_values($righi) as $indice => $rigo) {
            $prefisso = sprintf('ST%03d', $indice + 2);
            $quadri->proponi($prefisso . '001', sprintf('%02d%04d', $rigo['mese'], $rigo['anno']));
            $quadri->proponi($prefisso . '002', $rigo['ritenute']);
            $quadri->proponi($prefisso . '007', $rigo['versato']);
            $quadri->proponi($prefisso . '011', RitenuteOperate::CODICE_TRIBUTO);
            $quadri->proponi($prefisso . '014', $rigo['data']);
        }
    }
}
