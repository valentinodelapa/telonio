<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\Dichiarazione;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\CustomTypes\SismaDateTime;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Proposta del modello Redditi persone fisiche dai dati della suite:
 * terreni (RA) e fabbricati (RB) dai possessi dell'Anagrafica con i
 * contratti di locazione, cedolare secca (LC); rate dei bonus edilizi
 * (quadro RP, sezioni III e IV) dall'archivio degli interventi
 * (PropostaBonusEdilizi); lavoro dipendente e
 * pensione (RC) dalle Certificazioni Uniche, IRPEF (RN), addizionali (RV) e
 * risultato (RX) con PropostaIrpef.
 *
 * Righi oltre quelli del quadro (22 terreni, 9 fabbricati, 3 contratti
 * della sezione II, 2 CIN) vanno nei moduli successivi ("Mod. N."); i
 * totali (RA23, RB10, RB11) sono nel primo.
 */
class PropostaRedditi
{

    public const QUADRI = ['FA', 'RA', 'RB', 'LC', 'RH', 'RL', 'RE', 'RG', 'RF', 'LM', 'RC', 'RP', 'RS', 'RM', 'RT', 'RW', 'CE', 'CR', 'RN', 'RV', 'RX'];
    private const RIGHI_RA = 22;
    private const RIGHI_RB = 9;

    /** @var string[] */
    private array $avvisi = [];

    private Soggetto $soggetto;
    private int $anno;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[] avvisi della proposta
     */
    public function proponi(Dichiarazione $dichiarazione, ?QuadriDichiarazione $quadri = null, bool $salva = true): array
    {
        $this->avvisi = [];
        $quadri ??= new QuadriDichiarazione($dichiarazione, $this->dataMapper);
        $soggetto = $this->soggetto = $dichiarazione->soggetto;
        $anno = $this->anno = $dichiarazione->anno;

        $terreni = new PropostaTerreni($this->dataMapper);
        $righiRa = $terreni->getRighi($soggetto, $anno);
        array_push($this->avvisi, ...$terreni->getAvvisi());
        $fabbricati = new PropostaFabbricati($this->dataMapper);
        $righiRb = $fabbricati->getRighi($soggetto, $anno);
        array_push($this->avvisi, ...$fabbricati->getAvvisi());
        $familiari = new PropostaFamiliari($this->dataMapper);
        $pagineFa = PropostaFamiliari::impagina($familiari->getRighi($soggetto, $anno));
        array_push($this->avvisi, ...$familiari->getAvvisi());

        $bonus = (new PropostaBonusEdilizi($this->dataMapper))->carica($soggetto, $anno);
        $esteri = (new PropostaEsteri($this->dataMapper))->carica($soggetto, $anno);
        $plusvalenze = (new PropostaPlusvalenze($this->dataMapper))->carica($soggetto, $anno);
        $partecipazioni = (new PropostaPartecipazioni($this->dataMapper))->carica($soggetto, $anno);
        array_push($this->avvisi, ...$partecipazioni->getAvvisi());
        array_push($this->avvisi, ...$esteri->getAvvisi());

        $moduli = max(1, (int) ceil(count($righiRa) / self::RIGHI_RA), (int) ceil(count($righiRb) / self::RIGHI_RB), count($pagineFa), $bonus->getModuliNecessari(), $esteri->getModuliNecessari(), $plusvalenze->getModuliNecessari(), $partecipazioni->getModuliNecessari(), max($quadri->getModuli() ?: [1]));
        for ($modulo = 1; $modulo <= $moduli; $modulo++) {
            $quadri->usaModulo($modulo)->avviaProposta(...self::QUADRI);
        }
        PropostaFamiliari::proponi($quadri, $pagineFa);
        $bonus->proponiRighi($quadri, $soggetto);
        $quadri->usaModulo(1);
        $this->proponiTerreni($quadri, $righiRa);
        $cedolare = $this->proponiFabbricati($quadri, $righiRb);
        $quadri->usaModulo(1);
        $this->proponiCedolare($quadri, $cedolare);
        array_push($this->avvisi, ...(new PropostaImpresa($this->dataMapper))->proponi($soggetto, $anno, $quadri));
        $esteri->proponi($quadri, $soggetto, $anno);
        $plusvalenze->proponi($quadri);
        $partecipazioni->proponi($quadri);
        $crediti = new PropostaCrediti($this->dataMapper);
        $crediti->proponiCrediti($quadri, $soggetto, $anno);
        array_push($this->avvisi, ...$crediti->getAvvisi());
        $altri = new PropostaAltriRedditi($this->dataMapper);
        $altri->proponi($quadri, $soggetto, $anno);
        array_push($this->avvisi, ...$altri->getAvvisi());
        array_push($this->avvisi, ...(new PropostaIrpef($this->dataMapper))->proponi($dichiarazione, $quadri, $bonus));
        $quadri->usaModulo(1);

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
     * Riepilogo per la scheda della dichiarazione (importi non nulli).
     *
     * @return array<string, int>
     */
    public static function getRiepilogo(QuadriDichiarazione $quadri): array
    {
        $righi = [
            'Redditi dei terreni (RA23, col. 11 e 12)' => $quadri->getImporto('RA023011', 1) + $quadri->getImporto('RA023012', 1),
            'Redditi dei fabbricati in IRPEF (RB10, col. 13 e 19)' => $quadri->getImporto('RB010013', 1) + $quadri->getImporto('RB010019', 1),
            'Deduzione abitazione principale (RB10, col. 19)' => $quadri->getImporto('RB010019', 1),
            'Cedolare secca dovuta (LC1, col. 3)' => $quadri->getImporto('LC001003', 1),
            'Cedolare secca a debito (LC1, col. 12)' => $quadri->getImporto('LC001012', 1),
            'Cedolare secca a credito (LC1, col. 13)' => $quadri->getImporto('LC001013', 1),
            'Acconti della cedolare secca (LC2)' => $quadri->getImporto('LC002001', 1) + $quadri->getImporto('LC002002', 1),
        ];
        return array_filter($righi);
    }

    /**
     * @param RigoTerreno[] $righi
     */
    private function proponiTerreni(QuadriDichiarazione $quadri, array $righi): void
    {
        if (count($righi) === 0) {
            return;
        }
        $calcolo = new CalcoloTerreni();
        $calcolo->calcola($righi);
        foreach ($righi as $indice => $rigo) {
            $quadri->usaModulo(intdiv($indice, self::RIGHI_RA) + 1);
            $codice = fn(int $colonna) => sprintf('RA%03d%03d', $indice % self::RIGHI_RA + 1, $colonna);
            $quadri->proponi($codice(1), $rigo->dominicale ?: null);
            $quadri->proponi($codice(2), $rigo->titolo);
            $quadri->proponi($codice(3), $rigo->agrario ?: null);
            $quadri->proponi($codice(4), $rigo->giorni);
            $quadri->proponi($codice(5), $rigo->percentuale);
            $quadri->proponi($codice(6), $rigo->canone);
            $quadri->proponi($codice(7), $rigo->casiParticolari);
            $quadri->proponi($codice(8), $rigo->continuazione ?: null);
            $quadri->proponi($codice(9), $rigo->imuNonDovuta ?: null);
            $quadri->proponi($codice(10), $rigo->iap ?: null);
            foreach ($rigo->redditi as $colonna => $importo) {
                $quadri->proponi($codice($colonna), $importo);
            }
            $quadri->proponi($codice(16), $rigo->produzioneVegetali);
        }
        $quadri->usaModulo(1);
        foreach ($calcolo->totali($righi) as $colonna => $importo) {
            $quadri->proponi(sprintf('RA023%03d', $colonna), $importo ?: null);
        }
    }

    /**
     * @param RigoFabbricato[] $righi
     * @return int imposta cedolare secca (RB11, col. 4)
     */
    private function proponiFabbricati(QuadriDichiarazione $quadri, array $righi): int
    {
        if (count($righi) === 0) {
            return 0;
        }
        $calcolo = new CalcoloFabbricati();
        $calcolo->calcola($righi);
        $contratti = [];
        $cin = [];
        foreach ($righi as $indice => $rigo) {
            $modulo = intdiv($indice, self::RIGHI_RB) + 1;
            $numero = $indice % self::RIGHI_RB + 1;
            $quadri->usaModulo($modulo);
            $codice = fn(int $colonna) => sprintf('RB%03d%03d', $numero, $colonna);
            $quadri->proponi($codice(1), $rigo->rendita);
            $quadri->proponi($codice(2), $rigo->utilizzo);
            $quadri->proponi($codice(3), $rigo->giorni);
            $quadri->proponi($codice(4), $rigo->percentuale);
            $quadri->proponi($codice(5), $rigo->codiceCanone);
            $quadri->proponi($codice(6), $rigo->canone);
            $quadri->proponi($codice(7), $rigo->casiParticolari);
            $quadri->proponi($codice(8), $rigo->continuazione ?: null);
            $quadri->proponi($codice(9), $rigo->codiceComune);
            $quadri->proponi($codice(11), $rigo->cedolare);
            $quadri->proponi($codice(12), $rigo->casiImu);
            foreach ($rigo->redditi as $colonna => $importo) {
                $quadri->proponi($codice($colonna), $importo);
            }
            $quadri->proponi($codice(20), $rigo->altriDati);
            // sezione II (locazioni in Abruzzo) e III (CIN delle locazioni brevi e turistiche), sul primo rigo del fabbricato
            if (($rigo->contratto !== null) && ($rigo->utilizzo === 14)) {
                $contratti[] = ['rigo' => $numero, 'modulo' => $modulo, 'contratto' => $rigo->contratto];
            }
            if (($rigo->contratto !== null) && in_array($rigo->cedolare, [2, 3], true) && !isset($cin[(string) $rigo->fabbricato])) {
                $cin[(string) $rigo->fabbricato] = ['rigo' => $numero, 'modulo' => $modulo, 'cin' => $rigo->contratto->cin];
            }
        }
        $this->proponiContratti($quadri, $contratti);
        $this->proponiCin($quadri, array_values($cin));
        ['totali' => $totali, 'imposte' => $imposte] = $calcolo->totali($righi);
        $quadri->usaModulo(1);
        foreach ($totali as $colonna => $importo) {
            $quadri->proponi(sprintf('RB010%03d', $colonna), $importo ?: null);
        }
        foreach ($imposte as $colonna => $importo) {
            $quadri->proponi(sprintf('RB011%03d', $colonna), $importo ?: null);
        }
        return $imposte[4];
    }

    private function proponiContratti(QuadriDichiarazione $quadri, array $contratti): void
    {
        foreach ($contratti as $indice => ['rigo' => $rigo, 'modulo' => $modulo, 'contratto' => $contratto]) {
            $quadri->usaModulo(intdiv($indice, 3) + 1);
            $codice = fn(string $colonna) => sprintf('RB%03d%s', 21 + $indice % 3, $colonna);
            $quadri->proponi($codice('001'), $rigo);
            $quadri->proponi($codice('002'), $modulo);
            if ($contratto->codiceIdentificativo !== null) {
                $quadri->proponi($codice('007'), $contratto->codiceIdentificativo);
            } elseif ($contratto->dataRegistrazione !== null) {
                $quadri->proponi($codice('003'), $contratto->dataRegistrazione->format('Y-m-d'));
                $quadri->proponi($codice('004'), $contratto->serieRegistrazione);
                [$numero, $sottonumero] = array_pad(explode('/', (string) $contratto->numeroRegistrazione, 2), 2, null);
                $quadri->proponi($codice('A05'), $numero === '' ? null : $numero);
                $quadri->proponi($codice('B05'), $sottonumero);
                $quadri->proponi($codice('006'), $contratto->codiceUfficio);
            } else {
                $quadri->proponi($codice('008'), true);
                $this->avvisi[] = 'Quadro RB, sezione II: contratto ' . $contratto->getDescrizione() . ' senza estremi di registrazione, segnato come non superiore a 30 giorni.';
            }
        }
    }

    private function proponiCin(QuadriDichiarazione $quadri, array $cin): void
    {
        foreach ($cin as $indice => ['rigo' => $rigo, 'modulo' => $modulo, 'cin' => $codice]) {
            $quadri->usaModulo(intdiv($indice, 2) + 1);
            $numero = 24 + $indice % 2;
            $quadri->proponi(sprintf('RB%03d001', $numero), $rigo);
            $quadri->proponi(sprintf('RB%03d002', $numero), $modulo);
            $quadri->proponi(sprintf('RB%03d003', $numero), $codice);
            if ($codice === null) {
                $this->avvisi[] = 'Quadro RB, sezione III: manca il CIN della locazione breve del rigo ' . $rigo . ' (obbligatorio dal 2025, da indicare nel contratto).';
            }
        }
    }

    /**
     * Rigo LC1 e acconto della cedolare secca (LC2): dovuto per il 100
     * per cento se la differenza è almeno 52 euro, in due rate (40 e 60
     * per cento) se la prima supera 103 euro.
     */
    private function proponiCedolare(QuadriDichiarazione $quadri, int $imposta): void
    {
        if ($imposta === 0) {
            return;
        }
        $quadri->proponi('LC001001', $imposta);
        $totale = $imposta + $quadri->getImporto('LC001002');
        $quadri->proponi('LC001003', $totale);
        $differenza = $totale - $quadri->getImporto('LC001004');
        $quadri->proponi('LC001005', max(0, $differenza) ?: null);
        $quadri->proponi('LC001008', (int) round(VersamentiRedditi::getAccontiVersati($this->dataMapper, $this->soggetto, $this->anno)['cedolare']) ?: null);
        $saldo = max(0, $differenza) - $quadri->getImporto('LC001006') + $quadri->getImporto('LC001007') - $quadri->getImporto('LC001008') - $quadri->getImporto('LC001010') + $quadri->getImporto('LC001011');
        $quadri->proponi('LC001012', $saldo > 0 ? $saldo : null);
        $quadri->proponi('LC001013', $saldo < 0 ? -$saldo : null);
        if ($differenza >= 52) {
            $primo = (int) round($differenza * 0.4);
            $quadri->proponi('LC002001', $primo > 103 ? $primo : null);
            $quadri->proponi('LC002002', $primo > 103 ? $differenza - $primo : $differenza);
        }
    }
}
