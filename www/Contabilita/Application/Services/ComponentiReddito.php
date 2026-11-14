<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Models\EsercizioContabileModel;
use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Abbinamento;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\TipoChiusura;
use Contabilita\Application\Enumerations\TipoPartita;
use Contabilita\Application\Models\AbbinamentoModel;
use Contabilita\Application\Models\PartitaModel;
use Contabilita\Application\Models\RigaContabileModel;
use SismaFramework\Orm\CustomTypes\SismaDate;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Componenti del reddito d'impresa e di lavoro autonomo per i quadri del
 * modello Redditi, dalle registrazioni in partita doppia.
 *
 * Per cassa (quadri RG e RE, art. 66 e 54 Tuir): le fatture emesse e
 * ricevute concorrono in proporzione a quanto incassato o pagato
 * nell'anno secondo gli abbinamenti delle partite (Services\PartiteAperte):
 * incassi e pagamenti, ritenute d'acconto (parte del compenso),
 * compensazioni, note di credito (nota e fattura stornata si compensano)
 * e acconti (alla data dell'acconto); gli insoluti riducono l'incassato
 * dell'anno in cui si registrano e i loro incassi lo ripristinano. Gli
 * abbuoni e le perdite su crediti non valgono come incasso, e i conti
 * economici delle loro registrazioni non concorrono (il ricavo non
 * incassato non è tassato). Le altre registrazioni (corrispettivi, note
 * di credito, spese pagate direttamente, ammortamenti e altre scritture
 * di rettifica) concorrono alla data di registrazione.
 *
 * Per le aziende senza partite (registrazioni anteriori alle partite
 * aperte, prima di "sisma partite-aperte") gli incassi chiudono le
 * fatture della controparte dalla più vecchia (FIFO), con un avviso.
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

    /** @var array{ricavi: float, costi: float} totali dell'ultimo calcolo per cassa (esclusi i conti con rigo "NO") */
    private array $totaliCassa = ['ricavi' => 0.0, 'costi' => 0.0];

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
     * Quota incassata o pagata nell'anno di ciascuna fattura secondo gli
     * abbinamenti delle partite (id della registrazione => frazione del
     * totale, negativa se gli insoluti superano gli incassi dell'anno).
     *
     * @param Partita[] $partite tutte le partite del soggetto
     * @param Abbinamento[] $abbinamenti tutti gli abbinamenti del soggetto
     * @return array<int, float>
     */
    public static function getQuoteAbbinate(iterable $partite, iterable $abbinamenti, int $anno): array
    {
        $perPartita = [];
        foreach ($abbinamenti as $abbinamento) {
            $perPartita[$abbinamento->partita->id][] = $abbinamento;
        }
        $incassato = $totali = [];
        foreach ($partite as $partita) {
            $documento = $partita->tipo === TipoPartita::insoluto ? $partita->partitaOrigine : $partita;
            if (($documento === null) || (in_array($partita->tipo, [TipoPartita::documento, TipoPartita::insoluto], true) === false)) {
                continue;
            }
            $id = $documento->registrazioneContabile->id;
            if ($partita->tipo === TipoPartita::documento) {
                $totali[$id] = ($totali[$id] ?? 0.0) + $partita->importo;
            } elseif ((int) $partita->data->format('Y') === $anno) {
                $incassato[$id] = ($incassato[$id] ?? 0.0) - $partita->importo;
            }
            foreach ($perPartita[$partita->id] ?? [] as $abbinamento) {
                // l'acconto conta alla data in cui è stato incassato o pagato
                $data = (($abbinamento->tipo === TipoChiusura::acconto) && ($abbinamento->partitaCollegata !== null)) ? $abbinamento->partitaCollegata->data : $abbinamento->data;
                if ($abbinamento->tipo->isIncasso() && ((int) $data->format('Y') === $anno)) {
                    $incassato[$id] = ($incassato[$id] ?? 0.0) + $abbinamento->importo;
                }
            }
        }
        $quote = [];
        foreach ($incassato as $id => $importo) {
            if (($totali[$id] ?? 0) > 0) {
                $quote[$id] = max(-1.0, min(1.0, $importo / $totali[$id]));
            }
        }
        return $quote;
    }

    /**
     * Quota incassata o pagata nell'anno di ciascuna fattura per le
     * registrazioni senza partite: incassi e pagamenti chiudono le fatture
     * della controparte dalla più vecchia (id della registrazione =>
     * frazione del totale).
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
        $this->totaliCassa = ['ricavi' => 0.0, 'costi' => 0.0];
        // senza epilogo, chiusura e riapertura dei conti
        $righe = array_values(array_filter(iterator_to_array((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, null, null), false),
                        fn(RigaContabile $riga) => ChiusuraEsercizio::isChiusuraORiapertura($riga->registrazioneContabile) === false));
        $documentiConPartita = [];
        foreach ($righe as $riga) {
            if (($riga->controparte !== null) && $riga->conto->natura->isPatrimoniale()) {
                $documentiConPartita[$riga->registrazioneContabile->id] = true;
            }
        }
        $partite = iterator_to_array((new PartitaModel($this->dataMapper))->getPerSoggetto($soggetto), false);
        $conAbbuoni = [];
        if ((count($partite) === 0) && (count($documentiConPartita) > 0)) {
            $this->avvisi['partite'] = 'Le registrazioni non hanno ancora le partite aperte: incassi e pagamenti chiudono le fatture dalla più vecchia. Eseguire "sisma partite-aperte" e verificare gli abbinamenti nello scadenzario.';
            $quote = $this->getQuoteIncassate($righe, $anno);
        } else {
            $abbinamenti = iterator_to_array((new AbbinamentoModel($this->dataMapper))->getPerSoggetto($soggetto), false);
            $quote = self::getQuoteAbbinate($partite, $abbinamenti, $anno);
            foreach ($abbinamenti as $abbinamento) {
                if ($abbinamento->tipo === TipoChiusura::abbuono) {
                    $conAbbuoni[$abbinamento->registrazioneContabile->id] = true;
                }
            }
        }
        $componenti = [];
        foreach ($righe as $riga) {
            if (($riga->conto->natura->isEconomico() === false) || isset($conAbbuoni[$riga->registrazioneContabile->id])) {
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
        // parte indeducibile degli ammortamenti dei cespiti (limiti fiscali, coefficiente)
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        if ($esercizio !== null) {
            foreach ((new Ammortamenti($this->dataMapper))->getVariazioni($soggetto, $esercizio)['conti'] as ['conto' => $conto, 'importo' => $importo]) {
                $rigo = $conto->rigoRedditi ?? RighiRedditi::getPredefinito($conto->voceCee, $professionista);
                if (($rigo !== null) && isset($componenti[$rigo])) {
                    $componenti[$rigo] -= $importo;
                    if ($rigo !== RighiRedditi::ESCLUSO) {
                        $this->totaliCassa['costi'] -= $importo;
                    }
                }
            }
        }
        return array_map(fn(float $importo) => round($importo, 2), $componenti);
    }

    /**
     * Ricavi e costi dell'ultimo calcolo per cassa (es. reddito della
     * società di persone da imputare ai soci, quadro RH).
     *
     * @return array{ricavi: float, costi: float}
     */
    public function getTotaliCassa(): array
    {
        return array_map(fn(float $importo) => round($importo, 2), $this->totaliCassa);
    }

    /**
     * Componenti per competenza dell'anno (utile del quadro RF) e parte
     * indeducibile dei costi dei conti con la percentuale deducibile, per
     * rigo della variazione in aumento (RF16, RF18, RF31:99), e parte non
     * imponibile dei ricavi con la percentuale (variazione in diminuzione
     * RF55:99).
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
            if ($riga->registrazioneContabile->origine === ChiusuraEsercizio::ORIGINE) {
                continue;
            }
            if ($riga->conto->natura === NaturaConto::ricavo) {
                $ricavi += $riga->avere - $riga->dare;
                if ($riga->conto->percentualeDeducibile !== null) {
                    // parte non imponibile dei ricavi: variazione in diminuzione
                    $rigo = RighiRedditi::VARIAZIONE_DIMINUZIONE;
                    $variazioni[$rigo] = ($variazioni[$rigo] ?? 0.0) + ($riga->avere - $riga->dare) * (100 - $riga->conto->percentualeDeducibile) / 100;
                }
            } elseif ($riga->conto->natura === NaturaConto::costo) {
                $costi += $riga->dare - $riga->avere;
                if ($riga->conto->percentualeDeducibile !== null) {
                    $rigo = $riga->conto->variazioneRedditi ?? 'RF31:99';
                    $variazioni[$rigo] = ($variazioni[$rigo] ?? 0.0) + ($riga->dare - $riga->avere) * (100 - $riga->conto->percentualeDeducibile) / 100;
                }
            }
        }
        // variazioni fiscali degli ammortamenti dei cespiti (RF18, RF31 codice 99, RF55 codice 99)
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        if ($esercizio !== null) {
            foreach ((new Ammortamenti($this->dataMapper))->getVariazioni($soggetto, $esercizio)['righi'] as $rigo => $importo) {
                $variazioni[$rigo] = ($variazioni[$rigo] ?? 0.0) + $importo;
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
        if ($conto->percentualeDeducibile !== null) {
            // nei quadri RG ed RE solo la parte deducibile dei costi e la parte imponibile dei ricavi
            $importo *= $conto->percentualeDeducibile / 100;
        }
        $componenti[$rigo] = ($componenti[$rigo] ?? 0.0) + $importo;
        if ($rigo !== RighiRedditi::ESCLUSO) {
            $this->totaliCassa[$conto->natura === NaturaConto::ricavo ? 'ricavi' : 'costi'] += $importo;
        }
    }
}
