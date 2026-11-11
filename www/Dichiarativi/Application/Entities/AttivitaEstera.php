<?php

namespace Dichiarativi\Application\Entities;

use Anagrafica\Application\Entities\Soggetto;
use Dichiarativi\Application\Enumerations\CodiceInvestimentoEstero;
use SismaFramework\Orm\BaseClasses\BaseEntity;
use SismaFramework\Orm\CustomTypes\SismaDate;
use Telonio\Application\Entities\Studio;

/**
 * Investimento o attività finanziaria all'estero del contribuente
 * nell'anno d'imposta, per il quadro RW (monitoraggio, IVIE, IVAFE e
 * imposta sulle cripto-attività) e per i redditi di fonte estera:
 * proventi incassati (interessi, dividendi, altri) con le imposte pagate
 * all'estero, per i quadri RM e CE.
 *
 * Titolo di possesso (col. 1): 1 proprietà, 2 usufrutto, 3 nuda
 * proprietà, 4 altro. Tipo di contribuente (col. 2): 1 delegato, 2
 * titolare effettivo. Criterio del valore (col. 6): 1 mercato, 2
 * nominale, 3 rimborso, 4 costo d'acquisto, 5 catastale, 6 dichiarazione
 * di successione o altri atti. Codice dei quadri reddituali (col. 14): 1
 * RL, 2 RM, 3 RT, 4 più quadri, 5 redditi percepiti in anni successivi o
 * infruttiferi. Le date di inizio e fine detenzione nell'anno (vuote =
 * tutto l'anno) danno i giorni (IVAFE e cripto) e i mesi (IVIE).
 * Con l'opzione per la tassazione ordinaria (rigo RM31, col. 7) i
 * proventi concorrono al reddito complessivo e l'imposta estera dà il
 * credito del quadro CE.
 */
class AttivitaEstera extends BaseEntity
{

    public const PROVENTI = ['I' => 'Interessi', 'D' => 'Dividendi e utili', 'A' => 'Altri redditi di capitale'];

    protected int $id;
    protected Studio $studio;
    protected Soggetto $soggetto;
    protected int $annoImposta;
    protected CodiceInvestimentoEstero $codiceInvestimento = CodiceInvestimentoEstero::contiCorrenti;
    protected ?string $codiceStatoEstero = null;
    protected string $descrizione = '';
    protected ?string $intermediario = null;
    protected int $titoloPossesso = 1;
    protected ?int $tipoContribuente = null;
    protected float $quota = 100.0;
    protected int $criterioValore = 1;
    protected float $valoreIniziale = 0.0;
    protected float $valoreFinale = 0.0;
    protected ?float $valoreMassimo = null;
    protected ?SismaDate $dataInizio = null;
    protected ?SismaDate $dataFine = null;
    protected ?float $creditoImposta = null;
    protected ?int $mesiAbitazionePrincipale = null;
    protected ?int $codiceQuadri = null;
    protected ?float $quotaPartecipazione = null;
    protected ?string $codiceFiscaleEntita = null;
    protected ?string $cointestatario1 = null;
    protected ?string $cointestatario2 = null;
    protected bool $piuCointestatari = false;
    protected bool $soloMonitoraggio = false;
    protected ?string $tipoProventi = null;
    protected ?float $proventi = null;
    protected ?float $impostaEstera = null;
    protected bool $tassazioneOrdinaria = false;
    protected ?string $note = null;

    #[\Override]
    protected function setEncryptedProperties(): void
    {

    }

    #[\Override]
    protected function setPropertyDefaultValue(): void
    {

    }

    /**
     * Giorni di detenzione nell'anno (colonna 10).
     */
    public function getGiorni(): int
    {
        [$inizio, $fine] = $this->getPeriodo();
        // al massimo 365 (valori ammessi della colonna, anche negli anni bisestili)
        return $fine < $inizio ? 0 : min(365, (int) $inizio->diff($fine)->days + 1);
    }

    /**
     * Mesi di possesso nell'anno (colonna 11): conta il mese in cui il
     * possesso è durato almeno 15 giorni.
     */
    public function getMesi(): int
    {
        [$inizio, $fine] = $this->getPeriodo();
        $mesi = 0;
        for ($mese = 1; $mese <= 12; $mese++) {
            $primo = new \DateTimeImmutable(sprintf('%d-%02d-01', $this->annoImposta, $mese));
            $ultimo = $primo->modify('last day of this month');
            $da = max($primo, $inizio);
            $a = min($ultimo, $fine);
            if (($a >= $da) && ((int) $da->diff($a)->days + 1 >= 15)) {
                $mesi++;
            }
        }
        return $mesi;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function getPeriodo(): array
    {
        $inizioAnno = new \DateTimeImmutable($this->annoImposta . '-01-01');
        $fineAnno = new \DateTimeImmutable($this->annoImposta . '-12-31');
        $inizio = $this->dataInizio === null ? $inizioAnno : max($inizioAnno, new \DateTimeImmutable($this->dataInizio->format('Y-m-d')));
        $fine = $this->dataFine === null ? $fineAnno : min($fineAnno, new \DateTimeImmutable($this->dataFine->format('Y-m-d')));
        return [$inizio, $fine];
    }
}
