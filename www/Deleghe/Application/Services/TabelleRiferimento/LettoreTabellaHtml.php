<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

/**
 * Lettura della prima tabella di una pagina HTML: righe indicizzate per
 * intestazione di colonna, con verifica delle colonne attese (se il
 * tracciato della fonte cambia l'import si ferma).
 */
class LettoreTabellaHtml
{

    /**
     * @param string[] $colonneRichieste intestazioni obbligatorie
     * @return array<array<string, string>>
     */
    public function leggi(string $html, array $colonneRichieste, string $fonte): array
    {
        $documento = new \DOMDocument();
        // le pagine HTML5 producono avvisi del parser libxml non rilevanti
        $erroriInterni = libxml_use_internal_errors(true);
        $caricato = $documento->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($erroriInterni);
        if ($caricato === false) {
            throw new \UnexpectedValueException('Pagina non leggibile: ' . $fonte);
        }
        $xpath = new \DOMXPath($documento);
        $tabella = $xpath->query('//table')->item(0) ?? throw new \UnexpectedValueException('Tabella non trovata: ' . $fonte);
        $intestazione = array_map(fn(\DOMNode $cella) => self::testo($cella), iterator_to_array($xpath->query('.//thead/tr/th', $tabella)));
        $mancanti = array_diff($colonneRichieste, $intestazione);
        if (count($mancanti) > 0) {
            throw new \UnexpectedValueException('Tracciato di ' . $fonte . ' cambiato, colonne mancanti: ' . implode(', ', $mancanti));
        }
        $righe = [];
        foreach ($xpath->query('.//tbody/tr', $tabella) as $riga) {
            $celle = array_map(fn(\DOMNode $cella) => self::testo($cella), iterator_to_array($xpath->query('./th|./td', $riga)));
            if (count($celle) !== count($intestazione)) {
                throw new \UnexpectedValueException('Riga con ' . count($celle) . ' colonne invece di ' . count($intestazione) . ': ' . $fonte);
            }
            $righe[] = array_combine($intestazione, $celle);
        }
        return $righe;
    }

    private static function testo(\DOMNode $nodo): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{A0}", ' ', $nodo->textContent)));
    }
}
