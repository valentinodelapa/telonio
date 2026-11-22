<?php

namespace Contabilita\Application\Services;

use Contabilita\Application\Models\EsercizioContabileModel;
use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Abbinamento;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\DestinazioneConto;
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
 *
 * Enti non commerciali (destinazione dei conti, art. 144 TUIR): concorrono
 * solo i conti dell'attività commerciale (o senza destinazione) e la quota
 * dei costi promiscui pari al rapporto tra i ricavi dei conti commerciali e
 * il totale dei ricavi e proventi dello stesso calcolo; il resto (conti
 * istituzionali e parte istituzionale dei promiscui) è nelle voci CEE
 * dell'attività istituzionale (base retributiva IRAP degli enti).
 */
class ComponentiReddito
{

    private const DOCUMENTI = [CausaleContabile::fatturaVendita, CausaleContabile::fatturaAcquisto];
    private const NOTE = [CausaleContabile::notaCreditoEmessa, CausaleContabile::notaCreditoRicevuta];

    /** @var string[] */
    private array $avvisi = [];

    /** @var array{ricavi: float, costi: float} totali dell'ultimo calcolo per cassa (esclusi i conti con rigo "NO") */
    private array $totaliCassa = ['ricavi' => 0.0, 'costi' => 0.0];

    /** @var array<string, float> */
    private array $perVoce = [];

    /** @var array<string, float> */
    private array $perRigoIsa = [];

    /** @var array<string, float> */
    private array $perRigoIrap = [];

    /** @var array<string, float> voci CEE dei conti istituzionali e parte istituzionale dei promiscui */
    private array $perVoceIstituzionale = [];

    /** Quota commerciale dei costi promiscui dell'ultimo calcolo, null se nessun conto ha una destinazione */
    private ?float $quotaPromiscui = null;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {

    }

    /**
     * @return string[]
     */
    /**
     * Componenti dell'ultimo calcolo (per cassa o per competenza) per voce
     * del conto economico CEE dei conti (es. "E.B.7" costi per servizi),
     * con lo stesso criterio e la sola parte deducibile dei costi (parte
     * imponibile dei ricavi): i ricavi positivi, i costi positivi. Per i
     * righi del modello ISA che distinguono i costi per natura (quadro F).
     *
     * @return array<string, float> voce CEE => importo
     */
    public function getPerVoceCee(): array
    {
        return array_map(fn(float $importo) => round($importo, 2), $this->perVoce);
    }

    /**
     * Componenti dell'ultimo calcolo per rigo del quadro F dei modelli ISA
     * (RighiIsa: dal rigo del conto o dalla voce di bilancio), con lo
     * stesso criterio e la parte deducibile; i campi "di cui" hanno la loro
     * chiave (es. "F14:2") e sono compresi anche nel rigo ("F14").
     *
     * @return array<string, float> rigo => importo
     */
    public function getPerRigoIsa(): array
    {
        return array_map(fn(float $importo) => round($importo, 2), $this->perRigoIsa);
    }

    /**
     * Componenti dell'ultimo calcolo per competenza per variazione del
     * quadro IC del modello IRAP indicata sul conto (RighiIrap, es. "IC46"
     * o "IC51:99"), con la stessa parte dei costi e dei ricavi delle voci:
     * l'importo da indicare nella variazione.
     *
     * @return array<string, float> rigo => importo
     */
    public function getPerRigoIrap(): array
    {
        return array_map(fn(float $importo) => round($importo, 2), $this->perRigoIrap);
    }

    public function getAvvisi(): array
    {
        return $this->avvisi;
    }

    /**
     * Enti non commerciali: componenti dell'ultimo calcolo dell'attività
     * istituzionale (conti istituzionali e parte non commerciale dei costi
     * promiscui) per voce del conto economico CEE, con la parte deducibile.
     *
     * @return array<string, float> voce CEE => importo
     */
    public function getPerVoceCeeIstituzionale(): array
    {
        return array_map(fn(float $importo) => round($importo, 2), $this->perVoceIstituzionale);
    }

    /**
     * Quota commerciale dei costi promiscui dell'ultimo calcolo (da 0 a 1),
     * null se nessun conto movimentato ha una destinazione (imprese).
     */
    public function getQuotaPromiscui(): ?float
    {
        return $this->quotaPromiscui === null ? null : round($this->quotaPromiscui, 6);
    }

    /**
     * Rapporto dell'art. 144, c. 4, TUIR tra i ricavi e proventi
     * dell'attività commerciale e l'ammontare complessivo dei ricavi e
     * proventi (da 0 a 1; 0 senza ricavi).
     */
    public static function calcolaQuotaPromiscui(float $ricaviCommerciali, float $ricaviTotali): float
    {
        return $ricaviTotali <= 0 ? 0.0 : max(0.0, min(1.0, $ricaviCommerciali / $ricaviTotali));
    }

    /**
     * Parte del conto che concorre all'attività commerciale: 1 per i conti
     * commerciali o senza destinazione, 0 per gli istituzionali, la quota
     * dei promiscui.
     */
    private function getFattore(Conto $conto): float
    {
        return match ($conto->destinazione) {
            null, DestinazioneConto::commerciale => 1.0,
            DestinazioneConto::istituzionale => 0.0,
            DestinazioneConto::promiscuo => $this->quotaPromiscui ?? 1.0,
        };
    }

    /**
     * Quota dei promiscui dalle righe del calcolo (riga, frazione
     * dell'anno), solo se qualche conto economico ha una destinazione.
     *
     * @param array<array{0: RigaContabile, 1: float}> $righe
     */
    private function impostaQuotaPromiscui(array $righe): void
    {
        $this->quotaPromiscui = null;
        $this->perVoceIstituzionale = [];
        $commerciali = $totali = 0.0;
        $destinazioni = false;
        foreach ($righe as [$riga, $frazione]) {
            $destinazioni = $destinazioni || ($riga->conto->natura->isEconomico() && ($riga->conto->destinazione !== null));
            if ($riga->conto->natura === NaturaConto::ricavo) {
                $importo = ($riga->avere - $riga->dare) * $frazione;
                $totali += $importo;
                if (in_array($riga->conto->destinazione, [null, DestinazioneConto::commerciale], true)) {
                    $commerciali += $importo;
                }
            }
        }
        if ($destinazioni) {
            $this->quotaPromiscui = self::calcolaQuotaPromiscui($commerciali, $totali);
        }
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
        $this->perVoce = [];
        $this->perRigoIsa = [];
        $this->perRigoIrap = [];
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
        $daAggiungere = [];
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
            $daAggiungere[] = [$riga, $frazione];
        }
        $this->impostaQuotaPromiscui($daAggiungere);
        foreach ($daAggiungere as [$riga, $frazione]) {
            $this->aggiungi($componenti, $riga, $frazione, $professionista);
        }
        // parte indeducibile degli ammortamenti dei cespiti (limiti fiscali, coefficiente)
        $esercizio = (new EsercizioContabileModel($this->dataMapper))->getPerAnno($soggetto, $anno);
        if ($esercizio !== null) {
            foreach ((new Ammortamenti($this->dataMapper))->getVariazioni($soggetto, $esercizio)['conti'] as ['conto' => $conto, 'importo' => $importo]) {
                $rigo = $conto->rigoRedditi ?? RighiRedditi::getPredefinito($conto->voceCee, $professionista);
                $importo *= $this->getFattore($conto);
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
        $this->perVoce = [];
        $this->perRigoIsa = [];
        $this->perRigoIrap = [];
        $dal = new SismaDate($anno . '-01-01');
        $al = new SismaDate($anno . '-12-31');
        $righe = array_values(array_filter(iterator_to_array((new RigaContabileModel($this->dataMapper))->getMovimenti($soggetto, null, $dal, $al), false),
                        fn(RigaContabile $riga) => $riga->registrazioneContabile->origine !== ChiusuraEsercizio::ORIGINE));
        $this->impostaQuotaPromiscui(array_map(fn(RigaContabile $riga) => [$riga, 1.0], $righe));
        foreach ($righe as $riga) {
            $fattore = $this->getFattore($riga->conto);
            if (in_array($riga->conto->natura, [NaturaConto::ricavo, NaturaConto::costo], true) && ($riga->conto->voceCee !== null)) {
                $importo = ($riga->conto->natura === NaturaConto::ricavo ? $riga->avere - $riga->dare : $riga->dare - $riga->avere) * ($riga->conto->percentualeDeducibile ?? 100.0) / 100;
                $voce = $riga->conto->voceCee->value;
                if ($fattore > 0) {
                    $this->perVoce[$voce] = ($this->perVoce[$voce] ?? 0.0) + $importo * $fattore;
                }
                if ($fattore < 1) {
                    $this->perVoceIstituzionale[$voce] = ($this->perVoceIstituzionale[$voce] ?? 0.0) + $importo * (1 - $fattore);
                }
            }
            // conti dell'attività istituzionale: fuori dal reddito d'impresa
            if ($fattore <= 0) {
                continue;
            }
            if (in_array($riga->conto->natura, [NaturaConto::ricavo, NaturaConto::costo], true)) {
                $importo = ($riga->conto->natura === NaturaConto::ricavo ? $riga->avere - $riga->dare : $riga->dare - $riga->avere) * $fattore;
                foreach (RighiIsa::getChiavi($riga->conto) as $chiave) {
                    $this->perRigoIsa[$chiave] = ($this->perRigoIsa[$chiave] ?? 0.0) + $importo * ($riga->conto->percentualeDeducibile ?? 100.0) / 100;
                }
                $rigoIrap = RighiIrap::getRigo($riga->conto);
                if ($rigoIrap !== null) {
                    $this->perRigoIrap[$rigoIrap] = ($this->perRigoIrap[$rigoIrap] ?? 0.0) + $importo * ($riga->conto->percentualeDeducibile ?? 100.0) / 100;
                }
            }
            if ($riga->conto->natura === NaturaConto::ricavo) {
                $ricavi += ($riga->avere - $riga->dare) * $fattore;
                if ($riga->conto->percentualeDeducibile !== null) {
                    // parte non imponibile dei ricavi: variazione in diminuzione
                    $rigo = RighiRedditi::VARIAZIONE_DIMINUZIONE;
                    $variazioni[$rigo] = ($variazioni[$rigo] ?? 0.0) + ($riga->avere - $riga->dare) * $fattore * (100 - $riga->conto->percentualeDeducibile) / 100;
                }
            } elseif ($riga->conto->natura === NaturaConto::costo) {
                $costi += ($riga->dare - $riga->avere) * $fattore;
                if ($riga->conto->percentualeDeducibile !== null) {
                    $rigo = $riga->conto->variazioneRedditi ?? 'RF31:99';
                    $variazioni[$rigo] = ($variazioni[$rigo] ?? 0.0) + ($riga->dare - $riga->avere) * $fattore * (100 - $riga->conto->percentualeDeducibile) / 100;
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
        // enti non commerciali: solo la parte dell'attività commerciale
        $fattore = $this->getFattore($conto);
        if (($fattore < 1) && ($conto->voceCee !== null) && ($rigo !== RighiRedditi::ESCLUSO)) {
            $this->perVoceIstituzionale[$conto->voceCee->value] = ($this->perVoceIstituzionale[$conto->voceCee->value] ?? 0.0) + $importo * (1 - $fattore);
        }
        if ($fattore <= 0) {
            return;
        }
        $importo *= $fattore;
        $componenti[$rigo] = ($componenti[$rigo] ?? 0.0) + $importo;
        if (($conto->voceCee !== null) && ($rigo !== RighiRedditi::ESCLUSO)) {
            $this->perVoce[$conto->voceCee->value] = ($this->perVoce[$conto->voceCee->value] ?? 0.0) + $importo;
        }
        if ($rigo !== RighiRedditi::ESCLUSO) {
            foreach (RighiIsa::getChiavi($conto) as $chiave) {
                $this->perRigoIsa[$chiave] = ($this->perRigoIsa[$chiave] ?? 0.0) + $importo;
            }
        }
        if ($rigo !== RighiRedditi::ESCLUSO) {
            $this->totaliCassa[$conto->natura === NaturaConto::ricavo ? 'ricavi' : 'costi'] += $importo;
        }
    }
}
