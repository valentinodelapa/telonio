<?php

namespace Anagrafica\Application\Services\TabelleRiferimento;

/**
 * Lettura della corrispondenza tra codici ISO 3166-1 alpha-3 e alpha-2 dalla
 * tabella di autorità "Countries and territories" di EU Vocabularies (Ufficio
 * delle pubblicazioni dell'Unione europea), interrogata con l'endpoint SPARQL
 * pubblico in formato CSV. Serve a ricavare l'alpha-2 degli stati esteri ANPR,
 * che riportano solo l'alpha-3. Sono esclusi i paesi cessati (deprecati).
 *
 * Il Kosovo non ha codici ISO: ANPR usa l'alpha-3 "KOS", a cui si associa il
 * codice "XK" usato dalle istituzioni dell'Unione e dalle specifiche
 * FatturaPA.
 *
 * Riutilizzo: decisione 2011/833/UE della Commissione (CC BY 4.0).
 *
 * @see https://op.europa.eu/web/eu-vocabularies/dataset/-/resource?uri=http://publications.europa.eu/resource/dataset/country
 */
class CodiciIsoUe
{

    public const ENDPOINT = 'https://publications.europa.eu/webapi/rdf/sparql';
    public const QUERY = <<<SPARQL
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX euvoc: <http://publications.europa.eu/ontology/euvoc#>
PREFIX owl: <http://www.w3.org/2002/07/owl#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
SELECT DISTINCT ?alpha3 ?alpha2 WHERE {
  ?paese skos:inScheme <http://publications.europa.eu/resource/authority/country> ;
    euvoc:xlNotation ?notazione3 , ?notazione2 .
  ?notazione3 dct:type <http://publications.europa.eu/resource/authority/notation-type/ISO_3166_1_ALPHA_3> ; rdf:value ?alpha3 .
  ?notazione2 dct:type <http://publications.europa.eu/resource/authority/notation-type/ISO_3166_1_ALPHA_2> ; rdf:value ?alpha2 .
  FILTER NOT EXISTS { ?paese owl:deprecated ?deprecato }
} ORDER BY ?alpha3
SPARQL;
    private const CODICI_NON_ISO = ['KOS' => 'XK'];
    private const MINIMO_CODICI = 240;

    public static function url(): string
    {
        return self::ENDPOINT . '?' . http_build_query(['query' => self::QUERY, 'format' => 'text/csv']);
    }

    /**
     * @return array<string, string> codice alpha-2 indicizzato per alpha-3
     */
    public function leggi(string $percorso): array
    {
        $file = fopen($percorso, 'r') ?: throw new \RuntimeException('Impossibile aprire ' . $percorso);
        $intestazione = array_map(fn($colonna) => trim($colonna, "\u{FEFF} "), fgetcsv($file, escape: '') ?: []);
        if ($intestazione !== ['alpha3', 'alpha2']) {
            fclose($file);
            throw new \UnexpectedValueException('Intestazione inattesa nei codici ISO: ' . implode(', ', $intestazione));
        }
        $codici = [];
        while (($valori = fgetcsv($file, escape: '')) !== false) {
            if ($valori === [null]) {
                continue;
            }
            [$alpha3, $alpha2] = array_map(fn($valore) => strtoupper(trim($valore)), $valori + ['', '']);
            if ((preg_match('/^[A-Z]{3}$/', $alpha3) !== 1) || (preg_match('/^[A-Z]{2}$/', $alpha2) !== 1)) {
                fclose($file);
                throw new \UnexpectedValueException('Riga non valida nei codici ISO: ' . implode(',', $valori));
            }
            $codici[$alpha3] = $alpha2;
        }
        fclose($file);
        if ((count($codici) < self::MINIMO_CODICI) || (($codici['ITA'] ?? null) !== 'IT')) {
            throw new \UnexpectedValueException('Codici ISO incompleti: ' . count($codici) . ' paesi.');
        }
        return $codici + self::CODICI_NON_ISO;
    }
}
