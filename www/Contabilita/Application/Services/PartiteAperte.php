<?php

namespace Contabilita\Application\Services;

use Anagrafica\Application\Entities\Soggetto;
use Contabilita\Application\Entities\Abbinamento;
use Contabilita\Application\Entities\Conto;
use Contabilita\Application\Entities\Controparte;
use Contabilita\Application\Entities\Partita;
use Contabilita\Application\Entities\RegistrazioneContabile;
use Contabilita\Application\Entities\RigaContabile;
use Contabilita\Application\Enumerations\CausaleContabile;
use Contabilita\Application\Enumerations\FunzioneConto;
use Contabilita\Application\Enumerations\NaturaConto;
use Contabilita\Application\Enumerations\TipoChiusura;
use Contabilita\Application\Enumerations\TipoPartita;
use Contabilita\Application\Models\AbbinamentoModel;
use Contabilita\Application\Models\PartitaModel;
use Contabilita\Application\Models\RigaContabileModel;
use SismaFramework\Orm\HelperClasses\DataMapper;

/**
 * Partite aperte del partitario clienti e fornitori.
 *
 * Le righe della registrazione sui sottoconti delle controparti formano
 * un gruppo per controparte e sottoconto, con l'importo nel segno
 * naturale del partitario (dare per i clienti, avere per i fornitori):
 *
 * - in apertura (fatture, addebiti, insoluti): nasce una partita per
 *   l'intero importo, che si può abbinare agli acconti e alle note di
 *   credito ancora aperte della controparte; con la scissione dei
 *   pagamenti la partita è al netto dell'IVA stornata dal cliente;
 * - in chiusura (incassi, pagamenti, note di credito, compensazioni):
 *   l'importo si abbina alle partite aperte. La proposta le chiude dalla
 *   più vecchia (FIFO) e ripartisce l'importo tra i modi di chiusura
 *   ricavati dalle altre righe (ritenuta d'acconto, abbuono o perdita sui
 *   conti economici, compensazione con l'altro ruolo della controparte,
 *   denaro per il resto). L'eccedenza non abbinata è una nuova partita:
 *   acconto, se indicato, o nota di credito da abbinare.
 *
 * Gli abbinamenti sono obbligatori: la registrazione si salva solo con un
 * abbinamento per ogni gruppo, confermato dall'utente o proposto
 * (registrazioni dirette delle fixture e del comando di migrazione).
 *
 * Struttura delle scelte per gruppo: ['abbinamenti' => [id partita =>
 * [tipo di chiusura => importo]], 'acconto' => bool, 'riapre' => ?id
 * della partita riaperta dall'insoluto].
 */
class PartiteAperte
{

    public const TOLLERANZA = 0.005;

    private PartitaModel $partitaModel;
    private AbbinamentoModel $abbinamentoModel;

    public function __construct(private DataMapper $dataMapper = new DataMapper())
    {
        $this->partitaModel = new PartitaModel($this->dataMapper);
        $this->abbinamentoModel = new AbbinamentoModel($this->dataMapper);
    }

    public static function isPartitario(RigaContabile $riga): bool
    {
        return ($riga->controparte !== null) && $riga->conto->natura->isPatrimoniale();
    }

    /**
     * Gruppi del partitario della registrazione.
     *
     * @param RigaContabile[] $righe
     * @return array<string, array{chiave: string, controparte: Controparte, conto: Conto, cliente: bool, importo: float, apertura: bool, composizione: array<string, float>}>
     */
    public function getGruppi(RegistrazioneContabile $registrazione, array $righe): array
    {
        $gruppi = [];
        $ritenute = $abbuoni = 0.0;
        foreach ($righe as $riga) {
            if (self::isPartitario($riga)) {
                $cliente = $riga->conto->natura === NaturaConto::attivita;
                $chiave = $riga->controparte->id . '-' . $riga->conto->id;
                $gruppi[$chiave] ??= ['chiave' => $chiave, 'controparte' => $riga->controparte, 'conto' => $riga->conto, 'cliente' => $cliente, 'importo' => 0.0];
                $gruppi[$chiave]['importo'] += ($riga->dare - $riga->avere) * ($cliente ? 1 : -1);
            } elseif (in_array($riga->conto->funzione, [FunzioneConto::ritenuteSubite, FunzioneConto::ritenuteDaVersare], true)) {
                $ritenute += abs($riga->dare - $riga->avere);
            } elseif ($riga->conto->natura->isEconomico()) {
                $abbuoni += abs($riga->dare - $riga->avere);
            }
        }
        $gruppi = array_filter($gruppi, fn(array $gruppo) => abs($gruppo['importo']) > self::TOLLERANZA);
        $chiusure = 0.0;
        foreach ($gruppi as $gruppo) {
            $chiusure += $gruppo['importo'] < 0 ? -$gruppo['importo'] : 0.0;
        }
        foreach ($gruppi as &$gruppo) {
            $gruppo['importo'] = round($gruppo['importo'], 2);
            $gruppo['apertura'] = $gruppo['importo'] > 0;
            $gruppo['composizione'] = $gruppo['apertura'] ? [] : self::composizione($registrazione->causale, -$gruppo['importo'], $ritenute, $abbuoni, $chiusure + $gruppo['importo']);
        }
        unset($gruppo);
        return $gruppi;
    }

    /**
     * Modi di chiusura dell'importo: nota di credito per le note, altrimenti
     * ritenuta, abbuono, compensazione con le altre chiusure della stessa
     * registrazione e denaro per il resto.
     *
     * @return array<string, float> tipo => importo
     */
    public static function composizione(CausaleContabile $causale, float $totale, float $ritenute, float $abbuoni, float $altreChiusure): array
    {
        if (in_array($causale, [CausaleContabile::notaCreditoEmessa, CausaleContabile::notaCreditoRicevuta], true)) {
            return [TipoChiusura::notaCredito->value => round($totale, 2)];
        }
        $ritenuta = min($totale, $ritenute);
        $abbuono = min($totale - $ritenuta, $abbuoni);
        $compensazione = min($totale - $ritenuta - $abbuono, $altreChiusure);
        $denaro = $totale - $ritenuta - $abbuono - $compensazione;
        return array_filter([TipoChiusura::denaro->value => round($denaro, 2), TipoChiusura::ritenuta->value => round($ritenuta, 2),
            TipoChiusura::abbuono->value => round($abbuono, 2), TipoChiusura::compensazione->value => round($compensazione, 2)], fn(float $importo) => $importo > self::TOLLERANZA);
    }

    /**
     * Ripartizione dalla più vecchia (FIFO) dell'importo tra le partite e,
     * per ciascuna, tra i modi di chiusura in proporzione (es. un incasso
     * di due parcelle con le ritenute: per ogni parcella l'80 per cento in
     * denaro e il 20 di ritenuta).
     *
     * @param array<int, float> $residui id della partita => residuo, in ordine
     * @param array<string, float> $composizione tipo => importo
     * @return array<int, array<string, float>> id => tipo => importo
     */
    public static function ripartisci(array $residui, array $composizione): array
    {
        $totaleComposizione = round(array_sum($composizione), 2);
        $totale = min($totaleComposizione, round(array_sum($residui), 2));
        if (($totale <= self::TOLLERANZA) || ($totaleComposizione <= 0)) {
            return [];
        }
        $quote = [];
        $resto = $totale;
        foreach ($residui as $id => $residuo) {
            if ($resto <= self::TOLLERANZA) {
                break;
            }
            $quota = round(min($residuo, $resto), 2);
            if ($quota > 0) {
                $quote[$id] = $quota;
                $resto = round($resto - $quota, 2);
            }
        }
        $tipi = array_keys($composizione);
        $principale = isset($composizione[TipoChiusura::denaro->value]) ? TipoChiusura::denaro->value : $tipi[0];
        $ripartizione = [];
        $assegnato = [];
        $ultimo = array_key_last($quote);
        foreach ($quote as $id => $quota) {
            $altri = 0.0;
            foreach ($composizione as $tipo => $importo) {
                if ($tipo === $principale) {
                    continue;
                }
                $obiettivo = round($importo * $totale / $totaleComposizione, 2);
                $parte = $id === $ultimo ? round($obiettivo - ($assegnato[$tipo] ?? 0.0), 2) : round($quota * $importo / $totaleComposizione, 2);
                $parte = max(0.0, min($parte, round($quota - $altri, 2)));
                if ($parte > 0) {
                    $ripartizione[$id][$tipo] = $parte;
                    $assegnato[$tipo] = ($assegnato[$tipo] ?? 0.0) + $parte;
                    $altri += $parte;
                }
            }
            $principaleQuota = round($quota - $altri, 2);
            if ($principaleQuota > 0) {
                $ripartizione[$id] = [$principale => $principaleQuota] + ($ripartizione[$id] ?? []);
            }
        }
        return $ripartizione;
    }

    /**
     * Partite della controparte sul sottoconto abbinabili al gruppo, con il
     * residuo (esclusi gli abbinamenti della registrazione in modifica): per
     * le chiusure i documenti e gli insoluti, per le aperture gli acconti e
     * le note di credito, registrati non dopo la registrazione (un incasso
     * anteriore alla fattura è un acconto).
     *
     * @return array<int, array{partita: Partita, residuo: float}> per id, dalla più vecchia
     */
    public function getAbbinabili(Controparte $controparte, Conto $conto, bool $apertura, ?RegistrazioneContabile $registrazione = null): array
    {
        $abbinabili = [];
        foreach ($this->getResidui($controparte, $registrazione) as $id => [$partita, $residuo]) {
            if (($partita->conto->id !== $conto->id) || ($residuo <= self::TOLLERANZA) || ($partita->tipo->isApertura() === $apertura)
                    || (($registrazione !== null) && isset($registrazione->id) && ($partita->registrazioneContabile->id === $registrazione->id))
                    || (($registrazione !== null) && ($partita->data->format('Y-m-d') > $registrazione->dataRegistrazione->format('Y-m-d')))) {
                continue;
            }
            $abbinabili[$id] = ['partita' => $partita, 'residuo' => $residuo];
        }
        uasort($abbinabili, fn(array $prima, array $seconda) => [($prima['partita']->dataScadenza ?? $prima['partita']->data)->format('Y-m-d'), $prima['partita']->id]
                <=> [($seconda['partita']->dataScadenza ?? $seconda['partita']->data)->format('Y-m-d'), $seconda['partita']->id]);
        return $abbinabili;
    }

    /**
     * Documenti della controparte sul sottoconto che un insoluto può
     * riaprire (anche già chiusi).
     *
     * @return Partita[]
     */
    public function getRiapribili(Controparte $controparte, Conto $conto, ?RegistrazioneContabile $registrazione = null): array
    {
        $partite = [];
        foreach ($this->partitaModel->getPerControparte($controparte) as $partita) {
            if (($partita->conto->id === $conto->id) && ($partita->tipo === TipoPartita::documento)
                    && (($registrazione === null) || (isset($registrazione->id) === false) || ($partita->registrazioneContabile->id !== $registrazione->id))) {
                $partite[] = $partita;
            }
        }
        return $partite;
    }

    /**
     * Partite della controparte con il residuo.
     *
     * @return array<int, array{0: Partita, 1: float}>
     */
    public function getResidui(Controparte $controparte, ?RegistrazioneContabile $escludi = null): array
    {
        $residui = [];
        foreach ($this->partitaModel->getPerControparte($controparte) as $partita) {
            $residui[$partita->id] = [$partita, $partita->importo];
        }
        foreach ($this->abbinamentoModel->getPerControparte($controparte) as $abbinamento) {
            if (($escludi !== null) && isset($escludi->id) && ($abbinamento->registrazioneContabile->id === $escludi->id)) {
                continue;
            }
            if (isset($residui[$abbinamento->partita->id])) {
                $residui[$abbinamento->partita->id][1] = round($residui[$abbinamento->partita->id][1] - $abbinamento->importo, 2);
            }
        }
        return $residui;
    }

    /**
     * Abbinamenti proposti per i gruppi della registrazione.
     *
     * @return array<string, array{abbinamenti: array<int, array<string, float>>, acconto: bool, riapre: ?int}>
     */
    public function proponi(RegistrazioneContabile $registrazione, array $gruppi): array
    {
        $scelte = [];
        foreach ($gruppi as $chiave => $gruppo) {
            $abbinabili = $this->getAbbinabili($gruppo['controparte'], $gruppo['conto'], $gruppo['apertura'], $registrazione);
            $residui = array_map(fn(array $voce) => $voce['residuo'], $abbinabili);
            if ($gruppo['apertura']) {
                $abbinamenti = [];
                foreach (self::ripartisci($residui, ['X' => $gruppo['importo']]) as $id => $quote) {
                    $abbinamenti[$id] = [self::tipoAbbinamentoApertura($abbinabili[$id]['partita'])->value => $quote['X']];
                }
                $scelte[$chiave] = ['abbinamenti' => $abbinamenti, 'acconto' => false, 'riapre' => null];
                continue;
            }
            $abbinamenti = self::ripartisci($residui, $gruppo['composizione']);
            $abbinato = round(array_sum(array_map('array_sum', $abbinamenti)), 2);
            $scelte[$chiave] = ['abbinamenti' => $abbinamenti, 'acconto' => (-$gruppo['importo'] - $abbinato > self::TOLLERANZA) && (self::isNota($registrazione) === false), 'riapre' => null];
        }
        return $scelte;
    }

    /**
     * Abbinamenti salvati della registrazione, nella struttura delle scelte.
     *
     * @return array<string, array{abbinamenti: array<int, array<string, float>>, acconto: bool, riapre: ?int}>
     */
    public function getScelte(RegistrazioneContabile $registrazione): array
    {
        $scelte = [];
        if (isset($registrazione->id) === false) {
            return $scelte;
        }
        foreach ($this->abbinamentiDi($registrazione) as $abbinamento) {
            $partita = $abbinamento->partita;
            if ($partita->registrazioneContabile->id === $registrazione->id) {
                continue;
            }
            $chiave = $partita->controparte->id . '-' . $partita->conto->id;
            $scelte[$chiave] ??= ['abbinamenti' => [], 'acconto' => false, 'riapre' => null];
            $scelte[$chiave]['abbinamenti'][$partita->id][$abbinamento->tipo->value] = round(($scelte[$chiave]['abbinamenti'][$partita->id][$abbinamento->tipo->value] ?? 0.0) + $abbinamento->importo, 2);
        }
        foreach ($this->partiteDi($registrazione) as $partita) {
            $chiave = $partita->controparte->id . '-' . $partita->conto->id;
            $scelte[$chiave] ??= ['abbinamenti' => [], 'acconto' => false, 'riapre' => null];
            if ($partita->tipo === TipoPartita::acconto) {
                $scelte[$chiave]['acconto'] = true;
            } elseif (($partita->tipo === TipoPartita::insoluto) && ($partita->partitaOrigine !== null)) {
                $scelte[$chiave]['riapre'] = $partita->partitaOrigine->id;
            }
        }
        return $scelte;
    }

    /**
     * Controlli degli abbinamenti scelti.
     *
     * @return string[] errori
     */
    public function verifica(RegistrazioneContabile $registrazione, array $gruppi, array $scelte): array
    {
        $errori = [];
        foreach ($gruppi as $chiave => $gruppo) {
            $nome = ($gruppo['cliente'] ? 'Cliente ' : 'Fornitore ') . $gruppo['controparte']->denominazione . ': ';
            $scelta = $scelte[$chiave] ?? null;
            if ($scelta === null) {
                $errori[] = $nome . 'indicare l\'abbinamento alle partite aperte.';
                continue;
            }
            $abbinabili = $this->getAbbinabili($gruppo['controparte'], $gruppo['conto'], $gruppo['apertura'], $registrazione);
            $totale = 0.0;
            foreach ($scelta['abbinamenti'] as $id => $quote) {
                $somma = round(array_sum($quote), 2);
                if ($somma <= 0) {
                    continue;
                }
                if (isset($abbinabili[$id]) === false) {
                    $errori[] = $nome . 'partita non abbinabile (già chiusa, di un\'altra controparte o di segno diverso).';
                    continue;
                }
                if (min($quote) < 0) {
                    $errori[] = $nome . 'gli importi abbinati sono positivi.';
                }
                if ($somma - $abbinabili[$id]['residuo'] > self::TOLLERANZA) {
                    $errori[] = $nome . 'alla partita ' . $abbinabili[$id]['partita']->getEtichetta() . ' si abbinano al massimo ' . self::importo($abbinabili[$id]['residuo']) . '.';
                }
                foreach (array_keys($quote) as $tipo) {
                    $tipoChiusura = TipoChiusura::tryFrom((string) $tipo);
                    if (($tipoChiusura === null) || ($gruppo['apertura'] === false && $tipoChiusura === TipoChiusura::acconto)) {
                        $errori[] = $nome . 'modo di chiusura non previsto.';
                    }
                }
                $totale += $somma;
            }
            $importo = abs($gruppo['importo']);
            if ($totale - $importo > self::TOLLERANZA) {
                $errori[] = $nome . 'abbinati ' . self::importo($totale) . ' su un importo di ' . self::importo($importo) . '.';
            } elseif (($gruppo['apertura'] === false) && ($importo - $totale > self::TOLLERANZA) && (self::isNota($registrazione) === false) && (($scelta['acconto'] ?? false) === false)) {
                $errori[] = $nome . 'abbinare l\'intero importo (' . self::importo($importo) . ', abbinati ' . self::importo($totale) . ') o registrare l\'eccedenza come acconto.';
            }
            if (($scelta['riapre'] ?? null) !== null) {
                $riapribili = array_filter($this->getRiapribili($gruppo['controparte'], $gruppo['conto'], $registrazione), fn(Partita $partita) => $partita->id === (int) $scelta['riapre']);
                if (($gruppo['apertura'] === false) || $registrazione->causale->isDocumentoRichiesto() || (count($riapribili) === 0)) {
                    $errori[] = $nome . 'l\'insoluto riapre un documento della controparte, con una registrazione che addebita il cliente o accredita il fornitore.';
                }
            }
        }
        return array_merge($errori, $this->verificaPartiteProprie($registrazione, $this->nuovePartite($registrazione, $gruppi, $scelte)));
    }

    /**
     * Le partite della registrazione già abbinate da altre registrazioni non
     * si eliminano né si riducono sotto quanto abbinato.
     *
     * @param array<string, array{gruppo: array, tipo: TipoPartita, importo: float, origine: ?int}> $nuove
     * @return string[]
     */
    private function verificaPartiteProprie(RegistrazioneContabile $registrazione, array $nuove): array
    {
        if (isset($registrazione->id) === false) {
            return [];
        }
        $errori = [];
        foreach ($this->partiteDi($registrazione) as $partita) {
            $esterni = 0.0;
            foreach ($this->abbinamentoModel->getPerPartita($partita) as $abbinamento) {
                if (($abbinamento->partita->id === $partita->id) && ($abbinamento->registrazioneContabile->id !== $registrazione->id)) {
                    $esterni += $abbinamento->importo;
                }
            }
            $chiave = $partita->controparte->id . '-' . $partita->conto->id . '|' . $partita->tipo->value;
            $nuovoImporto = $nuove[$chiave]['importo'] ?? 0.0;
            if ($esterni - $nuovoImporto > self::TOLLERANZA) {
                $errori[] = 'La partita ' . $partita->getEtichetta() . ' di ' . $partita->controparte->denominazione . ' è già abbinata ad altre registrazioni per ' . self::importo($esterni) . ': modificare prima quelle.';
            }
        }
        return $errori;
    }

    /**
     * Partite che la registrazione apre.
     *
     * @return array<string, array{gruppo: array, tipo: TipoPartita, importo: float, origine: ?int}> per gruppo e tipo
     */
    private function nuovePartite(RegistrazioneContabile $registrazione, array $gruppi, array $scelte): array
    {
        $nuove = [];
        foreach ($gruppi as $chiave => $gruppo) {
            $scelta = $scelte[$chiave] ?? ['abbinamenti' => [], 'acconto' => false, 'riapre' => null];
            if ($gruppo['apertura']) {
                $tipo = ($scelta['riapre'] ?? null) !== null ? TipoPartita::insoluto : TipoPartita::documento;
                $nuove[$chiave . '|' . $tipo->value] = ['gruppo' => $gruppo, 'tipo' => $tipo, 'importo' => $gruppo['importo'], 'origine' => $tipo === TipoPartita::insoluto ? (int) $scelta['riapre'] : null];
                continue;
            }
            $abbinato = round(array_sum(array_map('array_sum', $scelta['abbinamenti'])), 2);
            $eccedenza = round(-$gruppo['importo'] - $abbinato, 2);
            if ($eccedenza > self::TOLLERANZA) {
                $tipo = self::isNota($registrazione) ? TipoPartita::notaCredito : TipoPartita::acconto;
                $nuove[$chiave . '|' . $tipo->value] = ['gruppo' => $gruppo, 'tipo' => $tipo, 'importo' => $eccedenza, 'origine' => null];
            }
        }
        return $nuove;
    }

    /**
     * Scrive partite e abbinamenti della registrazione salvata (nella
     * transazione del salvataggio), sostituendo quelli precedenti.
     */
    public function registra(RegistrazioneContabile $registrazione, array $gruppi, array $scelte, bool $automatico = false): void
    {
        foreach ($this->abbinamentiDi($registrazione) as $abbinamento) {
            $this->dataMapper->delete($abbinamento);
        }
        $esistenti = [];
        foreach ($this->partiteDi($registrazione) as $partita) {
            $esistenti[$partita->controparte->id . '-' . $partita->conto->id . '|' . $partita->tipo->value] = $partita;
        }
        $proprie = [];
        foreach ($this->nuovePartite($registrazione, $gruppi, $scelte) as $chiave => $nuova) {
            $partita = $esistenti[$chiave] ?? new Partita($this->dataMapper);
            unset($esistenti[$chiave]);
            $gruppo = $nuova['gruppo'];
            $partita->soggetto = $registrazione->soggetto;
            $partita->controparte = $gruppo['controparte'];
            $partita->conto = $gruppo['conto'];
            $partita->registrazioneContabile = $registrazione;
            $partita->tipo = $nuova['tipo'];
            $partita->cliente = $gruppo['cliente'];
            $partita->data = $registrazione->dataRegistrazione;
            $partita->numeroDocumento = $registrazione->numeroDocumento;
            $partita->dataDocumento = $registrazione->dataDocumento;
            $partita->dataScadenza ??= $registrazione->dataDocumento ?? $registrazione->dataRegistrazione;
            $partita->importo = $nuova['importo'];
            $partita->partitaOrigine = $nuova['origine'] === null ? null : $this->partitaModel->getEntityById($nuova['origine']);
            $this->dataMapper->save($partita);
            if ($nuova['tipo']->isApertura()) {
                $proprie[$gruppo['chiave']] = $partita;
            }
        }
        foreach ($esistenti as $partita) {
            $this->dataMapper->delete($partita);
        }
        foreach ($gruppi as $chiave => $gruppo) {
            foreach ($scelte[$chiave]['abbinamenti'] ?? [] as $id => $quote) {
                $abbinata = $this->partitaModel->getEntityById((int) $id);
                if ($abbinata === null) {
                    continue;
                }
                foreach ($quote as $tipo => $importo) {
                    if (round($importo, 2) <= 0) {
                        continue;
                    }
                    if ($gruppo['apertura']) {
                        // il documento usa l'acconto o la nota: si chiudono entrambi
                        $tipoChiusura = self::tipoAbbinamentoApertura($abbinata);
                        $this->scriviAbbinamento($registrazione, $abbinata, $tipoChiusura, $importo, $proprie[$chiave], $automatico);
                        $this->scriviAbbinamento($registrazione, $proprie[$chiave], $tipoChiusura, $importo, $abbinata, $automatico);
                    } else {
                        $this->scriviAbbinamento($registrazione, $abbinata, TipoChiusura::from((string) $tipo), $importo, null, $automatico);
                    }
                }
            }
        }
    }

    /**
     * Motivo per cui la registrazione non si elimina (partite abbinate da
     * altre registrazioni o riaperte da un insoluto), null se si elimina.
     */
    public function getBloccoEliminazione(RegistrazioneContabile $registrazione): ?string
    {
        $errori = $this->verificaPartiteProprie($registrazione, []);
        if (count($errori) > 0) {
            return $errori[0];
        }
        foreach ($this->partiteDi($registrazione) as $partita) {
            if ($this->partitaModel->countByPartitaOrigine($partita) > 0) {
                return 'La partita ' . $partita->getEtichetta() . ' è riaperta da un insoluto: eliminare prima quello.';
            }
        }
        return null;
    }

    public function elimina(RegistrazioneContabile $registrazione): void
    {
        foreach ($this->abbinamentiDi($registrazione) as $abbinamento) {
            $this->dataMapper->delete($abbinamento);
        }
        foreach ($this->partiteDi($registrazione) as $partita) {
            $this->dataMapper->delete($partita);
        }
    }

    /**
     * Ritenute d'acconto abbinate nell'anno alle partite dei clienti
     * (subite) o dei fornitori (operate).
     */
    public function getRitenute(Soggetto $soggetto, int $anno, bool $clienti = true): float
    {
        $totale = 0.0;
        foreach ($this->abbinamentoModel->getPerSoggetto($soggetto) as $abbinamento) {
            if (($abbinamento->tipo === TipoChiusura::ritenuta) && ($abbinamento->partita->cliente === $clienti) && ((int) $abbinamento->data->format('Y') === $anno)) {
                $totale += $abbinamento->importo;
            }
        }
        return round($totale, 2);
    }

    /**
     * Scadenzario dell'azienda per controparte e sottoconto: partite con
     * residuo e abbinamenti, totale delle partite (nel segno naturale del
     * partitario, gli acconti e le note in diminuzione) e quadratura con
     * il saldo del sottoconto.
     *
     * @return array<string, array{controparte: Controparte, conto: Conto, cliente: bool, partite: array, residuo: float, saldo: float, differenza: float, daVerificare: int}>
     */
    public function getScadenzario(Soggetto $soggetto, bool $tutte = false, ?Controparte $filtro = null): array
    {
        $perPartita = [];
        foreach ($this->abbinamentoModel->getPerSoggetto($soggetto) as $abbinamento) {
            $perPartita[$abbinamento->partita->id][] = $abbinamento;
        }
        $scadenzario = [];
        foreach ($this->partitaModel->getPerSoggetto($soggetto) as $partita) {
            if (($filtro !== null) && ($partita->controparte->id !== $filtro->id)) {
                continue;
            }
            $chiave = $partita->controparte->id . '-' . $partita->conto->id;
            $scadenzario[$chiave] ??= ['controparte' => $partita->controparte, 'conto' => $partita->conto, 'cliente' => $partita->cliente, 'partite' => [], 'residuo' => 0.0, 'saldo' => 0.0, 'differenza' => 0.0, 'daVerificare' => 0];
            $abbinamenti = $perPartita[$partita->id] ?? [];
            $residuo = round($partita->importo - array_sum(array_map(fn(Abbinamento $abbinamento) => $abbinamento->importo, $abbinamenti)), 2);
            $automatici = count(array_filter($abbinamenti, fn(Abbinamento $abbinamento) => $abbinamento->automatico));
            $scadenzario[$chiave]['residuo'] += $partita->tipo->isApertura() ? $residuo : -$residuo;
            $scadenzario[$chiave]['daVerificare'] += $automatici;
            if ($tutte || (abs($residuo) > self::TOLLERANZA)) {
                $scadenzario[$chiave]['partite'][] = ['partita' => $partita, 'residuo' => $residuo, 'abbinamenti' => $abbinamenti, 'daVerificare' => $automatici > 0];
            }
        }
        $righe = new RigaContabileModel($this->dataMapper);
        foreach ($scadenzario as $chiave => &$voce) {
            $saldo = 0.0;
            foreach ($righe->getMovimenti($soggetto, $voce['conto'], null, null) as $riga) {
                if (($riga->controparte === null) || ($riga->controparte->id === $voce['controparte']->id)) {
                    $saldo += $riga->dare - $riga->avere;
                }
            }
            $voce['saldo'] = round($voce['cliente'] ? $saldo : -$saldo, 2);
            $voce['residuo'] = round($voce['residuo'], 2);
            $voce['differenza'] = round($voce['saldo'] - $voce['residuo'], 2);
            usort($voce['partite'], fn(array $prima, array $seconda) => [($prima['partita']->dataScadenza ?? $prima['partita']->data)->format('Y-m-d'), $prima['partita']->id]
                    <=> [($seconda['partita']->dataScadenza ?? $seconda['partita']->data)->format('Y-m-d'), $seconda['partita']->id]);
        }
        unset($voce);
        uasort($scadenzario, fn(array $prima, array $seconda) => [$prima['cliente'] ? 0 : 1, $prima['controparte']->denominazione] <=> [$seconda['cliente'] ? 0 : 1, $seconda['controparte']->denominazione]);
        return $tutte ? $scadenzario : array_filter($scadenzario, fn(array $voce) => (count($voce['partite']) > 0) || (abs($voce['differenza']) > self::TOLLERANZA) || ($voce['daVerificare'] > 0));
    }

    /**
     * Conferma gli abbinamenti automatici della controparte (creati dalla
     * migrazione), dopo la verifica dell'utente.
     */
    public function conferma(Controparte $controparte): int
    {
        $confermati = 0;
        foreach ($this->abbinamentoModel->getPerControparte($controparte) as $abbinamento) {
            if ($abbinamento->automatico) {
                $abbinamento->automatico = false;
                $this->dataMapper->save($abbinamento);
                $confermati++;
            }
        }
        return $confermati;
    }

    /**
     * @return Partita[]
     */
    private function partiteDi(RegistrazioneContabile $registrazione): array
    {
        if (isset($registrazione->id) === false) {
            return [];
        }
        return array_values(array_filter(iterator_to_array($this->partitaModel->getPerRegistrazione($registrazione), false), fn(Partita $partita) => $partita->registrazioneContabile->id === $registrazione->id));
    }

    /**
     * @return Abbinamento[]
     */
    private function abbinamentiDi(RegistrazioneContabile $registrazione): array
    {
        if (isset($registrazione->id) === false) {
            return [];
        }
        return array_values(array_filter(iterator_to_array($this->abbinamentoModel->getPerRegistrazione($registrazione), false), fn(Abbinamento $abbinamento) => $abbinamento->registrazioneContabile->id === $registrazione->id));
    }

    private function scriviAbbinamento(RegistrazioneContabile $registrazione, Partita $partita, TipoChiusura $tipo, float $importo, ?Partita $collegata, bool $automatico): void
    {
        $abbinamento = new Abbinamento($this->dataMapper);
        $abbinamento->soggetto = $registrazione->soggetto;
        $abbinamento->controparte = $partita->controparte;
        $abbinamento->partita = $partita;
        $abbinamento->registrazioneContabile = $registrazione;
        $abbinamento->data = $registrazione->dataRegistrazione;
        $abbinamento->tipo = $tipo;
        $abbinamento->importo = round($importo, 2);
        $abbinamento->partitaCollegata = $collegata;
        $abbinamento->automatico = $automatico;
        $this->dataMapper->save($abbinamento);
    }

    public static function tipoAbbinamentoApertura(Partita $abbinata): TipoChiusura
    {
        return $abbinata->tipo === TipoPartita::notaCredito ? TipoChiusura::notaCredito : TipoChiusura::acconto;
    }

    private static function isNota(RegistrazioneContabile $registrazione): bool
    {
        return in_array($registrazione->causale, [CausaleContabile::notaCreditoEmessa, CausaleContabile::notaCreditoRicevuta], true);
    }

    private static function importo(float $valore): string
    {
        return number_format($valore, 2, ',', '.');
    }
}
