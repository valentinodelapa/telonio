<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\TipoOpzioneTassazione;
use Anagrafica\Application\Models\DatiFiscaliModel;
use Anagrafica\Application\Models\OpzioneTassazioneModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta del modello Consolidato nazionale e mondiale (CNM 2026) della
 * consolidante, dalle dichiarazioni Redditi SC dell'anno delle società del
 * consolidato nazionale (opzioni dell'Anagrafica) presenti nella suite
 * (istruzioni del CNM, quadri NF e CN).
 *
 * Quadro NF, sezione I: reddito (GN6, col. 6) o perdita (GN5, col. 3) della
 * consolidante nel rigo NF1 e delle consolidate, con il codice fiscale, nei
 * righi NF2-NF7 (più moduli se servono); totale NF8 e reddito complessivo
 * globale NF12, col. 2, con le rettifiche di consolidamento (NF9 e NF10)
 * inserite a mano.
 *
 * Quadro CN (determinazione dell'IRES del gruppo): reddito (CN1) o perdita
 * (CN2), perdite di periodi precedenti del consolidato inserite a mano
 * (CN3, col. 1 limitata all'80 per cento, col. 2 piena), reddito
 * imponibile CN4, IRES al 24 per cento (CN5), imposta netta CN7, ritenute
 * delle società del gruppo (GN19) in CN11, IRES dovuta CN13, eccedenza
 * del CNM dell'anno prima (CN15), acconti versati dalla consolidante
 * (codici 2001 e 2002, CN17) e IRES a debito (CN18 col. 3, CN20 col. 2) o a
 * credito (CN19, CN21) da utilizzare in compensazione (CN24, col. 3).
 */
class PropostaConsolidato
{

    public const QUADRI = ['NF', 'CN'];
    private const RIGHI_NF = 6;

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Dichiarazioni Redditi SC dell'anno delle società del consolidato della
     * consolidante (la consolidante per prima), per codice fiscale.
     *
     * @return array<string, array{societa: Soggetto, dichiarazione: ?Dichiarazione}>
     */
    public function getSocieta(Soggetto $consolidante, int $anno): array
    {
        $societa = [];
        $opzioni = (new OpzioneTassazioneModel($this->dataMapper))->getConsolidate($consolidante, $anno);
        usort($opzioni, fn($a, $b) => [(int) ($a->soggetto->id !== $consolidante->id), $a->soggetto->codiceFiscale] <=> [(int) ($b->soggetto->id !== $consolidante->id), $b->soggetto->codiceFiscale]);
        foreach ($opzioni as $opzione) {
            if ($opzione->tipoOpzione !== TipoOpzioneTassazione::consolidatoNazionale) {
                continue;
            }
            $societa[$opzione->soggetto->codiceFiscale] = ['societa' => $opzione->soggetto,
                'dichiarazione' => (new DichiarazioneModel($this->dataMapper))->getPerModello($opzione->soggetto, ModelloDichiarativo::redditiSc, $anno)];
        }
        return $societa;
    }

    /**
     * @return string[] avvisi
     */
    public function proponi(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, bool $salva = true): array
    {
        $this->avvisi = [];
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $consolidante = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $societa = $this->getSocieta($consolidante, $anno);
        $consolidate = array_values(array_filter($societa, fn(array $voce) => $voce['societa']->id !== $consolidante->id));
        $moduli = max(1, (int) ceil(count($consolidate) / self::RIGHI_NF), max($quadri->getModuli() ?: [1]));
        for ($modulo = 1; $modulo <= $moduli; $modulo++) {
            $quadri->usaModulo($modulo)->avviaProposta(...self::QUADRI);
        }
        $ritenute = 0;
        $totale = 0;
        $quadri->usaModulo(1);
        $propria = $societa[$consolidante->codiceFiscale] ?? null;
        if ($propria === null) {
            $this->avvisi[] = 'Nessuna opzione per il consolidato nazionale con la società come consolidante nell\'anno: indicare l\'opzione in Anagrafica (dati fiscali).';
        } else {
            [$reddito, $ritenuteSocieta] = $this->getTrasferito($propria);
            $quadri->proponi('NF001002', $reddito ?: null);
            $totale += $reddito;
            $ritenute += $ritenuteSocieta;
        }
        foreach ($consolidate as $indice => $voce) {
            $quadri->usaModulo(intdiv($indice, self::RIGHI_NF) + 1);
            $rigo = sprintf('NF%03d', $indice % self::RIGHI_NF + 2);
            [$reddito, $ritenuteSocieta] = $this->getTrasferito($voce);
            $quadri->proponi($rigo . '001', $voce['societa']->codiceFiscale);
            $quadri->proponi($rigo . '002', $reddito ?: null);
            $totale += $reddito;
            $ritenute += $ritenuteSocieta;
        }
        $quadri->usaModulo(1);
        // totale dei righi NF1-NF7 di tutti i moduli, anche di quelli inseriti a mano
        $somma = 0;
        $minimi = 0;
        foreach ($quadri->getModuli() as $modulo) {
            for ($numero = 1; $numero <= 7; $numero++) {
                $somma += $quadri->getImporto(sprintf('NF%03d002', $numero), $modulo);
                $minimi += $quadri->getImporto(sprintf('NF%03d003', $numero), $modulo);
            }
        }
        $quadri->proponi('NF008002', $somma ?: null);
        $quadri->proponi('NF008003', $minimi ?: null);
        $quadri->proponi('NF008004', $somma ?: null);
        $variazioni = $quadri->getImporto('NF010001') - $quadri->getImporto('NF009001');
        $quadri->proponi('NF011001', $variazioni ?: null);
        $globale = $somma + $variazioni;
        $quadri->proponi('NF012002', $globale ?: null);
        $this->proponiImposta($quadri, $dichiarazione, $globale, $ritenute);
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
     * Reddito (GN6, col. 6) o perdita (GN5, col. 3, con il segno meno) e
     * ritenute (GN19) trasferiti dalla società al consolidato.
     *
     * @param array{societa: Soggetto, dichiarazione: ?Dichiarazione} $voce
     * @return array{0: int, 1: int}
     */
    private function getTrasferito(array $voce): array
    {
        if ($voce['dichiarazione'] === null) {
            $this->avvisi[] = 'Quadro NF: ' . $voce['societa']->nominativo . ' non ha la dichiarazione Redditi SC dell\'anno nella suite, reddito trasferito da indicare a mano.';
            return [0, 0];
        }
        $quadri = new QuadriDichiarazione($voce['dichiarazione'], $this->dataMapper);
        if ($quadri->get('GN000002', 1) === null) {
            $this->avvisi[] = 'Quadro NF: la dichiarazione Redditi SC di ' . $voce['societa']->nominativo . ' non ha il quadro GN; riproporla dopo aver indicato l\'opzione per il consolidato.';
        }
        return [$quadri->getImporto('GN006006', 1) - $quadri->getImporto('GN005003', 1), $quadri->getImporto('GN019001', 1)];
    }

    private function proponiImposta(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, int $globale, int $ritenute): void
    {
        $quadri->proponi('CN001002', $globale > 0 ? $globale : null);
        $quadri->proponi('CN002001', $globale < 0 ? -$globale : null);
        $positivo = max(0, $globale);
        $perdite = $positivo > 0 ? min($positivo, $quadri->getImporto('CN003002') + min($quadri->getImporto('CN003001'), (int) floor($positivo * 0.8))) : 0;
        $quadri->proponi('CN003003', $perdite ?: null);
        $reddito = $positivo - $perdite;
        $quadri->proponi('CN004001', $reddito ?: null);
        $imponibile = max(0, $reddito - $quadri->getImporto('CN004002') - $quadri->getImporto('CN004003'));
        $quadri->proponi('CN004004', $imponibile ?: null);
        $quadri->proponi('CN005001', $imponibile ?: null);
        $quadri->proponi('CN005002', (int) round($imponibile * PropostaRedditiSocieta::ALIQUOTA_IRES) ?: null);
        $totale = $quadri->getImporto('CN005002') + $quadri->getImporto('CN005005');
        $quadri->proponi('CN005006', $totale ?: null);
        $netta = max(0, $totale - $quadri->getImporto('CN006001') - $quadri->getImporto('CN006002'));
        $quadri->proponi('CN007001', $netta ?: null);
        $quadri->proponi('CN011001', $ritenute ?: null);
        $crediti = $quadri->getImporto('CN008001') + $quadri->getImporto('CN009001') + $quadri->getImporto('CN010003') + $ritenute;
        $quadri->proponi('CN012001', $crediti ?: null);
        $dovuta = $netta - $crediti;
        $quadri->proponi('CN013001', $dovuta ?: null);
        $soggetto = $dichiarazione->soggetto;
        $anno = $dichiarazione->anno;
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::consolidato, $anno - 1);
        if ($precedente !== null) {
            $quadri->proponi('CN015001', (new QuadriDichiarazione($precedente, $this->dataMapper))->getImporto('CN024003', 1) ?: null);
        }
        $acconti = (int) round(array_sum(VersamentiRedditi::getVersatiPerCodici($this->dataMapper, $soggetto, $anno, PropostaRedditiSocieta::ACCONTI_IRES)));
        $quadri->proponi('CN017002', $acconti ?: null);
        $totaleAcconti = $quadri->getImporto('CN017001') + $acconti + $quadri->getImporto('CN017003') + $quadri->getImporto('CN017004');
        $quadri->proponi('CN017005', $totaleAcconti ?: null);
        $risultato = $dovuta - $quadri->getImporto('CN014001') - $quadri->getImporto('CN015001') + $quadri->getImporto('CN016001') - $totaleAcconti;
        $quadri->proponi('CN018003', $risultato > 0 ? $risultato : null);
        $quadri->proponi('CN019001', $risultato < 0 ? -$risultato : null);
        $quadri->proponi('CN020002', $risultato > 0 ? $risultato : null);
        $quadri->proponi('CN021001', $risultato < 0 ? -$risultato : null);
        $credito = $risultato < 0 ? -$risultato - $quadri->getImporto('CN022001') : 0;
        $quadri->proponi('CN024001', $credito > 0 ? $credito : null);
        $quadri->proponi('CN024003', $credito > 0 ? $credito - $quadri->getImporto('CN024002') : null);
        $isa = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($soggetto, new SismaDate($anno . '-12-31'))?->soggettoIsa ?? false;
        [$primo, $secondo] = PropostaRedditiSocieta::getAccontiIres((float) $dovuta, $isa);
        if ($primo + $secondo > 0) {
            $this->avvisi[] = sprintf('Acconti IRES del consolidato %d: %s euro (100%% dell\'IRES dovuta, rigo CN13), versati dalla consolidante.', $anno + 1, number_format($primo + $secondo, 0, ',', '.'));
        }
        $this->avvisi[] = 'Quadri NF e CN: rettifiche di consolidamento (NF9, NF10), perdite del consolidato (CN3), crediti (CN8-CN10, CN14) e detrazioni (CN6) da indicare a mano; l\'IRES si ricalcola alla riproposta.';
    }

    /**
     * Riepilogo per la scheda della dichiarazione (importi non nulli).
     *
     * @return array<string, int>
     */
    public static function getRiepilogo(QuadriDichiarazione $quadri): array
    {
        return array_filter([
            'Somma dei redditi e delle perdite del gruppo (NF8)' => $quadri->getImporto('NF008002', 1),
            'Reddito complessivo globale (NF12)' => $quadri->getImporto('NF012002', 1),
            'Reddito imponibile (CN4)' => $quadri->getImporto('CN004004', 1),
            'IRES del gruppo (CN5)' => $quadri->getImporto('CN005006', 1),
            'IRES dovuta (CN13)' => $quadri->getImporto('CN013001', 1),
            'IRES a debito (CN20)' => $quadri->getImporto('CN020002', 1),
            'IRES a credito (CN21)' => $quadri->getImporto('CN021001', 1),
        ]);
    }
}
