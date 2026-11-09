<?php

namespace Dichiarativi\Application\Services\Redditi;

use Anagrafica\Application\Entities\Legame;
use Anagrafica\Application\Entities\Soggetto;
use Anagrafica\Application\Enumerations\RuoloLegame;
use Anagrafica\Application\Models\LegameModel;
use Dichiarativi\Application\Enumerations\ModelloDichiarativo;
use Dichiarativi\Application\Models\CertificazioneUnicaModel;
use Dichiarativi\Application\Models\DichiarazioneModel;
use Dichiarativi\Application\Models\ValoreCertificazioneUnicaModel;
use Dichiarativi\Application\Services\QuadriDichiarazione;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Prospetto dei familiari a carico del modello Redditi PF (istruzioni,
 * capitolo 4; specifiche tecniche, prospetto dei familiari) proposto dai
 * legami familiari dell'Anagrafica:
 *
 * - un rigo per ogni legame vigente nell'anno (più righi se la situazione
 *   cambia nell'anno), con i mesi dal mese di inizio a quello di fine
 *   (art. 12, comma 3, Tuir);
 * - a carico il familiare con reddito complessivo fino a 2.840,51 euro, o
 *   4.000 euro per i figli che nell'anno compiono al massimo 24 anni
 *   (circolare 14/E del 2023): il reddito si legge dalla dichiarazione
 *   Redditi del familiare nella suite (rigo RN1, col. 1, che comprende
 *   cedolare secca e reddito forfetario) o dalle sue Certificazioni Uniche;
 *   senza dati il familiare è proposto a carico con un avviso;
 * - coniuge nel rigo 1, sempre con il codice fiscale e con i mesi solo se a
 *   carico (anche dal legame registrato nella scheda dell'altro coniuge);
 * - figli dal più anziano (il primo nel rigo 2, F o D per la disabilità),
 *   con la percentuale del legame o, senza, 100 se il coniuge è a carico e
 *   50 altrimenti, la colonna 8 per l'affidamento e la colonna 10 con i mesi
 *   da 21 a 29 anni (da 21 anni per i figli con disabilità);
 * - ascendenti conviventi (G) e altri familiari conviventi (P, senza
 *   detrazione ma per gli oneri), al 100 per cento; i non conviventi non
 *   sono riportati.
 *
 * I righi oltre il rigo 5 vanno nei moduli successivi (righi da 3 a 5).
 */
class PropostaFamiliari
{

    public const LIMITE = 2840.51;
    public const LIMITE_FIGLI_24 = 4000.0;

    /** @var string[] */
    private array $avvisi = [];

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * Righi del prospetto: 'coniugi' (rigo 1) e 'familiari' (righi da 2).
     *
     * @return array{coniugi: list<array<string, mixed>>, familiari: list<array<string, mixed>>}
     */
    public function getRighi(Soggetto $soggetto, int $anno): array
    {
        $this->avvisi = [];
        $legameModel = new LegameModel($this->dataMapper);
        $coniugi = [];
        $figli = [];
        $altri = [];
        foreach ($legameModel->getBySoggetto($soggetto) as $legame) {
            $this->aggiungi($legame, $legame->soggettoCollegato, $anno, $coniugi, $figli, $altri);
        }
        foreach ($legameModel->getBySoggettoCollegato($soggetto) as $legame) {
            if ($legame->ruolo === RuoloLegame::coniuge) {
                $this->aggiungi($legame, $legame->soggetto, $anno, $coniugi, $figli, $altri);
            }
        }
        $coniugeACarico = count(array_filter($coniugi, fn(array $rigo) => $rigo['mesi'] !== null)) > 0;
        usort($figli, fn(array $a, array $b) => [$a['nascita'], $a['codiceFiscale']] <=> [$b['nascita'], $b['codiceFiscale']]);
        foreach ($figli as &$figlio) {
            if ($figlio['percentuale'] === null) {
                $figlio['percentuale'] = ($coniugeACarico || $figlio['affidamento']) ? '100' : '50';
            }
            $figlio['affidamento'] = $figlio['affidamento'] && in_array($figlio['percentuale'], ['100', 'C'], true);
        }
        unset($figlio);
        $figliC = array_filter($figli, fn(array $figlio) => $figlio['percentuale'] === 'C');
        if ((count($figliC) > 1) || ((count($figliC) === 1) && (array_key_first($figliC) !== 0))) {
            $this->avvisi[] = 'Prospetto dei familiari: la percentuale C (coniuge mancante) spetta solo al primo figlio, controlla i legami.';
        }
        return ['coniugi' => array_values($coniugi), 'familiari' => array_merge($figli, $altri)];
    }

    /**
     * Righi per modulo: nel primo i righi da 2 a 5, nei successivi da 3 a 5
     * (il rigo 2 è del primo figlio); il coniuge nel rigo 1.
     *
     * @return array<int, array<int, array<string, mixed>>> modulo => rigo => dati
     */
    public static function impagina(array $righi): array
    {
        $pagine = [];
        foreach ($righi['coniugi'] as $indice => $rigo) {
            $pagine[$indice + 1][1] = $rigo;
        }
        $modulo = 1;
        $numero = 2;
        foreach ($righi['familiari'] as $rigo) {
            if ($numero > 5) {
                $modulo++;
                $numero = 3;
            }
            $pagine[$modulo][$numero++] = $rigo;
        }
        ksort($pagine);
        return $pagine;
    }

    /**
     * Prospetto nei moduli (già in proposta).
     */
    public static function proponi(QuadriDichiarazione $quadri, array $pagine): void
    {
        foreach ($pagine as $modulo => $righi) {
            $quadri->usaModulo($modulo);
            foreach ($righi as $numero => $rigo) {
                $codice = fn(string $colonna) => sprintf('FA%03d%s', $numero, $colonna);
                if ($numero === 1) {
                    $quadri->proponi('FA001001', true);
                    $quadri->proponi('FA001005', $rigo['codiceFiscale']);
                    $quadri->proponi('FA001006', $rigo['mesi']);
                    continue;
                }
                $quadri->proponi($codice('001'), $rigo['relazione']);
                $quadri->proponi($codice('005'), $rigo['codiceFiscale']);
                $quadri->proponi($codice('006'), $rigo['mesi']);
                $percentuale = $rigo['percentuale'];
                $quadri->proponi($codice('A07'), ($percentuale !== null) && ($percentuale !== 'C') ? (float) $percentuale : null);
                $quadri->proponi($codice('B07'), $percentuale === 'C' ? 'C' : null);
                $quadri->proponi($codice('008'), $rigo['affidamento'] ?: null);
                $quadri->proponi($codice('010'), $rigo['mesi21'] ?: null);
            }
        }
        $quadri->usaModulo(1);
    }

    /**
     * @return string[]
     */
    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * Mesi dell'anno in cui il legame è vigente (dal mese di inizio a quello
     * di fine, compresi).
     *
     * @return int[] numeri dei mesi
     */
    public static function mesiVigenza(\DateTimeInterface $dal, ?\DateTimeInterface $al, int $anno): array
    {
        $mesi = [];
        for ($mese = 1; $mese <= 12; $mese++) {
            $inizio = sprintf('%04d-%02d-01', $anno, $mese);
            $fine = date('Y-m-t', strtotime($inizio));
            if (($dal->format('Y-m-d') <= $fine) && (($al === null) || ($al->format('Y-m-d') >= $inizio))) {
                $mesi[] = $mese;
            }
        }
        return $mesi;
    }

    /**
     * Mesi da 21 a 29 anni (dal mese del compimento dei 21 anni a quello
     * prima dei 30; per i figli con disabilità da 21 anni senza limite).
     *
     * @param int[] $mesi mesi a carico
     */
    public static function mesiDetrazioneFigli(\DateTimeInterface $nascita, array $mesi, int $anno, bool $disabile): int
    {
        $nato = (int) $nascita->format('Y') * 12 + (int) $nascita->format('n');
        return count(array_filter($mesi, function (int $mese) use ($nato, $anno, $disabile) {
            $corrente = $anno * 12 + $mese;
            return ($corrente >= $nato + 21 * 12) && ($disabile || ($corrente < $nato + 30 * 12));
        }));
    }

    private function aggiungi(Legame $legame, Soggetto $familiare, int $anno, array &$coniugi, array &$figli, array &$altri): void
    {
        $ruolo = $legame->ruolo;
        if ($ruolo->isFamiliare() === false) {
            return;
        }
        $mesi = self::mesiVigenza($legame->validoDal, $legame->validoAl, $anno);
        if (count($mesi) === 0) {
            return;
        }
        if ($ruolo->richiedeConvivenza() && ($legame->convivente === false)) {
            return;
        }
        $nascita = $familiare->dataNascita;
        $limite = (($ruolo === RuoloLegame::figlio) && ($nascita !== null) && ($anno - (int) $nascita->format('Y') <= 24)) ? self::LIMITE_FIGLI_24 : self::LIMITE;
        $reddito = $this->getReddito($familiare, $anno);
        $nome = $familiare->nominativo;
        if ($reddito === null) {
            $this->avvisi[] = sprintf('Prospetto dei familiari: reddito %d di %s non presente nella suite, proposto a carico; verifica che non superi %s euro.', $anno, $nome, number_format($limite, 2, ',', '.'));
        }
        $aCarico = ($reddito === null) || ($reddito <= $limite);
        $codiceFiscale = $familiare->codiceFiscale;
        if ($ruolo === RuoloLegame::coniuge) {
            $chiave = $familiare->id . '-' . $legame->validoDal->format('Y-m-d');
            $coniugi[$chiave] ??= ['codiceFiscale' => $codiceFiscale, 'mesi' => $aCarico ? count($mesi) : null];
            return;
        }
        if ($aCarico === false) {
            return;
        }
        if ($ruolo === RuoloLegame::figlio) {
            if ($nascita === null) {
                $this->avvisi[] = sprintf('Prospetto dei familiari: data di nascita di %s mancante, mesi da 21 anni (col. 10) da indicare a mano.', $nome);
            }
            $figli[] = [
                'relazione' => $legame->disabile ? 'D' : 'F',
                'codiceFiscale' => $codiceFiscale,
                'mesi' => count($mesi),
                'percentuale' => $legame->percentualeDetrazione,
                'affidamento' => $legame->affidamento,
                'mesi21' => $nascita !== null ? self::mesiDetrazioneFigli($nascita, $mesi, $anno, $legame->disabile) : 0,
                'nascita' => $nascita?->format('Y-m-d') ?? '9999-12-31',
            ];
            return;
        }
        $altri[] = [
            'relazione' => $ruolo === RuoloLegame::ascendente ? 'G' : 'P',
            'codiceFiscale' => $codiceFiscale,
            'mesi' => count($mesi),
            'percentuale' => '100',
            'affidamento' => false,
            'mesi21' => 0,
        ];
    }

    /**
     * Reddito complessivo del familiare (con cedolare secca e reddito
     * forfetario): dalla sua dichiarazione Redditi o, senza, dalle sue
     * Certificazioni Uniche; null se la suite non ha dati.
     */
    private function getReddito(Soggetto $familiare, int $anno): ?float
    {
        $dichiarazione = (new DichiarazioneModel($this->dataMapper))->getPerModello($familiare, ModelloDichiarativo::redditiPf, $anno);
        if ($dichiarazione !== null) {
            $quadri = new QuadriDichiarazione($dichiarazione, $this->dataMapper);
            if ($quadri->get('RN001001', 1) !== null) {
                return (float) $quadri->getImporto('RN001001', 1);
            }
        }
        $certificazioni = (new CertificazioneUnicaModel($this->dataMapper))->getPerAnno($familiare, $anno);
        if (count($certificazioni) === 0) {
            return null;
        }
        $this->avvisi[] = sprintf('Prospetto dei familiari: reddito %d di %s letto dalle Certificazioni Uniche, verifica che non abbia altri redditi.', $anno, $familiare->nominativo);
        $valoreModel = new ValoreCertificazioneUnicaModel($this->dataMapper);
        $reddito = 0.0;
        foreach ($certificazioni as $certificazione) {
            $valori = $valoreModel->getValori($certificazione);
            foreach ([PuntiCertificazioneUnica::INDETERMINATO, PuntiCertificazioneUnica::DETERMINATO, PuntiCertificazioneUnica::PENSIONE, PuntiCertificazioneUnica::ALTRI_ASSIMILATI, PuntiCertificazioneUnica::ASSEGNI_CONIUGE] as $punto) {
                $reddito += $valori[$punto] ?? 0.0;
            }
        }
        return $reddito;
    }
}
