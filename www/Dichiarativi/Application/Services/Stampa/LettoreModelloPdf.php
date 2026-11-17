<?php

namespace Dichiarativi\Application\Services\Stampa;

/**
 * Posizioni dei campi del catalogo sulle pagine del modello ufficiale in PDF
 * dell'Agenzia delle Entrate, ricavate dalla grafica e dai testi della
 * pagina (pdftocairo -svg e pdftotext -bbox, pacchetto poppler-utils), in
 * punti con l'origine in alto a sinistra:
 *
 * - i righi sono le fasce tra le linee orizzontali lunghe che separano i
 *   righi del quadro; la sigla del rigo ("RN1", "RA12") è a sinistra della
 *   fascia, anche a cavallo di una linea interna dei righi su due linee;
 * - ogni casella ha in alto a sinistra il numero della colonna, in un corpo
 *   piccolo che si ripete nella pagina: la casella va dal numero al numero
 *   successivo sulla stessa linea (o al margine destro); il ",00" stampato
 *   ne segna la fine per gli importi, scritti allineati a destra;
 * - con il rettangolo bianco della casella, se c'è, si prendono i suoi
 *   bordi;
 * - nei righi senza numeri di colonna un solo importo è la colonna 1, più
 *   importi (tabelle con i numeri nella sola prima riga, come il quadro RX)
 *   prendono la colonna dalla griglia dei numeri della riga numerata più
 *   vicina sopra;
 * - i righi del prospetto dei familiari a carico sono numerati da 1 a 5
 *   senza sigla (codici FA).
 *
 * In testa a ogni pagina dei quadri: le caselle del codice fiscale e del
 * numero di modulo ("Mod. N."), dalle tacche verticali.
 *
 * Dopo le correzioni manuali, rifinisci() aggiusta tutte le posizioni
 * sulla pagina: importi al ",00" (o alla virgola dei centesimi), celle
 * dalle tacche verticali.
 *
 * I campi non riconosciuti (strutture particolari, campi testuali senza
 * numero di colonna) restano negli scarti: si aggiungono con il file delle
 * correzioni (ModelliUfficialiDichiarativi).
 */
class LettoreModelloPdf
{

    public const LARGHEZZA = 595.276;
    public const ALTEZZA = 841.89;
    private const MARGINE_DESTRO = 560.0;
    private const ALTEZZA_CASELLA = 12.0;
    private const COLONNA_SIGLE = 125.0;

    /** @var array<string, true> */
    private array $catalogo;

    /** @var string[] */
    private array $scarti = [];

    /**
     * @param string[] $codici codici dei campi del catalogo del modello
     */
    public function __construct(array $codici)
    {
        $this->catalogo = array_fill_keys($codici, true);
    }

    /**
     * @return string[] caselle non attribuite a un campo del catalogo
     */
    public function getScarti(): array
    {
        return $this->scarti;
    }

    /**
     * @return list<array{codice: string, fascicolo: string, pagina: int, x: float, y: float, larghezza: float, altezza: float, celle: list<float>, destra: bool}>
     */
    public function leggi(string $pdf, string $fascicolo): array
    {
        $this->scarti = [];
        if (preg_match('~Pages:\s+(\d+)~', (string) shell_exec('pdfinfo ' . escapeshellarg($pdf) . ' 2>/dev/null'), $info) !== 1) {
            throw new \RuntimeException('PDF del modello non leggibile: ' . $pdf);
        }
        $posizioni = [];
        $trovati = [];
        for ($pagina = 1; $pagina <= (int) $info[1]; $pagina++) {
            [$svg, $html] = $this->estrai($pdf, $pagina);
            [$rettangoli, $separatori, $tacche] = $this->leggiGrafica($svg);
            $parole = $this->leggiParole($html);
            $campi = $this->leggiCampi($parole, $rettangoli, $separatori, $pagina);
            foreach ($campi as $campo) {
                if (isset($trovati[$campo['codice']])) {
                    $this->scarti[] = sprintf('pagina %d: %s già trovato a pagina %d', $pagina, $campo['codice'], $trovati[$campo['codice']]);
                    continue;
                }
                $trovati[$campo['codice']] = $pagina;
                $posizioni[] = ['codice' => $campo['codice'], 'fascicolo' => $fascicolo, 'pagina' => $pagina] + $campo;
            }
            if (count($campi) > 0) {
                foreach ($this->leggiIntestazione($parole, array_filter($tacche, fn(array $t) => $t['y'] < 140)) as $campo) {
                    $posizioni[] = ['codice' => $campo['codice'], 'fascicolo' => $fascicolo, 'pagina' => $pagina] + $campo;
                }
            }
        }
        return $posizioni;
    }

    /**
     * @return array{string, string} SVG e parole (HTML di pdftotext -bbox) della pagina
     */
    private function estrai(string $pdf, int $pagina): array
    {
        $base = sys_get_temp_dir() . '/modello-' . bin2hex(random_bytes(4));
        try {
            foreach ([sprintf('pdftocairo -svg -f %d -l %d %s %s.svg', $pagina, $pagina, escapeshellarg($pdf), $base),
                sprintf('pdftotext -bbox -f %d -l %d %s %s.html', $pagina, $pagina, escapeshellarg($pdf), $base)] as $comando) {
                exec($comando . ' 2>&1', $output, $esito);
                if ($esito !== 0) {
                    throw new \RuntimeException('Comando non riuscito: ' . $comando);
                }
            }
            return [(string) file_get_contents($base . '.svg'), (string) file_get_contents($base . '.html')];
        } finally {
            @unlink($base . '.svg');
            @unlink($base . '.html');
        }
    }

    /**
     * Rettangoli bianchi (caselle), linee orizzontali lunghe (separatori dei
     * righi) e tacche verticali (divisioni delle caselle a celle, come quelle
     * del codice fiscale), con le trasformazioni dei percorsi applicate.
     *
     * @return array{list<array{x: float, y: float, w: float, h: float}>, list<float>, list<array{x: float, y: float, h: float}>}
     */
    private function leggiGrafica(string $svg): array
    {
        $rettangoli = [];
        $separatori = [];
        $tacche = [];
        preg_match_all('~<path ([^>]*)/>~', $svg, $percorsi);
        foreach ($percorsi[1] as $attributi) {
            if (preg_match('~ d="([^"]+)"~', $attributi, $d) !== 1) {
                continue;
            }
            $matrice = preg_match('~transform="matrix\(([^)]+)\)"~', $attributi, $t) === 1 ? array_map('floatval', explode(',', $t[1])) : null;
            $bianco = str_contains($attributi, 'fill="rgb(100%, 100%, 100%)"');
            $tratto = str_contains($attributi, 'stroke="');
            foreach (preg_split('~(?=M )~', trim($d[1])) as $sottopercorso) {
                preg_match_all('~(-?[\d.]+) (-?[\d.]+)~', $sottopercorso, $punti, PREG_SET_ORDER);
                if (count($punti) < 2) {
                    continue;
                }
                $xs = [];
                $ys = [];
                foreach ($punti as $punto) {
                    [$x, $y] = self::trasforma($matrice, (float) $punto[1], (float) $punto[2]);
                    $xs[] = $x;
                    $ys[] = $y;
                }
                $larghezza = max($xs) - min($xs);
                $altezza = max($ys) - min($ys);
                // caselle: almeno 7,5 punti per lato (i quadratini bianchi attorno ad alcuni numeri di colonna sono più piccoli)
                if ($bianco && ($larghezza >= 7.5) && ($altezza >= 7.5) && ($larghezza < 470) && (min($xs) > 100)) {
                    $rettangoli[] = ['x' => min($xs), 'y' => min($ys), 'w' => $larghezza, 'h' => $altezza];
                } elseif (($bianco === false) && ($altezza < 1.2) && ($larghezza > 300) && (min($xs) < self::COLONNA_SIGLE)) {
                    // separatori dei righi: le linee lunghe che attraversano la colonna delle sigle (le linee interne di un rigo
                    // su più linee, come RA o RN47, partono più a destra)
                    $separatori[] = round(min($ys), 1);
                } elseif ($tratto && ($larghezza < 0.6) && ($altezza > 3) && ($altezza < 25)) {
                    $tacche[] = ['x' => round(min($xs), 1), 'y' => min($ys), 'h' => $altezza];
                }
            }
        }
        $separatori = array_values(array_unique($separatori));
        sort($separatori);
        return [self::unisciQuadratini($rettangoli), $separatori, $tacche];
    }

    /**
     * Quadratino bianco del numero di colonna attaccato a sinistra della
     * casella del valore (come nel rigo RG37): un solo rettangolo.
     *
     * @param list<array{x: float, y: float, w: float, h: float}> $rettangoli
     * @return list<array{x: float, y: float, w: float, h: float}>
     */
    private static function unisciQuadratini(array $rettangoli): array
    {
        foreach ($rettangoli as $i => $piccolo) {
            if ($piccolo['w'] >= 14) {
                continue;
            }
            foreach ($rettangoli as $j => $casella) {
                if (($i !== $j) && isset($rettangoli[$j]) && (abs($casella['y'] - $piccolo['y']) < 1.5) && (abs($casella['h'] - $piccolo['h']) < 1.5)
                        && ($casella['x'] - ($piccolo['x'] + $piccolo['w']) > -1) && ($casella['x'] - ($piccolo['x'] + $piccolo['w']) < 3)) {
                    $rettangoli[$j] = ['x' => $piccolo['x'], 'y' => min($piccolo['y'], $casella['y']), 'w' => $casella['x'] + $casella['w'] - $piccolo['x'], 'h' => max($piccolo['h'], $casella['h'])];
                    unset($rettangoli[$i]);
                    break;
                }
            }
        }
        return array_values($rettangoli);
    }

    /**
     * @param list<float>|null $matrice
     * @return array{float, float}
     */
    private static function trasforma(?array $matrice, float $x, float $y): array
    {
        if ($matrice === null) {
            return [$x, $y];
        }
        [$a, $b, $c, $d, $e, $f] = $matrice;
        return [$a * $x + $c * $y + $e, $b * $x + $d * $y + $f];
    }

    /**
     * @return list<array{x: float, y: float, x2: float, y2: float, t: string}>
     */
    private function leggiParole(string $html): array
    {
        preg_match_all('~<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="([\d.]+)">([^<]*)</word>~', $html, $parole, PREG_SET_ORDER);
        return array_map(fn(array $p) => ['x' => (float) $p[1], 'y' => (float) $p[2], 'x2' => (float) $p[3], 'y2' => (float) $p[4], 't' => html_entity_decode($p[5], ENT_QUOTES | ENT_HTML5)], $parole);
    }

    /**
     * @return list<array{codice: string, x: float, y: float, larghezza: float, altezza: float, celle: list<float>, destra: bool}>
     */
    private function leggiCampi(array $parole, array $rettangoli, array $separatori, int $pagina): array
    {
        $familiari = count(array_filter($parole, fn(array $p) => $p['t'] === 'FAMILIARI')) > 0;
        $etichette = array_values(array_filter($parole, fn(array $p) => ($p['x'] < 140) && ((preg_match('~^[A-Z]{2}\d{1,3}$~', $p['t']) === 1)
                || ($familiari && (preg_match('~^[1-5]$~', $p['t']) === 1) && (($p['y2'] - $p['y']) > 6) && ($p['x'] > 100)))));
        // sigle dei righi su due colonne (come VM7-VM12): più a destra, nello stesso corpo delle sigle a sinistra
        $corpiSigle = array_map(fn(array $p) => round($p['y2'] - $p['y'], 1), array_filter($etichette, fn(array $p) => ctype_digit($p['t']) === false));
        foreach ($parole as $parola) {
            if (($parola['x'] >= 140) && (preg_match('~^[A-Z]{2}\d{1,3}$~', $parola['t']) === 1) && in_array(round($parola['y2'] - $parola['y'], 1), $corpiSigle, true)
                    && in_array(substr($parola['t'], 0, 2), array_map(fn(array $e) => substr($e['t'], 0, 2), $etichette), true)) {
                $etichette[] = $parola;
            }
        }
        $numeri = $this->numeriColonna($parole);
        $decimali = array_values(array_filter($parole, fn(array $p) => $p['t'] === ',00'));
        $percentuali = array_values(array_filter($parole, fn(array $p) => $p['t'] === '%'));
        $rigoDi = function (float $y, ?float $x = null) use ($separatori, $etichette): ?string {
            $sopra = 0.0;
            $sotto = self::ALTEZZA;
            foreach ($separatori as $separatore) {
                if ($separatore <= $y) {
                    $sopra = $separatore;
                } else {
                    $sotto = $separatore;
                    break;
                }
            }
            // sigla che tocca la fascia (anche a cavallo di una linea interna del rigo); con più sigle nella fascia (righi senza
            // linee di separazione, come nel modello IVA TR) la più vicina
            $rigo = null;
            $distanza = null;
            foreach ($etichette as $etichetta) {
                if (min($etichetta['y2'], $sotto) - max($etichetta['y'], $sopra) <= 0) {
                    continue;
                }
                $scarto = abs((($etichetta['y'] + $etichetta['y2']) / 2) - $y);
                // righi su due colonne: conta solo la sigla a sinistra della casella, la più vicina
                if (($x !== null) && ($etichetta['x'] > 140)) {
                    if ($etichetta['x'] > $x) {
                        continue;
                    }
                    $scarto -= 0.001 * $etichetta['x'];
                } elseif (($x !== null) && (count(array_filter($etichette, fn(array $e) => ($e['x'] > 140) && ($e['x'] < $x) && (abs($e['y'] - $etichetta['y']) < 3))) > 0)) {
                    continue;
                }
                if (($distanza === null) || ($scarto < $distanza)) {
                    $distanza = $scarto;
                    $rigo = ctype_digit($etichetta['t']) ? 'FA' . $etichetta['t'] : $etichetta['t'];
                }
            }
            return $rigo;
        };
        // caselle numerate: dal numero al numero successivo sulla stessa linea, o al ",00" per gli importi
        $ancore = [];
        $usati = [];
        foreach ($numeri as $numero) {
            $fine = self::MARGINE_DESTRO;
            $destra = false;
            foreach ($numeri as $altro) {
                if ((abs($altro['y'] - $numero['y']) < 3) && ($altro['x'] > $numero['x'] + 2) && ($altro['x'] - 1 < $fine)) {
                    $fine = $altro['x'] - 1;
                }
            }
            // il rettangolo bianco della casella, se c'è, la chiude (gli importi oltre sono di colonne senza numero) e ne dà l'altezza
            $fondo = $numero['y'] + 10;
            $r = self::casellaDi($rettangoli, $numero['x'], $numero['y']);
            if ($r !== null) {
                $fine = min($fine, $r['x'] + $r['w'] + 1);
                $fondo = max($fondo, $r['y'] + $r['h']);
            }
            foreach ($decimali as $indice => $decimale) {
                if (($decimale['x'] > $numero['x']) && ($decimale['x2'] <= $fine + 2) && ($decimale['y'] > $numero['y'] - 1) && ($decimale['y'] < $fondo)) {
                    $destra = true;
                    $fine = min($fine, $decimale['x']);
                    $usati[$indice] = true;
                }
            }
            // percentuali: il segno "%" stampato chiude la casella
            foreach ($percentuali as $segno) {
                if (($segno['x'] > $numero['x']) && ($segno['x2'] <= $fine + 2) && ($segno['y'] > $numero['y'] - 1) && ($segno['y'] < $fondo)) {
                    $destra = true;
                    $fine = min($fine, $segno['x'] - 1);
                }
            }
            $ancore[] = ['x' => $numero['x'], 'y' => $numero['y'], 'colonna' => $numero['t'], 'fine' => $fine, 'destra' => $destra];
        }
        // importi senza numero di colonna, per rigo
        $senzaNumeri = [];
        foreach ($decimali as $indice => $decimale) {
            $rigo = isset($usati[$indice]) ? null : $rigoDi($decimale['y'] + 3, $decimale['x']);
            if ($rigo !== null) {
                $senzaNumeri[$rigo][] = $decimale;
            }
        }
        $senzaGriglia = [];
        foreach ($senzaNumeri as $rigo => $importi) {
            $numerato = count(array_filter($numeri, fn(array $n) => $rigoDi($n['y'] + 3, $n['x']) === $rigo)) > 0;
            if ((count($importi) === 1) && ($numerato === false)) {
                // rigo con un solo importo: colonna 1
                $decimale = $importi[0];
                $inizio = $decimale['x'] - 75;
                foreach ($rettangoli as $r) {
                    if (($decimale['x'] >= $r['x']) && ($decimale['x'] <= $r['x'] + $r['w']) && ($decimale['y'] >= $r['y'] - 2) && ($decimale['y'] <= $r['y'] + $r['h'])) {
                        $inizio = $r['x'];
                    }
                }
                $ancore[] = ['x' => $inizio, 'y' => $decimale['y'] - 3.5, 'colonna' => null, 'fine' => $decimale['x'], 'destra' => true];
                continue;
            }
            // tabelle con i numeri di colonna nella sola prima riga: griglia della riga più vicina sopra con almeno due numeri
            $griglia = $this->griglia($numeri, $importi[0]['y']);
            if (count($griglia) === 0) {
                array_push($senzaGriglia, ...$importi);
                continue;
            }
            foreach ($importi as $decimale) {
                $colonna = null;
                foreach ($griglia as $numero) {
                    if (($numero['x'] < $decimale['x']) && (($colonna === null) || ($numero['x'] > $colonna['x']))) {
                        $colonna = $numero;
                    }
                }
                if ($colonna !== null) {
                    $ancore[] = ['x' => $colonna['x'], 'y' => $decimale['y'] - 3.5, 'colonna' => $colonna['t'], 'fine' => $decimale['x'], 'destra' => true];
                }
            }
        }
        // tabelle senza alcun numero di colonna (come i righi RX31-RX43): colonne nell'ordine delle posizioni degli importi
        $posizioniColonne = [];
        foreach ($senzaGriglia as $decimale) {
            if (count(array_filter($posizioniColonne, fn(float $x) => abs($x - $decimale['x']) < 5)) === 0) {
                $posizioniColonne[] = $decimale['x'];
            }
        }
        sort($posizioniColonne);
        foreach ($senzaGriglia as $decimale) {
            foreach ($posizioniColonne as $indice => $x) {
                if (abs($x - $decimale['x']) < 5) {
                    $inizio = $indice === 0 ? $decimale['x'] - 75 : max($posizioniColonne[$indice - 1] + 12, $decimale['x'] - 75);
                    $ancore[] = ['x' => $inizio, 'y' => $decimale['y'] - 3.5, 'colonna' => (string) ($indice + 1), 'fine' => $decimale['x'], 'destra' => true];
                }
            }
        }
        $campi = [];
        foreach ($ancore as $ancora) {
            $rigo = $rigoDi($ancora['y'] + 3, $ancora['x']);
            if ($rigo === null) {
                $this->scarti[] = sprintf('pagina %d: colonna %s senza rigo (%.1f, %.1f)', $pagina, $ancora['colonna'] ?? '-', $ancora['x'], $ancora['y']);
                continue;
            }
            $fine = $ancora['fine'];
            $destra = $ancora['destra'];
            $casella = ['x' => $ancora['x'], 'y' => $ancora['y'], 'w' => $fine - $ancora['x'], 'h' => self::ALTEZZA_CASELLA];
            $r = self::casellaDi($rettangoli, $ancora['x'], $ancora['y']);
            if ($r !== null) {
                $casella = ['x' => $r['x'], 'y' => $r['y'], 'w' => min($r['w'], $fine - $r['x'] + 1), 'h' => $r['h']];
            }
            $codice = self::codice($rigo, $ancora['colonna']);
            if (isset($this->catalogo[$codice]) === false) {
                $this->scarti[] = sprintf('pagina %d: %s non è nel catalogo (rigo %s, colonna %s)', $pagina, $codice, $rigo, $ancora['colonna'] ?? '-');
                continue;
            }
            $campi[] = ['codice' => $codice, 'x' => round($casella['x'], 1), 'y' => round($casella['y'], 1), 'larghezza' => round($casella['w'], 1), 'altezza' => round($casella['h'], 1), 'celle' => [], 'destra' => $destra];
        }
        return array_merge($campi, $this->copiaColonne($campi, $etichette, $rettangoli, $rigoDi, $numeri, $parole));
    }

    /**
     * Righi senza numeri di colonna sotto un rigo numerato (come i righi 2-5
     * del prospetto dei familiari): le caselle bianche della fascia del rigo
     * agli stessi x e con la stessa larghezza di quelle del rigo numerato più
     * vicino sopra prendono le sue colonne. Un rigo senza alcuna casella
     * propria e senza rettangoli bianchi (righi del modello IVA TR) prende le
     * caselle del rigo sopra traslate della distanza tra le sigle.
     */
    private function copiaColonne(array $campi, array $etichette, array $rettangoli, \Closure $rigoDi, array $numeri, array $parole): array
    {
        $perRigo = [];
        foreach ($campi as $campo) {
            $perRigo[substr($campo['codice'], 0, 5)][substr($campo['codice'], 5)] = $campo;
        }
        usort($etichette, fn(array $a, array $b) => $a['y'] <=> $b['y']);
        $copiati = [];
        $precedente = null;
        $yEtichette = [];
        foreach ($etichette as $etichetta) {
            $rigo = $rigoDi(($etichetta['y'] + $etichetta['y2']) / 2, $etichetta['x'] + 1);
            if ($rigo === null) {
                continue;
            }
            $prefisso = self::prefisso($rigo);
            $yEtichette[$prefisso] ??= $etichetta['y'];
            $vuoto = isset($perRigo[$prefisso]) === false;
            // descrizione del rigo nell'area dei dati (come "Totale crediti" nei righi TE40-TE43): niente copia per traslazione
            $descritto = count(array_filter($parole, fn(array $p) => ($p['x'] > $etichetta['x2'] + 2) && (abs($p['y'] - $etichetta['y']) < 4)
                    && (preg_match('~^[A-Za-zÀ-ú]{3,}~u', $p['t']) === 1))) > 0;
            if ($vuoto) {
                // rigo vuoto con le sue caselle bianche: colonne dalla griglia dei numeri dell'intestazione
                foreach ($rettangoli as $r) {
                    if (($r['w'] < 20) || ($rigoDi($r['y'] + $r['h'] / 2, $r['x'] + 1) !== $rigo)) {
                        continue;
                    }
                    // un'area bianca con un testo dentro non è una casella
                    if (count(array_filter($parole, fn(array $p) => ($p['x'] >= $r['x']) && ($p['x2'] <= $r['x'] + $r['w']) && ($p['y'] >= $r['y']) && ($p['y2'] <= $r['y'] + $r['h'])
                            && (preg_match('~^[A-Za-zÀ-ú]{3,}~u', $p['t']) === 1))) > 0) {
                        continue;
                    }
                    $colonna = null;
                    foreach ($this->griglia($numeri, $r['y'] + 1) as $numero) {
                        if (($numero['x'] < $r['x'] + $r['w'] - 10) && (($colonna === null) || ($numero['x'] > $colonna['x']))) {
                            $colonna = $numero;
                        }
                    }
                    $codice = $colonna === null ? null : self::codice($rigo, $colonna['t']);
                    if (($codice !== null) && isset($this->catalogo[$codice]) && (isset($perRigo[$prefisso][substr($codice, 5)]) === false)) {
                        $nuovo = ['codice' => $codice, 'x' => round($r['x'], 1), 'y' => round($r['y'], 1), 'larghezza' => round($r['w'], 1), 'altezza' => round($r['h'], 1), 'celle' => [], 'destra' => false];
                        $perRigo[$prefisso][substr($codice, 5)] = $nuovo;
                        $copiati[] = $nuovo;
                    }
                }
                // righi collocati dalle caselle: le colonne che mancano si completano per traslazione
                $vuoto = $descritto === false;
            }
            if (($precedente !== null) && ($precedente !== $prefisso) && (substr($precedente, 0, 2) === substr($prefisso, 0, 2))) {
                foreach ($perRigo[$precedente] ?? [] as $colonna => $campo) {
                    $codice = $prefisso . $colonna;
                    if (isset($perRigo[$prefisso][$colonna]) || (isset($this->catalogo[$codice]) === false)) {
                        continue;
                    }
                    foreach ($rettangoli as $r) {
                        if ((abs($r['x'] - $campo['x']) < 2) && (abs($r['w'] - $campo['larghezza']) < 3) && ($rigoDi($r['y'] + $r['h'] / 2, $r['x'] + 1) === $rigo)) {
                            $copia = ['codice' => $codice, 'x' => round($r['x'], 1), 'y' => round($r['y'], 1), 'altezza' => round($r['h'], 1)] + $campo;
                            $perRigo[$prefisso][$colonna] = $copia;
                            $copiati[] = $copia;
                            break;
                        }
                    }
                    if ($vuoto && (isset($perRigo[$prefisso][$colonna]) === false)) {
                        $spostamento = $etichetta['y'] - $yEtichette[$precedente];
                        if (($spostamento > 0) && ($spostamento < 40)) {
                            $copia = ['codice' => $codice, 'y' => round($campo['y'] + $spostamento, 1)] + $campo;
                            $perRigo[$prefisso][$colonna] = $copia;
                            $copiati[] = $copia;
                        }
                    }
                }
            }
            if (isset($perRigo[$prefisso])) {
                $precedente = $prefisso;
            }
        }
        return $copiati;
    }

    private static function prefisso(string $rigo): string
    {
        return substr($rigo, 0, 2) . sprintf('%03d', (int) substr($rigo, 2));
    }

    /**
     * Rettangolo bianco della casella con il numero di colonna nell'angolo in
     * alto a sinistra: il più largo (il quadratino del solo numero, se c'è,
     * è sovrapposto alla casella).
     *
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private static function casellaDi(array $rettangoli, float $x, float $y): ?array
    {
        $casella = null;
        foreach ($rettangoli as $r) {
            if (($x >= $r['x'] - 1) && ($x <= $r['x'] + 8) && ($y >= $r['y'] - 1.5) && ($y <= $r['y'] + 6) && (($casella === null) || ($r['w'] > $casella['w']))) {
                $casella = $r;
            }
        }
        return $casella;
    }

    /**
     * Numeri della riga numerata più vicina sopra la posizione indicata, con
     * almeno due numeri (intestazione di una tabella).
     *
     * @return list<array{x: float, y: float, x2: float, y2: float, t: string}>
     */
    private function griglia(array $numeri, float $y): array
    {
        $sopra = array_values(array_filter($numeri, fn(array $n) => $n['y'] < $y - 2));
        usort($sopra, fn(array $a, array $b) => $b['y'] <=> $a['y']);
        // righe di numeri dal basso verso l'alto, con 3 punti di tolleranza sulla linea
        while (count($sopra) > 0) {
            $linea = $sopra[0]['y'];
            $riga = array_values(array_filter($sopra, fn(array $n) => abs($n['y'] - $linea) < 3));
            if (count($riga) >= 2) {
                return $riga;
            }
            $sopra = array_values(array_filter($sopra, fn(array $n) => abs($n['y'] - $linea) >= 3));
        }
        return [];
    }

    /**
     * Numeri di colonna: cifre (con una lettera, es. "13A") nei corpi che si
     * ripetono almeno quattro volte nella pagina, esclusi i numeri dentro
     * un testo ("colonne 11, 12, 13, 14 e 15") e le aliquote stampate dopo
     * il ",00" dell'imponibile, sulla stessa linea (righi VE1-VE23 e
     * VF1-VF13 del modello IVA).
     *
     * @return list<array{x: float, y: float, x2: float, y2: float, t: string}>
     */
    private function numeriColonna(array $parole): array
    {
        $cifre = array_values(array_filter($parole, fn(array $p) => (preg_match('~^\d{1,2}[A-Z]?$~', $p['t']) === 1) && (($p['y2'] - $p['y']) < 6.5) && ($p['x'] > 100)));
        $corpi = array_count_values(array_map(fn(array $p) => (string) round($p['y2'] - $p['y'], 1), $cifre));
        $corpi = array_map('floatval', array_keys(array_filter($corpi, fn(int $volte) => $volte >= 3)));
        return array_values(array_filter($cifre, function (array $cifra) use ($corpi, $parole): bool {
            $corpo = $cifra['y2'] - $cifra['y'];
            if (count(array_filter($corpi, fn(float $c) => abs($corpo - $c) <= 0.15)) === 0) {
                return false;
            }
            foreach ($parole as $parola) {
                if (($parola !== $cifra) && (abs($parola['y'] - $cifra['y']) < 1) && ($parola['x2'] <= $cifra['x']) && ($cifra['x'] - $parola['x2'] < 4)
                        && in_array($parola['t'], ['e', 'ed', 'a', 'da', 'col.', 'colonna', 'colonne', 'Art.', 'art.'], true)) {
                    return false;
                }
                if (($parola['t'] === ',00') && ($parola['x2'] <= $cifra['x']) && ($cifra['x'] - $parola['x2'] < 9)
                        && (abs((($parola['y'] + $parola['y2']) / 2) - (($cifra['y'] + $cifra['y2']) / 2)) < 1.2)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Rifinitura delle posizioni di una pagina, anche di quelle corrette a
     * mano, sulla grafica e sui testi della pagina:
     *
     * - gli importi finiscono al ",00" stampato nella casella (o subito
     *   sotto, nelle caselle con il numero di colonna sopra l'importo) e ne
     *   prendono la linea di base; nei modelli con i centesimi la parte
     *   intera finisce alla virgola stampata ("virgola") e i centesimi
     *   vanno nelle due celle dopo;
     * - le caselle divise da tacche verticali (codici fiscali, partite IVA,
     *   codici attività, date) prendono i bordi delle celle, per scrivere un
     *   carattere per cella.
     *
     * @param list<array{codice: string, x: float, y: float, larghezza: float, altezza: float, celle: list<float>, destra: bool}> $campi
     * @return list<array{codice: string, x: float, y: float, larghezza: float, altezza: float, celle: list<float>, destra: bool}>
     */
    public function rifinisci(string $pdf, int $pagina, array $campi): array
    {
        [$svg, $html] = $this->estrai($pdf, $pagina);
        $tacche = $this->leggiGrafica($svg)[2];
        $decimali = self::decimali($this->leggiParole($html));
        foreach ($campi as &$campo) {
            $campo['virgola'] ??= 0.0;
            $decimale = str_starts_with($campo['codice'], '#') ? null : self::decimaleDi($decimali, $campo);
            if ($decimale === null) {
                $celle = self::celleDi($tacche, $campo);
                if (count($celle) > 0) {
                    $campo['celle'] = $celle;
                }
                continue;
            }
            $campo['altezza'] = min($campo['altezza'], self::ALTEZZA_CASELLA);
            $campo['y'] = round($decimale['y2'] + 1.5 - $campo['altezza'], 1);
            $campo['destra'] = true;
            if ($decimale['t'] === ',00') {
                $campo['larghezza'] = round(min($campo['larghezza'], $decimale['x'] - $campo['x'] - 0.5), 1);
                continue;
            }
            // importi con i centesimi (modello IVA TR): la virgola stampata, poi due celle divise da una tacca
            $tacca = current(array_filter($tacche, fn(array $t) => ($t['x'] > $decimale['x2']) && ($t['x'] < $decimale['x'] + 20)
                    && ($t['y'] < $decimale['y2'] + 2) && ($t['y'] + $t['h'] > $decimale['y'] - 2)));
            $centesimo = $tacca === false ? 13.5 : $tacca['x'] - $decimale['x'];
            $campo['virgola'] = round($decimale['x'], 1);
            $campo['celle'] = [round($decimale['x'], 1), round($decimale['x'] + $centesimo, 1), round($decimale['x'] + (2 * $centesimo), 1)];
            $campo['larghezza'] = round($decimale['x'] + (2 * $centesimo) - $campo['x'], 1);
        }
        return $campi;
    }

    /**
     * Fine stampata degli importi: il ",00" o, nei modelli con i centesimi
     * (IVA TR), la virgola da sola.
     *
     * @return list<array{x: float, y: float, x2: float, y2: float, t: string}>
     */
    private static function decimali(array $parole): array
    {
        $decimali = array_values(array_filter($parole, fn(array $p) => $p['t'] === ',00'));
        return count($decimali) > 0 ? $decimali : array_values(array_filter($parole, fn(array $p) => $p['t'] === ','));
    }

    /**
     * ",00" della casella: alla sua fine (fino a poco oltre, per le caselle
     * lette fino al numero successivo) e sulla sua linea o subito sotto.
     */
    private static function decimaleDi(array $decimali, array $campo): ?array
    {
        $fine = $campo['x'] + $campo['larghezza'];
        $scelto = null;
        $distanza = null;
        foreach ($decimali as $decimale) {
            $centro = ($decimale['y'] + $decimale['y2']) / 2;
            if (($decimale['x'] < $fine - ($decimale['t'] === ',' ? 35 : 15)) || ($decimale['x'] > $fine + 12) || ($decimale['x'] < $campo['x'] + 10)
                    || ($centro < $campo['y'] - 2) || ($centro > $campo['y'] + $campo['altezza'] + 6)) {
                continue;
            }
            // prima il ",00" dentro la casella, poi quello subito sotto
            $scarto = abs($decimale['x'] - $fine) + abs($centro - ($campo['y'] + $campo['altezza'] / 2)) + ($centro > $campo['y'] + $campo['altezza'] ? 100 : 0);
            if (($distanza === null) || ($scarto < $distanza)) {
                $distanza = $scarto;
                $scelto = $decimale;
            }
        }
        return $scelto;
    }

    /**
     * Bordi delle celle di una casella divisa da tacche verticali: la prima
     * e l'ultima cella larghe come le vicine (le tacche non arrivano ai
     * bordi della casella). Solo celle regolari, larghe almeno 8 punti: le
     * tacche che dividono parti diverse di una casella (prefisso e numero
     * del telefono) o di caselle vicine non fanno celle.
     *
     * @return list<float> ascisse dei bordi, vuoto per le caselle senza tacche
     */
    private static function celleDi(array $tacche, array $campo): array
    {
        $xs = [];
        foreach ($tacche as $tacca) {
            if (($tacca['x'] > $campo['x'] + 3) && ($tacca['x'] < $campo['x'] + $campo['larghezza'] - 3) && ($tacca['h'] <= $campo['altezza'] + 6)
                    && (min($tacca['y'] + $tacca['h'], $campo['y'] + $campo['altezza'] + 2) - max($tacca['y'], $campo['y'] - 2) > 2)) {
                $xs[] = $tacca['x'];
            }
        }
        sort($xs);
        $bordi = [];
        foreach ($xs as $x) {
            if ((count($bordi) === 0) || ($x - $bordi[count($bordi) - 1] > 2)) {
                $bordi[] = $x;
            }
        }
        if (count($bordi) === 0) {
            return [];
        }
        $inizio = count($bordi) > 1 ? max($campo['x'] - 2, (2 * $bordi[0]) - $bordi[1]) : $campo['x'];
        $fine = count($bordi) > 1 ? min($campo['x'] + $campo['larghezza'] + 2, (2 * $bordi[count($bordi) - 1]) - $bordi[count($bordi) - 2]) : $campo['x'] + $campo['larghezza'];
        $bordi = [$inizio, ...$bordi, $fine];
        $larghezze = [];
        for ($i = 1; $i < count($bordi); $i++) {
            $larghezze[] = $bordi[$i] - $bordi[$i - 1];
        }
        if ((min($larghezze) < 8) || (max($larghezze) > 1.25 * min($larghezze))) {
            return [];
        }
        return array_map(fn(float $x) => round($x, 1), $bordi);
    }

    private static function codice(string $rigo, ?string $colonna): string
    {
        $numeroColonna = $colonna === null ? '001' : (ctype_digit($colonna) ? sprintf('%03d', (int) $colonna) : str_pad($colonna, 3, '0', STR_PAD_LEFT));
        return substr($rigo, 0, 2) . sprintf('%03d', (int) substr($rigo, 2)) . $numeroColonna;
    }

    /**
     * Caselle del codice fiscale (16 celle dopo la scritta "CODICE FISCALE")
     * e del numero di modulo (2 celle dopo "Mod. N.") dalle tacche
     * verticali in testa alla pagina.
     *
     * @return list<array{codice: string, x: float, y: float, larghezza: float, altezza: float, celle: list<float>, destra: bool}>
     */
    private function leggiIntestazione(array $parole, array $tacche): array
    {
        $codiceFiscale = current(array_filter($parole, fn(array $p) => (strtoupper($p['t']) === 'CODICE') && ($p['y'] < 140)));
        $modulo = current(array_filter($parole, fn(array $p) => ($p['t'] === 'Mod.') && ($p['y'] < 140)));
        $campi = [];
        foreach ([[$codiceFiscale, 16, '#CF', $modulo], [$modulo, 2, '#MODULO', false]] as [$parola, $celle, $codice, $limite]) {
            if ($parola === false) {
                continue;
            }
            // tacche alla destra della scritta (codice fiscale: anche sotto), fino alla scritta "Mod."
            $fino = (($limite !== false) && ($limite['x'] > $parola['x']) && (abs($limite['y'] - $parola['y']) < 15)) ? $limite['x'] : self::LARGHEZZA;
            $vicine = array_values(array_filter($tacche, fn(array $t) => ($t['x'] > $parola['x'] - 2) && ($t['x'] < $fino) && ($t['y'] < $parola['y'] + 40) && ($t['y'] + $t['h'] > $parola['y'] - 10)));
            $xs = array_values(array_unique(array_column($vicine, 'x')));
            sort($xs);
            if (count($xs) < 2) {
                continue;
            }
            // celle separate dalle tacche, oppure un riquadro unico tra la prima e l'ultima tacca
            $diviso = count($xs) >= $celle + 1;
            $xs = $diviso ? array_slice($xs, 0, $celle + 1) : [$xs[0], $xs[count($xs) - 1]];
            $ultima = $xs[count($xs) - 1];
            $tacca = current(array_filter($vicine, fn(array $t) => $t['x'] === $xs[0]));
            $campi[] = ['codice' => $codice, 'x' => round($xs[0], 1), 'y' => round($tacca['y'], 1), 'larghezza' => round($ultima - $xs[0], 1), 'altezza' => round($tacca['h'], 1),
                'celle' => $diviso ? array_map(fn(float $x) => round($x, 1), $xs) : [], 'destra' => false];
        }
        return $campi;
    }
}
