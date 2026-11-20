<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

/**
 * Campi del modello IRAP (fornitura XML) dalle specifiche tecniche:
 * la "rappresentazione tabellare" (paragrafo 4, testo di "pdftotext
 * -layout") elenca, nell'ordine dello schema, il nome del tag XML, il
 * codice del campo del modello (es. "IC001001", RicaviVendite) e il tipo;
 * il percorso XML di ogni codice si ricava allineando l'elenco alle foglie
 * dello schema XSD sotto DatiContabili, nello stesso ordine. Le righe del
 * modello con più occorrenze (es. le altre variazioni in aumento) hanno un
 * codice per occorrenza: il percorso ne indica il numero ("[2]").
 *
 * Il nome del tag può andare a capo sulla riga precedente il codice, o il
 * codice sulla riga successiva al tag e al tipo; i
 * codici ripetuti (primo modulo e moduli successivi) valgono una volta, per
 * il primo modulo.
 */
class LettoreSpecificheIrap
{

    /** Formati del catalogo per i tipi dello schema */
    private const FORMATI = ['DatoNP_Type' => 'NP', 'DatoNU_Type' => 'NU', 'DatoCB_Type' => 'CB', 'DatoAN_Type' => 'AN', 'DatoCF_Type' => 'CF', 'DatoCN_Type' => 'CN',
        'DatoDT_Type' => 'DT', 'DatoPC_Type' => 'PC', 'DatoN2_Type' => 'N2', 'DatoN1_Type' => 'N1', 'DatoN3_Type' => 'N3', 'DatoN4_Type' => 'N4', 'DatoDA_Type' => 'DA',
        'Codice_Regione_Type' => 'N2', 'anonimo' => 'N2'];

    /** @var list<array{0: string, 1: string}> codici non trovati nello schema (tag, codice) */
    private array $nonTrovati = [];

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function getNonTrovati(): array
    {
        return $this->nonTrovati;
    }

    /**
     * @param string $testo specifiche ("pdftotext -layout")
     * @return array<string, array{formato: string, descrizione: string, percorso: string}> codice => campo, nell'ordine delle specifiche
     */
    public function leggi(string $testo, SchemaXsd $schema, string $namespace): array
    {
        $dichiarazione = $schema->getElementoGlobale($namespace, 'Dichiarazione') ?? throw new \RuntimeException('Elemento Dichiarazione non trovato nello schema IRAP');
        $datiContabili = null;
        foreach ($schema->getFigli($schema->getTipo($dichiarazione)) as ['dichiarazione' => $figlio]) {
            if ($figlio->getAttribute('name') === 'DatiContabili') {
                $datiContabili = $figlio;
            }
        }
        $foglie = $schema->getFoglie($datiContabili ?? throw new \RuntimeException('Elemento DatiContabili non trovato nello schema IRAP'), 'DatiContabili');
        return $this->allinea($this->leggiCodici($testo), $foglie);
    }

    /**
     * Righe "tag codice tipo" della rappresentazione tabellare, in ordine.
     *
     * @return list<array{0: string, 1: string, 2: string}> [tag, codice, tipo]
     */
    public function leggiCodici(string $testo): array
    {
        $righe = explode("\n", $testo);
        $inizio = 0;
        foreach ($righe as $numero => $riga) {
            if (str_starts_with($riga, 'DatiContabili')) {
                $inizio = $numero;
                break;
            }
        }
        $righe = array_slice($righe, $inizio);
        $codici = [];
        foreach ($righe as $indice => $riga) {
            if (preg_match('/^\s*([A-Z][A-Za-z0-9_]+)\s+([A-Z]{2}\d{3}[0-9A-Z]{3})\s+([A-Z][A-Za-z0-9_]*_Type|[A-Z][A-Za-z0-9]*)\b/', $riga, $parti) === 1) {
                $codici[] = [$parti[1], $parti[2], $parti[3]];
                continue;
            }
            // tag e tipo sulla riga, codice da solo sulla riga successiva (nella colonna dei codici)
            if ((preg_match('/^\s*([A-Z][A-Za-z0-9_]+)\s+([A-Z][A-Za-z0-9_]*_Type)\b/', $riga, $parti) === 1)
                    && (preg_match('/^\s{30,70}([A-Z]{2}\d{3}[0-9A-Z]{3})\s*$/', $righe[$indice + 1] ?? '', $codice) === 1)) {
                $codici[] = [$parti[1], $codice[1], $parti[2]];
                continue;
            }
            if (preg_match('/^\s+([A-Z]{2}\d{3}[0-9A-Z]{3})\s+([A-Z][A-Za-z0-9_]*_Type)/', $riga, $parti) === 1) {
                // tag andato a capo: sulla riga precedente, seguito da molti spazi
                for ($precedente = $indice - 1; $precedente >= max(0, $indice - 3); $precedente--) {
                    if (preg_match('/^ {1,40}([A-Z][A-Za-z0-9_]+)(?: {3,}.*)?$/', $righe[$precedente], $tag) === 1) {
                        $codici[] = [$tag[1], $parti[1], $parti[2]];
                        break;
                    }
                }
            }
        }
        return $codici;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $codici
     * @param list<array{percorso: string, tipo: string}> $foglie
     * @return array<string, array{formato: string, descrizione: string, percorso: string}>
     */
    public function allinea(array $codici, array $foglie): array
    {
        $this->nonTrovati = [];
        $ultimo = fn(string $percorso) => substr($percorso, strrpos($percorso, '/') + 1);
        $gruppo = function (string $percorso): ?string {
            $parti = explode('/', $percorso);
            for ($i = count($parti) - 1; $i >= 0; $i--) {
                if (str_ends_with($parti[$i], '[]') && !str_starts_with($parti[$i], 'Modulo')) {
                    return implode('/', array_slice($parti, 0, $i + 1));
                }
            }
            return null;
        };
        $assegnate = $occorrenze = $puntatore = $campi = [];
        $ultimoGruppo = null;
        foreach ($codici as [$tag, $codice, $tipo]) {
            if (isset($campi[$codice])) {
                continue;
            }
            $quadro = 'Quadro' . substr($codice, 0, 2);
            $candidati = [];
            foreach ($foglie as $indice => $foglia) {
                if (($ultimo($foglia['percorso']) === $tag) && str_contains($foglia['percorso'], '/' . $quadro . '/')) {
                    $candidati[] = $indice;
                }
            }
            if (count($candidati) === 0) {
                $this->nonTrovati[] = [$tag, $codice];
                continue;
            }
            $da = $puntatore[$quadro] ?? 0;
            $scelta = null;
            // nel gruppo ripetuto dell'ultimo codice, se il tag ne fa parte
            if ($ultimoGruppo !== null) {
                foreach ($candidati as $indice) {
                    if ($gruppo($foglie[$indice]['percorso']) === $ultimoGruppo) {
                        $scelta = $indice;
                        break;
                    }
                }
            }
            // foglia non ancora usata dopo l'ultima assegnata, poi qualunque non usata
            if ($scelta === null) {
                $libere = array_values(array_filter($candidati, fn(int $indice) => !isset($assegnate[$indice])));
                $dopo = array_values(array_filter($libere, fn(int $indice) => $indice >= $da));
                $scelta = $dopo[0] ?? $libere[0] ?? null;
            }
            // nuova occorrenza di un gruppo ripetuto
            if ($scelta === null) {
                $ripetute = array_values(array_filter($candidati, fn(int $indice) => $gruppo($foglie[$indice]['percorso']) !== null));
                if (count($ripetute) === 0) {
                    $this->nonTrovati[] = [$tag, $codice];
                    continue;
                }
                usort($ripetute, fn(int $a, int $b) => abs($a - $da) <=> abs($b - $da));
                $scelta = $ripetute[0];
            }
            $percorso = $foglie[$scelta]['percorso'];
            $chiave = $gruppo($percorso);
            if ($chiave !== null) {
                $stato = $occorrenze[$chiave] ?? ['numero' => 1, 'usati' => []];
                if (isset($stato['usati'][$tag])) {
                    $stato = ['numero' => $stato['numero'] + 1, 'usati' => []];
                }
                $stato['usati'][$tag] = true;
                $occorrenze[$chiave] = $stato;
                $percorso = substr($chiave, 0, -2) . '[' . $stato['numero'] . ']' . substr($percorso, strlen($chiave));
            }
            $ultimoGruppo = $chiave;
            $assegnate[$scelta] = true;
            $puntatore[$quadro] = $scelta + 1;
            $campi[$codice] = ['formato' => self::FORMATI[$foglie[$scelta]['tipo']] ?? 'AN', 'descrizione' => self::getDescrizione($percorso), 'percorso' => $percorso];
        }
        return $campi;
    }

    /**
     * Descrizione dal percorso: nome del tag e del gruppo che lo contiene,
     * con le parole separate (es. "Ricavi vendite (componenti positivi)").
     */
    public static function getDescrizione(string $percorso): string
    {
        $parti = array_values(array_filter(explode('/', preg_replace('/\[\d*\]/', '', $percorso)), fn(string $parte) => !in_array($parte, ['DatiContabili', 'PrimoModulo', 'Modulo'], true) && !str_starts_with($parte, 'Quadro')));
        $parole = fn(string $nome) => mb_strtolower(trim(preg_replace('/(?<=[a-z0-9])(?=[A-Z])|_/', ' ', $nome)));
        $tag = $parole((string) end($parti));
        $descrizione = mb_strtoupper(mb_substr($tag, 0, 1)) . mb_substr($tag, 1);
        if (count($parti) > 1) {
            $descrizione .= ' (' . $parole($parti[count($parti) - 2]) . ')';
        }
        return mb_substr($descrizione, 0, 500);
    }
}
