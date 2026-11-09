<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Models\RigaContabileModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Componenti del reddito d'impresa e di lavoro autonomo per i quadri del
 * modello Redditi, dalle registrazioni in partita doppia.
 *
 * Per cassa (quadri RG e RE, art. 66 e 54 Tuir): le fatture emesse e
 * ricevute concorrono in proporzione a quanto incassato o pagato
 * nell'anno; gli incassi e i pagamenti di ogni controparte (sul suo
 * sottoconto del partitario) chiudono le fatture dalla più vecchia
 * (FIFO), e le note di credito le chiudono senza movimento di denaro (nota
 * e fattura stornata si compensano). Le altre registrazioni (corrispettivi,
 * note di credito, spese pagate direttamente, ammortamenti e altre
 * scritture di rettifica) concorrono alla data di registrazione.
 *
 * Per competenza (quadro RF): saldi dei conti economici dell'anno.
 *
 * Ogni conto economico confluisce nel rigo indicato sul conto o, in
 * mancanza, in quello proposto dalla voce di conto economico
 * (RighiRedditi); i ricavi sono positivi, i costi positivi.
 */
class ComponentiReddito
{

    private const DOCUMENTI = [CausaleContabile::fatturaVendita, CausaleContabile::fatturaAcquisto];
    private const NOTE = [CausaleContabile::notaCreditoEmessa, CausaleContabile::notaCreditoRicevuta];

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
     * Quota incassata o pagata nell'anno di ciascuna fattura (id della
     * registrazione => frazione del totale).
     *
     * @param RigaContabile[] $righe tutte le righe del soggetto
     * @return array<int, float>
     */
    public function getQuoteIncassate(array $righe, int $anno): array
    {
        $partite = [];
        foreach ($righe as $riga) {
            if (($riga->controparte === null) || ($riga->conto->natura->isPatrimoniale() === false)) {
                continue;
            }
            $partite[$riga->controparte->id . '-' . $riga->conto->id][] = $riga;
        }
        $incassato = [];
        $totali = [];
        foreach ($partite as $movimenti) {
            usort($movimenti, fn(RigaContabile $prima, RigaContabile $seconda) => [$prima->dataRegistrazione->format('Y-m-d'), $prima->id] <=> [$seconda->dataRegistrazione->format('Y-m-d'), $seconda->id]);
            // segno della partita: dare per i crediti verso clienti, avere per i debiti verso fornitori
            $segno = $movimenti[0]->conto->natura === NaturaConto::attivita ? 1.0 : -1.0;
            $aperte = [];
            foreach ($movimenti as $riga) {
                $importo = ($riga->dare - $riga->avere) * $segno;
                $registrazione = $riga->registrazioneContabile;
                if (in_array($registrazione->causale, self::DOCUMENTI, true) && ($importo > 0)) {
                    $aperte[] = ['id' => $registrazione->id, 'residuo' => $importo];
                    $totali[$registrazione->id] = ($totali[$registrazione->id] ?? 0.0) + $importo;
                    continue;
                }
                $chiusura = -$importo;
                foreach ($aperte as &$partita) {
                    if ($chiusura <= 0.005) {
                        break;
                    }
                    $quota = min($partita['residuo'], $chiusura);
                    $partita['residuo'] -= $quota;
                    $chiusura -= $quota;
                    if ((int) $riga->dataRegistrazione->format('Y') === $anno) {
                        $incassato[$partita['id']] = ($incassato[$partita['id']] ?? 0.0) + $quota;
                    }
                }
                unset($partita);
                $aperte = array_values(array_filter($aperte, fn(array $partita) => $partita['residuo'] > 0.005));
            }
        }
        $quote = [];
        foreach ($incassato as $id => $importo) {
            $quote[$id] = $totali[$id] > 0 ? min(1.0, $importo / $totali[$id]) : 0.0;
        }
        return $quote;
    }

    /**
     * Componenti per cassa dell'anno per rigo del quadro RG o RE.
     *
     * @return array<string, float> rigo => importo
     */
    public function perCassa(Soggetto $soggetto, int $anno, bool $professionista): array
    {
        $this->avvisi = [];
        $righe = iterator_to_array((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, null, null), false);
        $quote = $this->getQuoteIncassate($righe, $anno);
        $documentiConPartita = [];
        foreach ($righe as $riga) {
            if (($riga->controparte !== null) && $riga->conto->natura->isPatrimoniale()) {
                $documentiConPartita[$riga->registrazioneContabile->id] = true;
            }
        }
        $componenti = [];
        foreach ($righe as $riga) {
            if ($riga->conto->natura->isEconomico() === false) {
                continue;
            }
            $registrazione = $riga->registrazioneContabile;
            $documento = in_array($registrazione->causale, self::DOCUMENTI, true) && isset($documentiConPartita[$registrazione->id]);
            $frazione = $documento ? ($quote[$registrazione->id] ?? 0.0) : ((int) $riga->dataRegistrazione->format('Y') === $anno ? 1.0 : 0.0);
            if ($frazione <= 0) {
                continue;
            }
            $this->aggiungi($componenti, $riga, $frazione, $professionista);
        }
        return array_map(fn(float $importo) => round($importo, 2), $componenti);
    }

    /**
     * Componenti per competenza dell'anno (utile del quadro RF) e parte
     * indeducibile dei costi dei conti con la percentuale deducibile, per
     * rigo della variazione in aumento (RF16, RF18, RF31:99).
     *
     * @return array{ricavi: float, costi: float, utile: float, variazioni: array<string, float>}
     */
    public function perCompetenza(Soggetto $soggetto, int $anno): array
    {
        $ricavi = $costi = 0.0;
        $variazioni = [];
        $dal = new SismaDate($anno . '-01-01');
        $al = new SismaDate($anno . '-12-31');
        foreach ((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, $dal, $al) as $riga) {
            if ($riga->conto->natura === NaturaConto::ricavo) {
                $ricavi += $riga->avere - $riga->dare;
            } elseif ($riga->conto->natura === NaturaConto::costo) {
                $costi += $riga->dare - $riga->avere;
                if ($riga->conto->percentualeDeducibile !== null) {
                    $rigo = $riga->conto->variazioneRedditi ?? 'RF31:99';
                    $variazioni[$rigo] = ($variazioni[$rigo] ?? 0.0) + ($riga->dare - $riga->avere) * (100 - $riga->conto->percentualeDeducibile) / 100;
                }
            }
        }
        return ['ricavi' => round($ricavi, 2), 'costi' => round($costi, 2), 'utile' => round($ricavi - $costi, 2), 'variazioni' => array_map(fn(float $importo) => round($importo, 2), $variazioni)];
    }

    private function aggiungi(array &$componenti, RigaContabile $riga, float $frazione, bool $professionista): void
    {
        $conto = $riga->conto;
        $rigo = $conto->rigoRedditi ?? RighiRedditi::getPredefinito($conto->voceCee, $professionista);
        if ($rigo === null) {
            $this->avvisi[$conto->id] = 'Conto ' . $conto->codice . ' ' . $conto->descrizione . ': nessun rigo della dichiarazione (voce di bilancio assente o senza rigo), indica il rigo sul conto.';
            return;
        }
        $importo = ($conto->natura === NaturaConto::ricavo ? $riga->avere - $riga->dare : $riga->dare - $riga->avere) * $frazione;
        if (($conto->natura === NaturaConto::costo) && ($conto->percentualeDeducibile !== null)) {
            // nei quadri RG ed RE solo la parte deducibile
            $importo *= $conto->percentualeDeducibile / 100;
        }
        $componenti[$rigo] = ($componenti[$rigo] ?? 0.0) + $importo;
    }
}
