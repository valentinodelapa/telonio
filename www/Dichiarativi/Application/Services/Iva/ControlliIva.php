<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Models\DatiFiscaliModel;
use Contabilita\Application\Services\RegimeIva;
use Deleghe\Application\Services\DatiContribuente;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Controlli della dichiarazione IVA prima della trasmissione (specifiche
 * tecniche del modello IVA 2026):
 *
 * - errori, che impediscono il file: dati del frontespizio obbligatori
 *   (partita IVA, dati di nascita, natura giuridica, firmatario dei
 *   soggetti diversi dalle persone fisiche, impegno dell'intermediario,
 *   professionista del visto e componenti dell'organo di controllo),
 *   codice attività di ogni modulo, causale del rimborso, quadri senza
 *   dati;
 * - segnalazioni dei "controlli di rispondenza con i dati della
 *   dichiarazione" (totali e risultati dei quadri VE, VF, VJ, VL, VT, VC e
 *   VX non coerenti, di solito per correzioni a mano), per modulo: il file
 *   si genera solo con il flag di conferma nel frontespizio (campo 8 del
 *   record B).
 */
class ControlliIva
{

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    public function getErrori(Dichiarazione $dichiarazione, QuadriDichiarazione $quadri): array
    {
        $errori = [];
        $soggetto = $dichiarazione->soggetto;
        if ($this->getPartitaIva($dichiarazione) === null) {
            $errori[] = 'Contribuente: manca la partita IVA nei dati fiscali dell\'Anagrafica.';
        }
        foreach (DatiContribuente::getDatiMancanti($soggetto) as $mancante) {
            $errori[] = 'Contribuente: manca ' . $mancante . '.';
        }
        if ($soggetto->isPersonaFisica() === false) {
            if ($dichiarazione->naturaGiuridica === null) {
                $errori[] = 'Indicare la natura giuridica del contribuente nel frontespizio.';
            }
            if ($dichiarazione->dichiarante === null) {
                $errori[] = 'Indicare chi sottoscrive la dichiarazione (rappresentante legale) nel frontespizio.';
            }
        }
        if ($dichiarazione->dichiarante !== null) {
            if ($dichiarazione->codiceCarica === null) {
                $errori[] = 'Indicare il codice di carica del sottoscrittore.';
            }
            foreach (DatiContribuente::getDatiMancanti($dichiarazione->dichiarante) as $mancante) {
                $errori[] = 'Sottoscrittore: manca ' . $mancante . '.';
            }
        }
        if (($dichiarazione->intermediario !== null) && (($dichiarazione->dataImpegno === null) || ($dichiarazione->impegno === null))) {
            $errori[] = 'Indicare l\'impegno alla presentazione e la sua data per l\'intermediario.';
        }
        if (($dichiarazione->vistoProfessionista !== null) && ($dichiarazione->vistoProfessionista->isPersonaFisica() === false)) {
            $errori[] = 'Il visto di conformità si appone da un professionista persona fisica.';
        }
        if (($dichiarazione->vistoProfessionista !== null) && ($dichiarazione->vistoCaf !== null)) {
            $errori[] = 'Il visto di conformità è del professionista o del CAF, non di entrambi.';
        }
        if (($dichiarazione->vistoCaf !== null) && ((preg_match('/^\d{11}$/', $dichiarazione->vistoCaf->codiceFiscale) !== 1) || ($dichiarazione->vistoResponsabileCaf === null)
                || ($dichiarazione->vistoResponsabileCaf->isPersonaFisica() === false))) {
            $errori[] = 'Visto del CAF: indicare il CAF (codice fiscale numerico) e il responsabile dell\'assistenza fiscale (persona fisica).';
        }
        foreach ($quadri->getModuli() ?: [1] as $modulo) {
            if (($quadri->get('VA002001', $modulo) === null) && (count(array_intersect($quadri->getQuadriCompilati($modulo), ['VE', 'VF', 'VJ'])) > 0 || ($modulo === 1))) {
                $errori[] = ($modulo === 1 ? '' : 'Modulo ' . $modulo . ', ') . 'rigo VA2: manca il codice attività.';
            }
        }
        if (($quadri->getImporto('VX004001', 1) > 0) && ($quadri->get('VX004003', 1) === null)) {
            $errori[] = 'Rigo VX4: indicare la causale del rimborso (campo 3).';
        }
        // art. 30, c. 2: rimborso solo con un credito dell'anno oltre 2.582,28 euro
        if (in_array($quadri->get('VX004003', 1), ['2', '3', '4', '5', '6', '7'], true) && ($quadri->getImporto('VX002001', 1) <= RimborsoIva::SOGLIA)) {
            $errori[] = 'Rigo VX4: con la causale indicata il rimborso compete solo se il credito dell\'anno (VX2) supera 2.582,28 euro.';
        }
        if ($quadri->getImporto('VX004001', 1) > PropostaIva::getCreditoRipartibile($quadri, 1)) {
            $errori[] = 'Rigo VX4: il rimborso non può superare il credito (VX2 più VX3 o, con l\'ingresso in un Gruppo IVA, VX2 al netto del campo 2).';
        }
        if ($quadri->getImporto('VX004002', 1) > $quadri->getImporto('VX004001', 1)) {
            $errori[] = 'Rigo VX4: la quota della procedura semplificata (campo 2) non può superare il rimborso.';
        }
        if (($quadri->get('VX004003', 1) === '7') && ($quadri->getImporto('VX004001', 1) > array_sum(array_map(fn(int $modulo) => $quadri->getImporto('VF054002', $modulo), $quadri->getModuli() ?: [1])))) {
            $errori[] = 'Rigo VX4: con la causale 7 il rimborso compete per l\'IVA teorica delle cessioni agricole non imponibili (VF54).';
        }
        if (($quadri->getImporto('VX004002', 1) > 0) && ($quadri->get('VX004003', 1) === '1')) {
            $errori[] = 'Rigo VX4: con la cessazione dell\'attività il rimborso spetta agli uffici, il campo 2 non va compilato.';
        }
        if (($quadri->get('VX004005', 1) !== null) && ($quadri->get('VX004004', 1) !== '6')) {
            $errori[] = 'Rigo VX4: l\'imposta delle operazioni in scissione dei pagamenti (campo 5) va indicata solo con il codice 6 di erogazione prioritaria (campo 4).';
        }
        // prospetto IVA 26 PR: rimborso del gruppo
        if (($quadri->get('VY004001', 1) !== null) && ($quadri->getImporto('VY004001', 1) > $quadri->getImporto('VY002001', 1) + $quadri->getImporto('VY003001', 1))) {
            $errori[] = 'Rigo VY4: il rimborso del gruppo non può superare il credito (VY2 più VY3).';
        }
        $differenzaVy4 = $quadri->getImporto('VY004001', 1) - $quadri->getImporto('VS020001', 1);
        if (($quadri->get('VY004003', 1) === null) && (($differenzaVy4 < 0) || (($differenzaVy4 > 0) && ($quadri->get('VZ001001', 1) === null)))) {
            $errori[] = 'Rigo VY4: deve coincidere con il totale delle quote del quadro VS (VS20), salvo la minore eccedenza detraibile del triennio con il quadro VZ compilato.';
        }
        if ($quadri->getImporto('VK025001', 1) > max(0, $quadri->getImporto('VK023001', 1) - $quadri->getImporto('VK024001', 1))) {
            $errori[] = 'Rigo VK25: l\'eccedenza chiesta a rimborso dalla controllante non può superare l\'eccedenza non compensata (VK23 meno VK24).';
        }
        if (count(array_diff($quadri->getQuadriCompilati(1), ['VA'])) === 0) {
            $errori[] = 'La dichiarazione non ha dati nei quadri: proporla dalla contabilità o compilarla.';
        }
        return $errori;
    }

    /**
     * @return string[]
     */
    public function getSegnalazioni(QuadriDichiarazione $quadri): array
    {
        $segnalazioni = [];
        $moduli = $quadri->getModuli() ?: [1];
        $etichetta = '';
        $verifica = function (string $rigo, int $atteso, ?int $modulo = null) use ($quadri, &$segnalazioni, &$etichetta): void {
            $valore = $quadri->getImporto($rigo, $modulo);
            if (abs($valore - $atteso) > 1) {
                $segnalazioni[] = sprintf('%s%s: indicato %s, risulta %s.', $etichetta, $rigo, number_format($valore, 0, ',', '.'), number_format($atteso, 0, ',', '.'));
            }
        };
        foreach ($moduli as $modulo) {
            $quadri->usaModulo($modulo);
            $etichetta = $modulo === 1 ? '' : 'Modulo ' . $modulo . ', ';
            $this->verificaModulo($quadri, $verifica);
        }
        foreach (PropostaIva::getGruppi($quadri, $moduli) as $gruppo) {
            $quadri->usaModulo($gruppo[0]);
            $etichetta = $gruppo[0] === 1 ? '' : 'Modulo ' . $gruppo[0] . ', ';
            $debiti = $crediti = 0;
            foreach ($gruppo as $modulo) {
                $debiti += $quadri->getImporto('VL003001', $modulo);
                $crediti += $quadri->getImporto('VL004001', $modulo);
            }
            $debiti += $quadri->somma('VL020001', 'VL021001', 'VL022001', 'VL023001');
            $crediti += $quadri->somma('VL011001', 'VL012001', 'VL024001', 'VL025001', 'VL026001', 'VL027001', 'VL028001', 'VL029001', 'VL031001');
            $a = $debiti - $crediti - $quadri->getImporto('VL030001');
            $b = $debiti - $crediti - $quadri->somma('VL030003', 'VL030004', 'VL030005');
            $verifica('VL032001', max(0, $a));
            $verifica('VL033001', ($a < 0) && ($b < 0) ? -$b : 0);
            $verifica('VL038001', $quadri->getImporto('VL032001') - $quadri->somma('VL034001', 'VL035001') + $quadri->getImporto('VL036001'));
            $verifica('VL039001', $quadri->getImporto('VL033001') - $quadri->getImporto('VL037001'));
        }
        $quadri->usaModulo(1);
        $etichetta = '';
        $imponibileVe = $impostaVe = 0;
        foreach ($moduli as $modulo) {
            $imponibileVe += $quadri->getImporto('VE024001', $modulo);
            $impostaVe += $quadri->getImporto('VE026002', $modulo);
        }
        [$imposta, $impostaGruppo] = PropostaIva::calcolaImposteVx($quadri, $moduli);
        if (($imposta > 10) || ($imposta < 0) || ($quadri->getImporto('VX001001') !== 0)) {
            $verifica('VX001001', max(0, $imposta));
        }
        $verifica('VX002001', max(0, -$imposta));
        if (($quadri->get('VK001001') !== null) && ($quadri->getImporto('VK001002') >= 12)) {
            $verifica('VX007001', max(0, $impostaGruppo));
            $verifica('VX008001', max(0, -$impostaGruppo));
        }
        if ($quadri->get('VK001001') !== null) {
            $eccedenza = $quadri->getImporto('VK020001') - $quadri->getImporto('VK021001');
            $verifica('VK022001', max(0, -$eccedenza));
            $verifica('VK023001', max(0, $eccedenza));
            if ($quadri->getImporto('VK024001') > $quadri->getImporto('VK023001')) {
                $segnalazioni[] = 'VK024001: l\'eccedenza compensata non può superare l\'eccedenza di credito (VK23).';
            }
        }
        if ($quadri->get('VW001001') !== null) {
            $verifica('VW003001', max(0, $quadri->getImporto('VW001001') - $quadri->getImporto('VW002001')));
            $verifica('VW004001', max(0, $quadri->getImporto('VW002001') - $quadri->getImporto('VW001001')));
            $verifica('VW030001', max($quadri->getImporto('VW030002'), $quadri->somma('VW030003', 'VW030004', 'VW030005')));
            $debiti = $quadri->somma('VW003001', 'VW020001', 'VW022001', 'VW023001', 'VW024001');
            $crediti = $quadri->somma('VW004001', 'VW025001', 'VW026001', 'VW026002', 'VW027001', 'VW028001', 'VW029001', 'VW030001', 'VW031001');
            $verifica('VW032001', max(0, $debiti - $crediti));
            $dovuto = $quadri->somma('VW032001', 'VW036001') - $quadri->somma('VW033001', 'VW034001');
            $verifica('VW038001', max(0, $dovuto));
            $verifica('VW039001', max(0, -$dovuto));
        }
        if (($quadri->get('VG008001') !== null) && ($quadri->get('VG002002') !== null)) {
            $segnalazioni[] = 'Quadro VG: con la revoca (VG8) i righi da VG1 a VG7 non vanno compilati.';
        }
        $verifica('VX005001', PropostaIva::getCreditoRipartibile($quadri) - $quadri->getImporto('VX004001') - $quadri->getImporto('VX006002'));
        if ($quadri->get('VT001001') !== null) {
            $verifica('VT001001', $imponibileVe);
            $verifica('VT001002', $impostaVe);
            $verifica('VT001005', $quadri->getImporto('VT001001') - $quadri->getImporto('VT001003'));
            $verifica('VT001006', $quadri->getImporto('VT001002') - $quadri->getImporto('VT001004'));
            $regionali = $impostaRegionale = 0;
            foreach (array_keys(RegioniVt::RIGHI) as $rigo) {
                $regionali += $quadri->getImporto(sprintf('VT%03d001', $rigo));
                $impostaRegionale += $quadri->getImporto(sprintf('VT%03d002', $rigo));
            }
            $verifica('VT001003', $regionali);
            $verifica('VT001004', $impostaRegionale);
        } elseif ($imponibileVe > 0) {
            $segnalazioni[] = 'Quadro VT non compilato: la ripartizione tra consumatori finali e soggetti IVA è obbligatoria per tutti i contribuenti.';
        }
        if ($quadri->get('VC013001') !== null) {
            for ($colonna = 1; $colonna <= 6; $colonna++) {
                $totale = 0;
                for ($mese = 1; $mese <= 12; $mese++) {
                    $totale += $quadri->getImporto(sprintf('VC%03d%03d', $mese, $colonna));
                }
                $verifica(sprintf('VC013%03d', $colonna), $totale);
            }
        }
        return array_values(array_unique($segnalazioni));
    }

    /**
     * Controlli dei quadri VE, VF, VJ e della sezione 1 del VL del modulo
     * corrente.
     */
    private function verificaModulo(QuadriDichiarazione $quadri, callable $verifica): void
    {
        foreach (PropostaIva::RIGHI_VE as $aliquota => $rigo) {
            $verifica(sprintf('VE%03d002', $rigo), (int) round($quadri->getImporto(sprintf('VE%03d001', $rigo)) * (float) $aliquota / 100));
        }
        foreach (PropostaIva::RIGHI_VF as $aliquota => $rigo) {
            $verifica(sprintf('VF%03d002', $rigo), (int) round($quadri->getImporto(sprintf('VF%03d001', $rigo)) * (float) $aliquota / 100));
        }
        $righiVe = array_merge(range(1, 11), array_values(PropostaIva::RIGHI_VE));
        $verifica('VE024001', array_sum(array_map(fn(int $rigo) => $quadri->getImporto(sprintf('VE%03d001', $rigo)), $righiVe)));
        $verifica('VE024002', array_sum(array_map(fn(int $rigo) => $quadri->getImporto(sprintf('VE%03d002', $rigo)), $righiVe)));
        $verifica('VE026002', $quadri->getImporto('VE024002') + $quadri->getImporto('VE025002'));
        $verifica('VE050001', $quadri->getImporto('VE024001') + $quadri->somma('VE030001', 'VE031001', 'VE032001', 'VE033001', 'VE034001', 'VE035001', 'VE036001', 'VE037001', 'VE038001', 'VE038002')
                - $quadri->somma('VE039001', 'VE040001'));
        $verifica('VF025001', array_sum(array_map(fn(int $rigo) => $quadri->getImporto(sprintf('VF%03d001', $rigo)), range(1, 13)))
                + $quadri->somma('VF017001', 'VF018001', 'VF018002', 'VF019001', 'VF020001', 'VF021001', 'VF022001', 'VF023001') - $quadri->getImporto('VF024001'));
        $verifica('VF025002', array_sum(array_map(fn(int $rigo) => $quadri->getImporto(sprintf('VF%03d002', $rigo)), range(1, 13))));
        $verifica('VF027002', $quadri->getImporto('VF025002') + $quadri->getImporto('VF026002'));
        if ($quadri->somma('VF029001', 'VF029002', 'VF029003', 'VF029004') > 0) {
            $verifica('VF025001', $quadri->somma('VF029001', 'VF029002', 'VF029003', 'VF029004'));
        }
        $quotaForfetaria = PropostaIva::getQuotaForfetaria($quadri);
        if ($quadri->get('VF030008') !== null) {
            $verifica('VF055002', $quadri->somma('VF052002', 'VF053002', 'VF054002'));
            $verifica('VF071002', $quadri->getImporto('VF055002') + (int) round($quadri->getImporto('VF062002') * 0.5) + $quadri->getImporto('VF070002'));
        } elseif ($quotaForfetaria !== null) {
            $verifica('VF071002', (int) round($quadri->getImporto('VE026002') * $quotaForfetaria) + $quadri->getImporto('VF070002'));
        } elseif ($quadri->get('VF030003') !== null) {
            $percentuale = PropostaIva::calcolaPercentuale($quadri);
            $verifica('VF034009', $percentuale);
            $base = $quadri->getImporto('VF027002') + $quadri->getImporto('VF035001') - $quadri->getImporto('VF036001');
            $verifica('VF037001', (int) round($base * $quadri->getImporto('VF034009') / 100) - $quadri->getImporto('VF035001') + $quadri->getImporto('VF036001'));
            $verifica('VF071002', $quadri->getImporto('VF037001') + $quadri->getImporto('VF070002'));
        } elseif (($quadri->get('VF032001') !== null) || ($quadri->get('VF033001') !== null)) {
            $verifica('VF071002', $quadri->getImporto('VF070002'));
        } else {
            $verifica('VF071002', $quadri->getImporto('VF027002') + $quadri->getImporto('VF070002'));
        }
        $verifica('VJ019002', array_sum(array_map(fn(int $rigo) => $quadri->getImporto(sprintf('VJ%03d002', $rigo)), range(1, 18))));
        $verifica('VL001001', $quadri->getImporto('VE026002') + $quadri->getImporto('VJ019002'));
        $verifica('VL002001', $quadri->getImporto('VF071002'));
        $differenza = $quadri->getImporto('VL001001') - $quadri->getImporto('VL002001');
        $verifica('VL003001', max(0, $differenza));
        $verifica('VL004001', max(0, -$differenza));
        if ($quadri->get('VL008001') !== null) {
            $verifica('VL025001', $quadri->getImporto('VL008001') - $quadri->getImporto('VL009001') - $quadri->getImporto('VL010001'));
        }
        $verifica('VL030001', max($quadri->getImporto('VL030002'), $quadri->somma('VL030003', 'VL030004', 'VL030005')));
    }

    /**
     * Avvertenze che non riguardano il file.
     *
     * @return string[]
     */
    public function getAvvertenze(QuadriDichiarazione $quadri, ?Dichiarazione $dichiarazione = null): array
    {
        $avvertenze = [];
        $sottoscritta = ($dichiarazione !== null) && isset($dichiarazione->id) && (count((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($dichiarazione)) > 0);
        if (($quadri->getImporto('VX005001', 1) > VersamentiIva::LIMITE_CREDITO_LIBERO) && (($dichiarazione?->hasVisto() ?? false) === false) && ($sottoscritta === false) && (($dichiarazione?->esoneroVisto ?? false) === false)) {
            $avvertenze[] = 'Credito da compensare superiore a 5.000 euro: la compensazione in F24 oltre questo importo richiede il visto di conformità o la sottoscrizione dell\'organo di controllo (art. 10, c. 1, lett. a), D.L. 78/2009), da indicare nel frontespizio.';
        }
        if (($quadri->getImporto('VX004001', 1) > 30000) && (($dichiarazione?->hasVisto() ?? false) === false) && ($sottoscritta === false) && ($quadri->get('VX004007', 1) === null)) {
            $avvertenze[] = 'Rimborso oltre 30.000 euro senza garanzia: servono il visto di conformità (o la sottoscrizione dell\'organo di controllo) e la dichiarazione sostitutiva delle condizioni dell\'art. 38-bis, c. 3 (VX4, campo 10).';
        }
        if ($dichiarazione !== null) {
            $rimborso = new RimborsoIva($this->dataMapper);
            if (($quadri->getImporto('VX004001', 1) > RimborsoIva::LIMITE_GARANZIA) || ($quadri->getImporto('VY004001', 1) > RimborsoIva::LIMITE_GARANZIA)) {
                foreach ($rimborso->getRischi($dichiarazione) as $rischio) {
                    $avvertenze[] = 'Rimborso oltre 30.000 euro: garanzia dovuta (art. 38-bis, c. 4) per ' . $rischio . '.';
                }
                if ($rimborso->isVerificaDaFare($dichiarazione)) {
                    $avvertenze[] = RimborsoIva::AVVISO_VERIFICA;
                }
            }
            $semplificata = $quadri->getImporto('VX004002', 1) + $quadri->getImporto('VY004002', 1);
            $compensati = $semplificata > 0 ? (new CreditiTrimestrali($this->dataMapper))->getCompensatiNellAnno($dichiarazione->soggetto, $dichiarazione->anno + 1) : 0;
            if ($semplificata + $compensati > RimborsoIva::LIMITE_COMPENSAZIONI) {
                $avvertenze[] = sprintf('Procedura semplificata di € %s con compensazioni in F24 di € %s nel %d: oltre il limite annuale di 2.000.000 di euro (art. 34, c. 1, L. 388/2000).',
                        number_format($semplificata, 0, ',', '.'), number_format($compensati, 0, ',', '.'), $dichiarazione->anno + 1);
            }
        }
        return $avvertenze;
    }

    public function getPartitaIva(Dichiarazione $dichiarazione): ?string
    {
        $datiFiscali = (new DatiFiscaliModel($this->dataMapper))->getVigenteAl($dichiarazione->soggetto, new SismaDate($dichiarazione->anno . '-12-31'))
                ?? (new RegimeIva($this->dataMapper))->getDatiFiscali($dichiarazione->soggetto, $dichiarazione->anno);
        return $datiFiscali?->partitaIva ?? $dichiarazione->soggetto->partitaIva;
    }
}
