<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Entities\AttivitaEstera;
use Dichiarativi\Application\Enumerations\CodiceInvestimentoEstero;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\AttivitaEsteraModel;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Quadro RW dall'archivio delle attività estere del contribuente: righi da
 * RW1 a RW5 (Services\Redditi\CalcoloQuadroRw) nei moduli necessari, righi
 * RW6 (IVAFE), RW7 (IVIE) e RW8 (cripto-attività) del primo modulo con il
 * totale di tutti i moduli, l'eccedenza della dichiarazione dell'anno
 * prima (col. 5 dei righi RX26, RX25 e RX27) e gli acconti versati in
 * Deleghe (codici 4047 e 4048, 4044 e 4045, 1728 e 1729), e righi RX25,
 * RX26 e RX27 con i crediti proposti in compensazione.
 *
 * I proventi incassati direttamente dall'estero (senza intermediari
 * residenti) vanno nel rigo RM31, un modulo per attività, con il tipo di
 * reddito dell'appendice delle istruzioni (G interessi dei conti, A
 * interessi delle obbligazioni e dei titoli, B proventi degli OICR esteri,
 * H dividendi, I altri redditi di capitale), il reddito al lordo delle
 * ritenute estere e l'imposta sostitutiva del 26 per cento (art. 18 Tuir);
 * il totale nel rigo RX7. L'opzione per la tassazione ordinaria (col. 7)
 * resta a mano.
 */
class PropostaEsteri
{

    public const RIGHI_RW = 5;

    /** Rigo del totale => [colonna dell'imposta dovuta, rigo RX, chiave degli acconti] */
    private const TOTALI = [6 => [30, 26, 'ivafe'], 7 => [32, 25, 'ivie'], 8 => [34, 27, 'cripto']];

    /** @var AttivitaEstera[] */
    private array $attivita = [];

    /** @var list<array<int, int|string|bool|null>> */
    private array $righi = [];

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    public function carica(Soggetto $soggetto, int $anno): self
    {
        $this->attivita = (new AttivitaEsteraModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        $calcolo = new CalcoloQuadroRw();
        $this->righi = $calcolo->righi($this->attivita);
        $this->avvisi = array_values($calcolo->getAvvisi());
        return $this;
    }

    /**
     * @return AttivitaEstera[]
     */
    public function getAttivita(): array
    {
        return $this->attivita;
    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    public function getModuliNecessari(): int
    {
        return max((int) ceil(count($this->righi) / self::RIGHI_RW), count($this->getProventi()));
    }

    /**
     * Attività con proventi incassati nell'anno (rigo RM31).
     *
     * @return AttivitaEstera[]
     */
    public function getProventi(): array
    {
        return array_values(array_filter($this->attivita, fn(AttivitaEstera $attivita) => ($attivita->proventi ?? 0) > 0));
    }

    /**
     * Tipo di reddito del rigo RM31 (appendice delle istruzioni, "Redditi di
     * capitale di fonte estera soggetti ad imposizione sostitutiva").
     */
    public static function tipoRedditoRm31(AttivitaEstera $attivita): string
    {
        return match (true) {
            $attivita->tipoProventi === 'D' => 'H',
            $attivita->tipoProventi === 'I' && in_array($attivita->codiceInvestimento, [CodiceInvestimentoEstero::contiCorrenti, CodiceInvestimentoEstero::valuteEstere], true) => 'G',
            $attivita->tipoProventi === 'I' && in_array($attivita->codiceInvestimento, [CodiceInvestimentoEstero::obbligazioni, CodiceInvestimentoEstero::titoliPubbliciItaliani, CodiceInvestimentoEstero::depositoTitoli], true) => 'A',
            $attivita->codiceInvestimento === CodiceInvestimentoEstero::titoliNonRappresentativi => 'B',
            default => 'I',
        };
    }

    public function proponi(QuadriDichiarazione $quadri, Soggetto $soggetto, int $anno): void
    {
        foreach ($this->righi as $indice => $rigo) {
            $quadri->usaModulo(intdiv($indice, self::RIGHI_RW) + 1);
            $numero = $indice % self::RIGHI_RW + 1;
            foreach ($rigo as $colonna => $valore) {
                $quadri->proponi(sprintf('RW%03d%03d', $numero, $colonna), $valore === 0 ? null : $valore);
            }
        }
        $imposteRm = 0;
        foreach ($this->getProventi() as $indice => $attivita) {
            $quadri->usaModulo($indice + 1);
            $reddito = (int) round($attivita->proventi);
            $imposta = (int) round($reddito * 0.26);
            $quadri->proponi('RM031001', self::tipoRedditoRm31($attivita));
            $quadri->proponi('RM031002', $attivita->codiceStatoEstero);
            $quadri->proponi('RM031003', $reddito);
            $quadri->proponi('RM031004', 26.0);
            $quadri->proponi('RM031007', $attivita->tassazioneOrdinaria ? true : null);
            $quadri->proponi('RM031008', $attivita->tassazioneOrdinaria ? null : ($imposta ?: null));
            if ($quadri->get('RM031007', $indice + 1) === null) {
                $imposteRm += $quadri->getImporto('RM031008', $indice + 1);
            }
        }
        $quadri->usaModulo(1);
        $quadri->proponi('RX007001', $imposteRm ?: null);
        $this->proponiCreditoEstero($quadri, $anno);
        $precedente = (new DichiarazioneModel($this->dataMapper))->getPerModello($soggetto, ModelloDichiarativo::redditiPf, $anno - 1);
        $quadriPrecedenti = $precedente === null ? null : new QuadriDichiarazione($precedente, $this->dataMapper);
        $acconti = VersamentiRedditi::getAccontiVersati($this->dataMapper, $soggetto, $anno);
        $q = fn(string $codice) => $quadri->getImporto($codice, 1);
        foreach (self::TOTALI as $numero => [$colonna, $rigoRx, $tipo]) {
            $totale = 0;
            foreach ($this->righi as $rigo) {
                $totale += (int) ($rigo[$colonna] ?? 0);
            }
            $codice = fn(int $col) => sprintf('RW%03d%03d', $numero, $col);
            $quadri->proponi($codice(1), $totale ?: null);
            $quadri->proponi($codice(2), $quadriPrecedenti === null ? null : ($quadriPrecedenti->getImporto(sprintf('RX%03d005', $rigoRx), 1) ?: null));
            $quadri->proponi($codice(4), (int) round($acconti[$tipo]) ?: null);
            $saldo = $q($codice(1)) - $q($codice(2)) + $q($codice(3)) - $q($codice(4));
            $quadri->proponi($codice(5), $saldo > 0 ? $saldo : null);
            $quadri->proponi($codice(6), $saldo < 0 ? -$saldo : null);
            // quadro RX: debito o credito, credito proposto in compensazione
            $rx = fn(int $col) => sprintf('RX%03d%03d', $rigoRx, $col);
            $quadri->proponi($rx(1), $saldo > 0 ? $saldo : null);
            $quadri->proponi($rx(2), $saldo < 0 ? -$saldo : null);
            $quadri->proponi($rx(5), max(0, max(0, -$saldo) + $q($rx(3)) - $q($rx(4))) ?: null);
        }
    }

    /**
     * Righi da CE1 a CE3 (Stato, anno, reddito e imposta estera) per i
     * proventi con l'opzione per la tassazione ordinaria, uno per Stato; il
     * credito si calcola nel quadro RN (CalcoloCreditoEstero).
     */
    private function proponiCreditoEstero(QuadriDichiarazione $quadri, int $anno): void
    {
        $stati = [];
        foreach ($this->getProventi() as $attivita) {
            if ($attivita->tassazioneOrdinaria && (($attivita->impostaEstera ?? 0) > 0) && ($attivita->codiceStatoEstero !== null)) {
                $stati[$attivita->codiceStatoEstero] ??= ['reddito' => 0.0, 'imposta' => 0.0];
                $stati[$attivita->codiceStatoEstero]['reddito'] += $attivita->proventi;
                $stati[$attivita->codiceStatoEstero]['imposta'] += $attivita->impostaEstera;
            }
        }
        foreach (array_values(array_keys($stati)) as $indice => $stato) {
            $quadri->usaModulo(intdiv($indice, 3) + 1);
            $c = fn(int $colonna) => sprintf('CE%03d%03d', $indice % 3 + 1, $colonna);
            $quadri->proponi($c(1), $stato);
            $quadri->proponi($c(2), $anno);
            $quadri->proponi($c(3), (int) round($stati[$stato]['reddito']));
            $quadri->proponi($c(4), (int) round($stati[$stato]['imposta']));
        }
        $quadri->usaModulo(1);
    }

    /**
     * Imposta dovuta dell'anno per il calcolo degli acconti (col. 1 dei
     * righi RW6, RW7 e RW8).
     *
     * @return array{ivafe: int, ivie: int, cripto: int}
     */
    public static function getImposteDovute(QuadriDichiarazione $quadri): array
    {
        return ['ivafe' => $quadri->getImporto('RW006001', 1), 'ivie' => $quadri->getImporto('RW007001', 1), 'cripto' => $quadri->getImporto('RW008001', 1)];
    }
}
