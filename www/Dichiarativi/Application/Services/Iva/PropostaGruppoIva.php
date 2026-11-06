<?php

namespace Dichiarativi\Application\Services\Iva;

use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\PeriodicitaIva;
use Contabilita\Application\Entities\GruppoIva;
use Contabilita\Application\Entities\LiquidazioneGruppoIva;
use Contabilita\Application\Entities\LiquidazioneIva;
use Contabilita\Application\Entities\PartecipanteGruppoIva;
use Contabilita\Application\Enumerations\StatoComunicazioneLipe;
use Contabilita\Application\Models\AccontoIvaModel;
use Contabilita\Application\Models\ComunicazioneLipeModel;
use Contabilita\Application\Models\GruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneGruppoIvaModel;
use Contabilita\Application\Models\LiquidazioneIvaModel;
use Contabilita\Application\Models\PartecipanteGruppoIvaModel;
use Contabilita\Application\Services\LiquidazioneGruppo;
use Contabilita\Application\Services\RegimeIva;
use Deleghe\Application\Enumerations\StatoDelega;
use Deleghe\Application\Models\CreditoModel;
use Deleghe\Application\Models\VersamentoModel;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\SottoscrizioneControlloModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * IVA di gruppo nella dichiarazione IVA (art. 73, c. 3; paragrafi 3.4,
 * 4.2.9, 4.2.14, 4.2.16 e 4.3 delle istruzioni IVA 2026):
 *
 * - società partecipanti (compresa la controllante): quadro VK con i
 *   crediti e i debiti trasferiti nel periodo di controllo (più il
 *   conguaglio annuale dei righi VX7 e VX8 se il controllo è durato tutto
 *   l'anno), l'eccedenza compensata con i debiti delle altre società
 *   (ripartita in proporzione tra le società a credito, da confrontare
 *   con l'attestazione della controllante), gli interessi trasferiti e,
 *   con la cessazione del controllo, la sezione 3 del periodo di
 *   controllo; righi VL30 (senza l'IVA periodica trasferita) e VL31;
 *   righi VX7 e VX8 al posto di VX1 e VX2 (paragrafo 2.9.1 delle
 *   specifiche);
 * - controllante: quadro VG nella dichiarazione presentata nel primo anno
 *   dell'opzione, dell'ingresso o dell'uscita di controllate o della
 *   revoca; prospetto IVA 26 PR (VS, VV, VW, VY) con le dichiarazioni
 *   delle società partecipanti presenti nella suite (da proporre prima
 *   di quella della controllante); il quadro VZ si compila a mano.
 */
class PropostaGruppoIva
{

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
     * Partecipazione del soggetto alla liquidazione di gruppo nell'anno.
     *
     * @return array{gruppo: GruppoIva, partecipante: ?PartecipanteGruppoIva, ultimoMese: int}|null
     */
    public function getPartecipazione(Soggetto $soggetto, int $anno): ?array
    {
        $gruppo = (new GruppoIvaModel($this->dataMapper))->getPerControllante($soggetto, $anno);
        if ($gruppo !== null) {
            return ['gruppo' => $gruppo, 'partecipante' => null, 'ultimoMese' => 12];
        }
        $partecipante = (new PartecipanteGruppoIvaModel($this->dataMapper))->getPerControllata($soggetto, $anno);
        if ($partecipante === null) {
            return null;
        }
        $periodicita = (new RegimeIva($this->dataMapper))->getPeriodicita($soggetto, $anno);
        return ['gruppo' => $partecipante->gruppoIva, 'partecipante' => $partecipante, 'ultimoMese' => $partecipante->getUltimoMese($anno, $periodicita)];
    }

    /**
     * Debiti, crediti e interessi trasferiti dal soggetto nell'anno.
     *
     * @param LiquidazioneIva[] $liquidazioni
     * @return array{debiti: float, crediti: float, interessi: float}
     */
    public static function getTrasferimenti(array $liquidazioni): array
    {
        $totali = ['debiti' => 0.0, 'crediti' => 0.0, 'interessi' => 0.0];
        foreach ($liquidazioni as $liquidazione) {
            // il quarto trimestre dei trimestrali passa con il conguaglio annuale (VX7, VX8)
            if (($liquidazione->isTrasferita() === false) || (($liquidazione->periodicita === PeriodicitaIva::trimestrale) && ($liquidazione->periodo === 4))) {
                continue;
            }
            $saldo = $liquidazione->getSaldoTrasferito();
            if ($saldo >= 0) {
                $totali['debiti'] += $saldo;
                $totali['interessi'] += $liquidazione->interessi;
            } else {
                $totali['crediti'] -= $saldo;
            }
        }
        return $totali;
    }

    /**
     * Quadro VK del modulo 1 (dopo i righi VX7 e VX8).
     *
     * @param array{gruppo: GruppoIva, partecipante: ?PartecipanteGruppoIva, ultimoMese: int} $partecipazione
     * @param LiquidazioneIva[] $liquidazioni
     */
    public function proponiVk(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, array $partecipazione, array $liquidazioni): void
    {
        $gruppo = $partecipazione['gruppo'];
        $controllante = $gruppo->controllante;
        $quadri->proponi('VK001001', $controllante->partitaIva);
        $quadri->proponi('VK001002', $partecipazione['ultimoMese']);
        $quadri->proponi('VK001003', mb_substr($controllante->nominativo, 0, 60));
        $interoAnno = $partecipazione['ultimoMese'] >= 12;
        $trasferimenti = self::getTrasferimenti($liquidazioni);
        $quadri->proponi('VK020001', (int) round($trasferimenti['crediti']) + ($interoAnno ? $quadri->getImporto('VX008001') : 0));
        $quadri->proponi('VK021001', (int) round($trasferimenti['debiti']) + ($interoAnno ? $quadri->getImporto('VX007001') : 0));
        $eccedenza = $quadri->getImporto('VK020001') - $quadri->getImporto('VK021001');
        $quadri->proponi('VK022001', max(0, -$eccedenza));
        $quadri->proponi('VK023001', max(0, $eccedenza));
        if ($eccedenza > 0) {
            $compensazioni = $this->getCompensazioni($gruppo, $dichiarazione->anno, [$dichiarazione->soggetto->id => $eccedenza]);
            $quadri->proponi('VK024001', min($eccedenza, $compensazioni[$dichiarazione->soggetto->id] ?? 0));
            $this->avvisi[] = 'Rigo VK24: eccedenza di credito compensata con i debiti delle altre società del gruppo, ripartita in proporzione tra le società a credito; deve coincidere con l\'attestazione della controllante (VS campo 7).';
            // eccedenza non compensata chiesta a rimborso dalla controllante (VS campo 10)
            $chiesto = $partecipazione['partecipante'] !== null ? $this->getRimborsoChiesto($gruppo, $dichiarazione->soggetto, $dichiarazione->anno) : null;
            if (($chiesto !== null) && ($quadri->getImporto('VK023001') > $quadri->getImporto('VK024001'))) {
                $quadri->proponi('VK025001', min($chiesto, $quadri->getImporto('VK023001') - $quadri->getImporto('VK024001')));
            }
        }
        $trasferite = array_filter($liquidazioni, fn(LiquidazioneIva $liquidazione) => $liquidazione->isTrasferita() && (($liquidazione->periodicita === PeriodicitaIva::mensile) || ($liquidazione->periodo < 4)));
        $quadri->proponi('VK026001', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->creditiImposta, $trasferite))));
        $quadri->proponi('VK027001', (int) round($trasferimenti['interessi']));
        $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($dichiarazione->soggetto, $dichiarazione->anno);
        if ($interoAnno && ($partecipazione['partecipante'] !== null) && ($acconto !== null) && $acconto->isDovuto()) {
            $quadri->proponi('VK028001', (int) round($acconto->importo));
        }
        if ($interoAnno === false) {
            // sezione 3: dati del solo periodo di controllo
            $quadri->proponi('VK030001', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->ivaEsigibile, $trasferite))));
            $quadri->proponi('VK031001', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->ivaDetraibile, $trasferite))));
            $quadri->proponi('VK032001', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->interessi, $trasferite))));
            $quadri->proponi('VK033001', $quadri->getImporto('VK026001'));
            $quadri->proponi('VK034001', (int) round(array_sum(array_map(fn(LiquidazioneIva $liquidazione) => $liquidazione->versamentiAuto, $trasferite))));
            $this->avvisi[] = sprintf('IVA di gruppo: controllo cessato nel %d (ultimo mese %d): sezione 3 del quadro VK dal periodo di controllo, saldo annuale nei righi VX1 o VX2.',
                    $dichiarazione->anno, $partecipazione['ultimoMese']);
        }
        $this->avvisi[] = sprintf('IVA di gruppo (controllante %s): quadro VK proposto dalle liquidazioni trasferite; l\'IVA periodica trasferita è nel rigo VL31 e non nel VL30.', $controllante->nominativo);
    }

    /**
     * Eccedenze di credito compensate per società (VK24 e VS campo 7):
     * con i debiti netti delle società a debito, ripartiti in proporzione
     * ai crediti netti; le eccedenze delle società senza dichiarazione
     * nella suite sono stimate dalle liquidazioni trasferite.
     *
     * @param array<int, int> $note eccedenze già note per soggetto
     * @return array<int, int>
     */
    public function getCompensazioni(GruppoIva $gruppo, int $anno, array $note = []): array
    {
        $saldi = [];
        foreach ((new LiquidazioneGruppo($this->dataMapper))->getSocieta($gruppo, $anno) as ['soggetto' => $soggetto]) {
            if (isset($note[$soggetto->id])) {
                $saldi[$soggetto->id] = $note[$soggetto->id];
                continue;
            }
            $quadri = $this->getQuadriSocieta($soggetto, $anno);
            if (($quadri !== null) && ($quadri->get('VK020001', 1) !== null)) {
                $saldi[$soggetto->id] = $quadri->getImporto('VK023001', 1) - $quadri->getImporto('VK022001', 1);
                continue;
            }
            $trasferimenti = self::getTrasferimenti((new LiquidazioneIvaModel($this->dataMapper))->getSequenza($soggetto, $anno));
            $saldi[$soggetto->id] = (int) round($trasferimenti['crediti'] - $trasferimenti['debiti']);
        }
        $crediti = array_sum(array_filter($saldi, fn(int $saldo) => $saldo > 0));
        $debiti = -array_sum(array_filter($saldi, fn(int $saldo) => $saldo < 0));
        $compensazioni = [];
        foreach ($saldi as $id => $saldo) {
            $compensazioni[$id] = ($saldo > 0) && ($crediti > 0) ? (int) round($saldo * min(1, $debiti / $crediti)) : 0;
        }
        return $compensazioni;
    }

    /**
     * Quadro VG della controllante: opzione, ingressi e uscite dal 1°
     * gennaio dell'anno successivo, revoca.
     */
    public function proponiVg(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione): void
    {
        $this->avviaQuadriSuccessivi($quadri, 'VG');
        $successivo = $dichiarazione->anno + 1;
        foreach ((new GruppoIvaModel($this->dataMapper))->getTuttiPerControllante($dichiarazione->soggetto) as $gruppo) {
            if ($gruppo->annoRevoca === $successivo) {
                $quadri->proponi('VG008001', true);
                $this->avvisi[] = 'Quadro VG: revoca della liquidazione IVA di gruppo dal 1° gennaio ' . $successivo . '.';
                return;
            }
            $opzione = $gruppo->annoInizio === $successivo;
            if (($opzione === false) && ($gruppo->isAttivoNel($successivo) === false)) {
                continue;
            }
            $partecipanti = (new PartecipanteGruppoIvaModel($this->dataMapper))->getPartecipanti($gruppo);
            $sezioneUno = array_values(array_filter($partecipanti, fn(PartecipanteGruppoIva $partecipante) => ($partecipante->soloCatena === false)
                                    && ($opzione ? $partecipante->partecipaNel($successivo) : (($partecipante->annoIngresso === $successivo) || ($partecipante->annoUscita === $successivo)))));
            $catena = array_values(array_filter($partecipanti, fn(PartecipanteGruppoIva $partecipante) => $partecipante->soloCatena && ($partecipante->annoIngresso <= $successivo)
                                    && (($partecipante->annoUscita === null) || ($partecipante->annoUscita > $successivo))));
            if (($opzione === false) && (count($sezioneUno) === 0)) {
                continue;
            }
            if ($gruppo->maggioranza !== null) {
                $quadri->proponi('VG001001', $gruppo->dataPossessoMaggioranza?->format('Y-m-d'));
                $quadri->proponi('VG001002', $gruppo->maggioranza->partitaIva);
                $quadri->proponi('VG001003', $gruppo->percentualeMaggioranza);
            }
            // tre righi per sezione in ogni quadro VG: le società successive nei quadri VG n. 2, 3, ...
            foreach ($sezioneUno as $indice => $partecipante) {
                [$modulo, $rigo] = self::getPosizione($indice, 3, 2);
                $uscita = $partecipante->annoUscita === $successivo;
                $quadri->proponi(sprintf('VG%03d002', $rigo), $partecipante->soggetto->partitaIva, $modulo);
                if ($opzione === false) {
                    $quadri->proponi(sprintf('VG%03d00%d', $rigo, $uscita ? 4 : 3), true, $modulo);
                }
                if ($uscita === false) {
                    $quadri->proponi(sprintf('VG%03d001', $rigo), $partecipante->dataPossesso?->format('Y-m-d'), $modulo);
                    $detentore = $partecipante->detentore ?? $gruppo->controllante;
                    if ($partecipante->detentoreEstero) {
                        $quadri->proponi(sprintf('VG%03d007', $rigo), true, $modulo);
                    } else {
                        $quadri->proponi(sprintf('VG%03d005', $rigo), $detentore->partitaIva, $modulo);
                    }
                    $quadri->proponi(sprintf('VG%03d006', $rigo), $partecipante->percentualePossesso, $modulo);
                }
            }
            foreach ($catena as $indice => $partecipante) {
                [$modulo, $rigo] = self::getPosizione($indice, 3, 5);
                $quadri->proponi(sprintf('VG%03d002', $rigo), $partecipante->soggetto->partitaIva, $modulo);
                $quadri->proponi(sprintf('VG%03d001', $rigo), $partecipante->dataPossesso?->format('Y-m-d'), $modulo);
                if ($partecipante->detentoreEstero) {
                    $quadri->proponi(sprintf('VG%03d006', $rigo), true, $modulo);
                } elseif ($partecipante->detentore !== null) {
                    $quadri->proponi(sprintf('VG%03d003', $rigo), $partecipante->detentore->partitaIva, $modulo);
                }
                $quadri->proponi(sprintf('VG%03d004', $rigo), $partecipante->percentualePossesso, $modulo);
            }
            if ((count($sezioneUno) > 3) || (count($catena) > 3)) {
                $this->avvisi[] = 'Quadro VG: più di tre società per sezione, proposte nei quadri VG successivi (n. ' . implode(', ', range(2, intdiv(max(count($sezioneUno), count($catena)) - 1, 3) + 1)) . ').';
            }
            $this->avvisi[] = $opzione ? 'Quadro VG: opzione per la liquidazione IVA di gruppo dal 1° gennaio ' . $successivo . '.'
                        : 'Quadro VG: ingresso o uscita di società controllate dal 1° gennaio ' . $successivo . '.';
            return;
        }
    }

    /**
     * Prospetto IVA 26 PR della controllante (quadri VS, VV, VW, VY).
     */
    public function proponiProspetto(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, GruppoIva $gruppo): void
    {
        $anno = $dichiarazione->anno;
        $servizio = new LiquidazioneGruppo($this->dataMapper);
        $societa = $servizio->getSocieta($gruppo, $anno);
        $this->avviaQuadriSuccessivi($quadri, 'VS');
        $compensazioni = $this->getCompensazioni($gruppo, $anno, [$dichiarazione->soggetto->id => $quadri->getImporto('VK023001', 1) - $quadri->getImporto('VK022001', 1)]);
        $regime = new RegimeIva($this->dataMapper);
        $totali = array_fill_keys(['VL001001', 'VL002001', 'VL027001', 'VL011001', 'VL029001', 'VL036001'], 0);
        $mancanti = [];
        $rigo = 0;
        $conCompensazione = $esonerate = $conRimborso = $rimborsi = $eccedenzeDebito = 0;
        foreach ($societa as ['soggetto' => $soggetto, 'partecipante' => $partecipante]) {
            // dodici società per quadro VS: le successive nei quadri VS n. 2, 3, ...
            [$modulo, $riga] = self::getPosizione($rigo, 12, 1);
            $rigo++;
            $proprie = $soggetto->id === $dichiarazione->soggetto->id ? $quadri : $this->getQuadriSocieta($soggetto, $anno);
            $ultimoMese = $partecipante === null ? 12 : $partecipante->getUltimoMese($anno, $regime->getPeriodicita($soggetto, $anno));
            $quadri->proponi(sprintf('VS%03d001', $riga), $soggetto->partitaIva, $modulo);
            $quadri->proponi(sprintf('VS%03d003', $riga), $ultimoMese, $modulo);
            $quadri->proponi(sprintf('VS%03d006', $riga), $proprie?->getImporto('VK023001', 1) ?? 0, $modulo);
            $quadri->proponi(sprintf('VS%03d007', $riga), $compensazioni[$soggetto->id] ?? 0, $modulo);
            $quadri->proponi(sprintf('VS%03d005', $riga), (new CreditiTrimestrali($this->dataMapper))->getRimborsoSocieta($gruppo->controllante, $anno, $soggetto->partitaIva), $modulo);
            if (($compensazioni[$soggetto->id] ?? 0) > 0) {
                $conCompensazione++;
            }
            if ($proprie === null) {
                $mancanti[] = $soggetto->nominativo;
                $trasferimenti = self::getTrasferimenti((new LiquidazioneIvaModel($this->dataMapper))->getSequenza($soggetto, $anno));
                $eccedenzeDebito += (int) round(max(0.0, $trasferimenti['debiti'] - $trasferimenti['crediti']));
                continue;
            }
            $eccedenzeDebito += $proprie->getImporto('VK022001', 1);
            // esonero dalle garanzie della società (istruzioni quadro VS, campo 8)
            $sottoscritta = isset($proprie->getDichiarazione()->id) && (count((new SottoscrizioneControlloModel($this->dataMapper))->getSottoscrizioni($proprie->getDichiarazione())) > 0);
            $quadri->proponi(sprintf('VS%03d008', $riga), $proprie->getDichiarazione()->getCodiceEsoneroGaranzie(['visto' => '1', 'collaborativo' => '2', 'isa' => '3', 'concordato' => '4'], $sottoscritta), $modulo);
            if ($quadri->get(sprintf('VS%03d008', $riga), $modulo) !== null) {
                $esonerate++;
            }
            // rimborso annuale chiesto per la società (campo 10 a mano): causale ed erogazione prioritaria
            $quotaRimborso = $quadri->getImporto(sprintf('VS%03d010', $riga), $modulo);
            if ($quotaRimborso > 0) {
                $rimborsi += $quotaRimborso;
                $conRimborso++;
                $presupposti = (new RimborsoIva($this->dataMapper))->getPresuppostiSocieta($soggetto, $anno, $proprie->getImporto('VK023001', 1) - $proprie->getImporto('VK024001', 1), $proprie);
                $quadri->proponi(sprintf('VS%03d009', $riga), $presupposti['causale'], $modulo);
                $quadri->proponi(sprintf('VS%03d011', $riga), $presupposti['priorita'], $modulo);
                $quadri->proponi(sprintf('VS%03d012', $riga), $presupposti['priorita'] === '6' ? $presupposti['scissione'] : null, $modulo);
                if ($presupposti['causale'] === null) {
                    $this->avvisi[] = sprintf('Quadro VS, %s: nessuna causale di rimborso risulta dalla contabilità della società; indicarla a mano (campo 9).', $soggetto->nominativo);
                }
            }
            $cessata = $ultimoMese < 12;
            $totali['VL001001'] += $proprie->getImporto($cessata ? 'VK030001' : 'VL001001', 1);
            $totali['VL002001'] += $proprie->getImporto($cessata ? 'VK031001' : 'VL002001', 1);
            $totali['VL027001'] += $proprie->getImporto($cessata ? 'VK033001' : 'VL027001', 1);
            $totali['VL029001'] += $proprie->getImporto($cessata ? 'VK034001' : 'VL029001', 1);
            $totali['VL011001'] += $cessata ? 0 : $proprie->getImporto('VL011001', 1);
            $totali['VL036001'] += $cessata ? 0 : $proprie->getImporto('VL036001', 1);
        }
        if ($rigo > 12) {
            $this->avvisi[] = 'Quadro VS: più di dodici società, proposte nei quadri VS successivi (righi VS20-VS22 nel solo primo quadro).';
        }
        $quadri->proponi('VS020001', $rimborsi);
        $quadri->proponi('VS020002', $conRimborso);
        $quadri->proponi('VS021001', count($societa));
        $quadri->proponi('VS022001', $conCompensazione);
        $quadri->proponi('VS022002', $esonerate);
        if (count($mancanti) > 0) {
            $this->avvisi[] = 'Prospetto IVA 26 PR: dichiarazioni IVA ' . ($anno + 1) . ' non predisposte nella suite per ' . implode(', ', $mancanti)
                    . ': i loro dati mancano dal quadro VW (predisporle e riproporre la dichiarazione della controllante).';
        }
        // liquidazioni del gruppo nella sequenza dei riporti, con i trimestri separati delle società trimestrali
        $liquidazioni = (new LiquidazioneGruppoIvaModel($this->dataMapper))->getSequenza($gruppo, $anno);
        $periodicita = $servizio->getPeriodicita($gruppo, $anno);
        $this->proponiVv($quadri, $dichiarazione, $gruppo, $liquidazioni, $periodicita);
        // VW: liquidazione annuale del gruppo
        $quadri->proponi('VW001001', $totali['VL001001']);
        $quadri->proponi('VW002001', $totali['VL002001']);
        $quadri->proponi('VW003001', max(0, $totali['VL001001'] - $totali['VL002001']));
        $quadri->proponi('VW004001', max(0, $totali['VL002001'] - $totali['VL001001']));
        $periodiche = array_filter($liquidazioni, fn(LiquidazioneGruppoIva $liquidazione) => ($liquidazione->periodicita === PeriodicitaIva::mensile) || ($liquidazione->periodo < 4));
        $quadri->proponi('VW023001', (int) round(array_sum(array_map(fn(LiquidazioneGruppoIva $liquidazione) => $liquidazione->interessiTrasferiti, $periodiche))));
        // crediti trimestrali del gruppo: rimborsi richiesti (TE50) e compensati in F24 dalla controllante
        $trimestrali = new CreditiTrimestrali($this->dataMapper);
        $quadri->proponi('VW020001', $trimestrali->getRimborsiRichiesti($gruppo->controllante, $anno));
        $entro = CreditiTrimestrali::getDataLimite($dichiarazione);
        $quadri->proponi('VW022001', $trimestrali->getCompensati($gruppo->controllante, $anno, $entro));
        $quadri->proponi('VW025001', $this->getCreditoGruppoPrecedente($gruppo, $dichiarazione));
        $this->proponiGaranzie($quadri, $gruppo->controllante, $anno, $eccedenzeDebito, $entro);
        $quadri->proponi('VW027001', $totali['VL027001']);
        $quadri->proponi('VW028002', $totali['VL011001']);
        $quadri->proponi('VW028001', $totali['VL011001']);
        $quadri->proponi('VW029001', $totali['VL029001']);
        $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($gruppo->controllante, $anno, $gruppo);
        $accontoDovuto = ($acconto !== null) && $acconto->isDovuto() ? $acconto->importo : 0.0;
        $quadri->proponi('VW030002', (int) round(array_sum(array_map(fn(LiquidazioneGruppoIva $liquidazione) => $liquidazione->importoDovuto, $periodiche)) + $accontoDovuto));
        $quadri->proponi('VW030003', $this->getVersamentiGruppo($gruppo, $anno));
        $quadri->proponi('VW030001', max($quadri->getImporto('VW030002'), $quadri->somma('VW030003', 'VW030004', 'VW030005')));
        $quadri->proponi('VW036001', $totali['VL036001']);
        self::calcolaVw($quadri);
        $quadri->proponi('VY001001', $quadri->getImporto('VW038001') > 10 ? $quadri->getImporto('VW038001') - $quadri->getImporto('VW040001') : 0);
        $quadri->proponi('VY002001', $quadri->getImporto('VW039001') + $quadri->getImporto('VW040001'));
        $this->proponiRimborsoGruppo($quadri, $dichiarazione, $gruppo);
        $quadri->proponi('VY005001', max(0, $quadri->getImporto('VY002001') - $quadri->getImporto('VY002002') + $quadri->getImporto('VY003001') - $quadri->getImporto('VY004001') - $quadri->getImporto('VY006002')));
        $this->avvisi[] = 'Prospetto IVA 26 PR: quadri VS, VW, VY e VZ proposti dalle dichiarazioni delle società partecipanti, dalle liquidazioni di gruppo, dai modelli IVA TR e da Deleghe; le quote di rimborso per società (VS campo 10) e il rigo VW26 a mano.';
    }

    /**
     * Credito di gruppo dell'anno precedente: parte compensata in F24
     * (VW24, oltre la parte del VL9 della controllante) e parte compensata
     * con le eccedenze di debito delle società nell'anno (VK22, compreso il
     * conguaglio annuale), garantita dalla controllante (VS30): si presume
     * compensata con precedenza sugli altri crediti.
     */
    public function proponiGaranzie(QuadriDichiarazione $quadri, Soggetto $controllante, int $anno, int $eccedenzeDebito, SismaDate $entro): void
    {
        $quadri->proponi('VW024001', min($quadri->getImporto('VW025001'), max(0, $this->getCompensatoAnnoPrecedente($controllante, $anno, $entro) - $quadri->getImporto('VL009001', 1))));
        $quadri->proponi('VS030001', min(max(0, $quadri->getImporto('VW025001') - $quadri->getImporto('VW024001')), $eccedenzeDebito));
        if ($quadri->getImporto('VS030001') > 0) {
            $this->avvisi[] = 'Rigo VS30: credito di gruppo dell\'anno precedente compensato con i debiti trasferiti dalle società; la controllante presta le garanzie dell\'art. 6 del D.M. 13 dicembre 1979.';
        }
    }

    /**
     * Rimborso del gruppo (VY4): quote per società del quadro VS (VS20) e,
     * se chiesta a mano una parte maggiore, minore eccedenza detraibile del
     * triennio del gruppo con il quadro VZ dai prospetti dei due anni
     * precedenti della controllante.
     */
    public function proponiRimborsoGruppo(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, GruppoIva $gruppo): void
    {
        $quadri->proponi('VY004001', $quadri->getImporto('VS020001'));
        $regime = new RegimeIva($this->dataMapper);
        if (RimborsoIva::isIngressoGruppoIva($regime->getDatiFiscali($gruppo->controllante, $dichiarazione->anno), $regime->getDatiFiscali($gruppo->controllante, $dichiarazione->anno + 1))) {
            // controllante che entra in un Gruppo IVA: il resto del credito si trasferisce
            $quadri->proponi('VY002002', max(0, $quadri->getImporto('VY002001') - $quadri->getImporto('VY004001')));
            $quadri->proponi('VY004003', $quadri->getImporto('VY004001') > 0 ? true : null);
            return;
        }
        $triennio = $quadri->getImporto('VY004001') > $quadri->getImporto('VS020001');
        $model = new DichiarazioneModel($this->dataMapper);
        $precedente = $model->getPerModello($gruppo->controllante, ModelloDichiarativo::iva, $dichiarazione->anno - 1);
        $secondo = $model->getPerModello($gruppo->controllante, ModelloDichiarativo::iva, $dichiarazione->anno - 2);
        $vz = [null, null];
        if (($precedente !== null) && ($secondo !== null) && ($gruppo->annoInizio <= $dichiarazione->anno - 2)) {
            $quadriPrecedente = new QuadriDichiarazione($precedente, $this->dataMapper);
            $vz = [
                (new QuadriDichiarazione($secondo, $this->dataMapper))->getImporto('VY005001', 1) - $quadriPrecedente->getImporto('VW024001', 1),
                $quadriPrecedente->getImporto('VY005001', 1) - $quadri->getImporto('VW024001'),
            ];
        }
        $spetta = ($vz[0] > 0) && ($vz[1] > 0) && ($quadri->getImporto('VY002001') > 0);
        // il quadro VZ si compila solo con la richiesta di rimborso della minore eccedenza del triennio
        $quadri->proponi('VZ001001', $triennio && $spetta ? $vz[0] : null);
        $quadri->proponi('VZ002001', $triennio && $spetta ? $vz[1] : null);
        $minore = $spetta ? min($vz[0], $vz[1], $quadri->getImporto('VY002001')) : 0;
        if ($triennio && ($spetta === false)) {
            $this->avvisi[] = 'Rigo VY4 oltre le quote del quadro VS: per la minore eccedenza detraibile del triennio compilare a mano il quadro VZ (prospetti IVA 26 PR dei due anni precedenti non presenti nella suite o senza eccedenze).';
        } elseif ($triennio && ($quadri->getImporto('VY004001') - $quadri->getImporto('VS020001') > $minore)) {
            $this->avvisi[] = sprintf('Rigo VY4: la minore eccedenza detraibile del triennio del gruppo è di € %s (righi VZ1, VZ2 e VY2).', number_format($minore, 0, ',', '.'));
        } elseif (($triennio === false) && ($minore > 0)) {
            $this->avvisi[] = sprintf('Il gruppo può chiedere a rimborso la minore eccedenza detraibile del triennio (€ %s): indicarla nel rigo VY4 oltre le quote del quadro VS.', number_format($minore, 0, ',', '.'));
        }
    }

    /**
     * Quota di rimborso chiesta dalla controllante per la società (VK25,
     * dal campo 10 del quadro VS del prospetto della controllante).
     */
    public function getRimborsoChiesto(GruppoIva $gruppo, Soggetto $soggetto, int $anno): ?int
    {
        $prospetto = (new DichiarazioneModel($this->dataMapper))->getPerModello($gruppo->controllante, ModelloDichiarativo::iva, $anno);
        if (($prospetto === null) || ($soggetto->partitaIva === null)) {
            return null;
        }
        $quadri = new QuadriDichiarazione($prospetto, $this->dataMapper);
        foreach ($quadri->getModuli() as $modulo) {
            for ($riga = 1; $riga <= 12; $riga++) {
                if ($quadri->get(sprintf('VS%03d001', $riga), $modulo) === $soggetto->partitaIva) {
                    return $quadri->getImporto(sprintf('VS%03d010', $riga), $modulo);
                }
            }
        }
        return null;
    }

    /**
     * Credito IVA dell'anno precedente della controllante (codice 6099)
     * utilizzato in compensazione nelle deleghe F24 fino alla data.
     */
    private function getCompensatoAnnoPrecedente(Soggetto $controllante, int $anno, SismaDate $entro): int
    {
        $trimestrali = new CreditiTrimestrali($this->dataMapper);
        $centesimi = 0;
        foreach ((new CreditoModel($this->dataMapper))->getElenco($controllante) as $credito) {
            if (($credito->codiceTributo?->codice === '6099') && ($credito->annoRiferimento === $anno - 1)) {
                $centesimi += $trimestrali->getUtilizzatoEntro($credito, $entro);
            }
        }
        return (int) round($centesimi / 100);
    }

    /**
     * Righi VW32-VW39 e VW41 con le formule delle istruzioni.
     */
    public static function calcolaVw(QuadriDichiarazione $quadri): void
    {
        $debiti = $quadri->somma('VW003001', 'VW020001', 'VW022001', 'VW023001', 'VW024001');
        $creditiSenzaVersamenti = $quadri->somma('VW004001', 'VW025001', 'VW026001', 'VW026002', 'VW027001', 'VW028001', 'VW029001', 'VW031001');
        $crediti = $creditiSenzaVersamenti + $quadri->getImporto('VW030001');
        $versati = $creditiSenzaVersamenti + $quadri->somma('VW030003', 'VW030004', 'VW030005');
        $quadri->proponi('VW032001', max(0, $debiti - $crediti));
        $quadri->proponi('VW033001', ($crediti - $debiti > 0) && ($versati - $debiti > 0) ? $versati - $debiti : 0);
        $dovuto = $quadri->somma('VW032001', 'VW036001') - $quadri->somma('VW033001', 'VW034001');
        $quadri->proponi('VW038001', max(0, $dovuto));
        $quadri->proponi('VW039001', max(0, -$dovuto));
        $quadri->proponi('VW041001', max(0, $quadri->getImporto('VW030002') - $quadri->somma('VW030003', 'VW030004', 'VW030005')));
        $quadri->proponi('VW041002', max(0, max(0, $crediti - $debiti) - $quadri->getImporto('VW033001')));
    }

    /**
     * Quadro VV per i periodi senza comunicazione del gruppo inviata.
     *
     * @param LiquidazioneGruppoIva[] $liquidazioni
     */
    private function proponiVv(QuadriDichiarazione $quadri, Dichiarazione $dichiarazione, GruppoIva $gruppo, array $liquidazioni, PeriodicitaIva $periodicita): void
    {
        $comunicazioni = (new ComunicazioneLipeModel($this->dataMapper))->getPerAnno($gruppo->controllante, $dichiarazione->anno, $gruppo);
        $nonInviati = [];
        foreach ($liquidazioni as $liquidazione) {
            $periodo = $liquidazione->periodo;
            $mensile = $liquidazione->periodicita === PeriodicitaIva::mensile;
            $trimestre = $mensile ? intdiv($periodo - 1, 3) + 1 : $periodo;
            if ((($mensile === false) && ($periodo === 4)) || (isset($comunicazioni[$trimestre]) && ($comunicazioni[$trimestre]->stato === StatoComunicazioneLipe::inviata))) {
                continue;
            }
            $rigo = $mensile ? $periodo + intdiv($periodo - 1, 3) : $periodo * 4;
            $saldo = $liquidazione->importoDovuto + $liquidazione->debitoRinviato - $liquidazione->creditoFinale;
            $quadri->proponi(sprintf('VV%03d001', $rigo), (int) round(max(0, -$saldo)));
            $quadri->proponi(sprintf('VV%03d002', $rigo), (int) round(max(0, $saldo)));
            if ($liquidazione->trimestreAnticipato !== null) {
                $quadri->proponi(sprintf('VV%03d003', $liquidazione->trimestreAnticipato * 4), true);
            }
            if ($liquidazione->acconto > 0) {
                $quadri->proponi('VV017001', (int) round($liquidazione->acconto));
                $acconto = (new AccontoIvaModel($this->dataMapper))->getPerAnno($gruppo->controllante, $dichiarazione->anno, $gruppo);
                $quadri->proponi('VV017002', ['S' => '1', 'P' => '2', 'A' => '3'][$acconto?->metodo->value ?? 'S']);
            }
            $nonInviati[] = $liquidazione->getDescrizionePeriodo();
        }
        if (count($nonInviati) > 0) {
            $this->avvisi[] = 'Quadro VV compilato per i periodi senza comunicazione del gruppo inviata: ' . implode(', ', $nonInviati) . '.';
        }
    }

    /**
     * Credito di gruppo dell'anno precedente non chiesto a rimborso (VY5
     * della dichiarazione dell'anno precedente della controllante).
     */
    public function getCreditoGruppoPrecedente(GruppoIva $gruppo, Dichiarazione $dichiarazione): int
    {
        if ($gruppo->annoInizio >= $dichiarazione->anno) {
            return 0;
        }
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($gruppo->controllante, ModelloDichiarativo::iva, $dichiarazione->anno - 1);
        if ($precedente === null) {
            $this->avvisi[] = 'Rigo VW25: dichiarazione IVA dell\'anno precedente della controllante non presente nella suite, indicare a mano il credito di gruppo (VY5).';
            return 0;
        }
        return (new QuadriDichiarazione($precedente, $this->dataMapper))->getImporto('VY005001', 1);
    }

    /**
     * Versamenti periodici e acconto del gruppo nelle deleghe trasmesse o
     * quietanzate (VW30, campo 3).
     */
    private function getVersamentiGruppo(GruppoIva $gruppo, int $anno): int
    {
        $versato = 0.0;
        $versamenti = (new VersamentoModel($this->dataMapper))->getPerPeriodo($gruppo->studio, new SismaDate($anno . '-01-01'), new SismaDate(($anno + 1) . '-12-31'), $gruppo->controllante);
        foreach ($versamenti as $versamento) {
            if (($versamento->annoRiferimento === $anno) && in_array($versamento->codiceTributo?->codice, PropostaIva::CODICI_VERSAMENTI_PERIODICI, true) && self::isVersamentoGruppo($versamento->riferimentoOrigine)
                    && in_array($versamento->delega?->stato, [StatoDelega::trasmessa, StatoDelega::quietanzata], true)) {
                $versato += $versamento->importoDebito;
            }
        }
        return (int) round($versato);
    }

    private function getQuadriSocieta(Soggetto $soggetto, int $anno): ?QuadriDichiarazione
    {
        $dichiarazione = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::iva, $anno);
        return $dichiarazione === null ? null : new QuadriDichiarazione($dichiarazione, $this->dataMapper);
    }

    /**
     * Versamento del gruppo registrato dalla liquidazione o dall'acconto
     * del gruppo (non della controllante come singola società).
     */
    public static function isVersamentoGruppo(?string $riferimento): bool
    {
        return ($riferimento !== null) && (str_starts_with($riferimento, 'iva-gruppo-') || str_ends_with($riferimento, '-gruppo'));
    }

    /**
     * Toglie i valori proposti del quadro nei quadri successivi al primo
     * (Mod. n. 2, 3, ...), riproposti dalla proposta corrente.
     */
    private function avviaQuadriSuccessivi(QuadriDichiarazione $quadri, string $quadro): void
    {
        $corrente = $quadri->getModuloCorrente();
        foreach ($quadri->getModuli() as $modulo) {
            if ($modulo > 1) {
                $quadri->usaModulo($modulo)->avviaProposta($quadro);
            }
        }
        $quadri->usaModulo($corrente);
    }

    /**
     * Quadro (Mod. n.) e rigo dell'elemento di un elenco distribuito su più
     * quadri con un numero fisso di righi per quadro.
     *
     * @return array{0: int, 1: int}
     */
    public static function getPosizione(int $indice, int $righiPerQuadro, int $primoRigo): array
    {
        return [intdiv($indice, $righiPerQuadro) + 1, $indice % $righiPerQuadro + $primoRigo];
    }
}
