<?php

namespace Dichiarativi\Application\Services\Telematico;

use Dichiarativi\Application\Services\TabelleRiferimento\SchemaXsd;

/**
 * Scrittura di un documento XML nell'ordine e nei namespace dello schema
 * XSD: i valori sono un albero per nome di elemento (gli attributi con la
 * chiave "@nome", gli elementi ripetuti come liste); di ogni elemento
 * complesso si scrivono, nell'ordine dello schema, i figli che hanno un
 * valore, ciascuno nel namespace dello schema in cui è dichiarato.
 * Valori null, stringhe vuote e rami senza valori non si scrivono.
 */
class ScritturaXml
{

    /** @var array<string, string> prefissi per namespace */
    private array $prefissi;

    /**
     * @param array<string, string> $prefissi namespace => prefisso
     */
    public function __construct(private SchemaXsd $schema, array $prefissi)
    {
        $this->prefissi = $prefissi;
    }

    /**
     * Scrive l'elemento dichiarato con i valori sotto il nodo padre.
     *
     * @param array<string, mixed>|string|int $valore
     * @throws \InvalidArgumentException con valori per elementi che lo schema non prevede
     */
    public function scrivi(\DOMNode $padre, \DOMElement $dichiarazione, array|string|int $valore): void
    {
        $dichiarazione = $this->schema->getDichiarazione($dichiarazione);
        $documento = $padre instanceof \DOMDocument ? $padre : $padre->ownerDocument;
        $namespace = SchemaXsd::getNamespace($dichiarazione);
        $nome = $dichiarazione->getAttribute('name');
        $elemento = $documento->createElementNS($namespace, ($this->prefissi[$namespace] ?? 'ns') . ':' . $nome);
        if (!is_array($valore)) {
            $elemento->appendChild($documento->createTextNode((string) $valore));
            $padre->appendChild($elemento);
            return;
        }
        $padre->appendChild($elemento);
        foreach ($valore as $chiave => $attributo) {
            if (str_starts_with((string) $chiave, '@')) {
                $elemento->setAttribute(substr($chiave, 1), (string) $attributo);
            }
        }
        $previsti = [];
        foreach ($this->schema->getFigli($this->schema->getTipo($dichiarazione)) as ['dichiarazione' => $figlio]) {
            $nomeFiglio = $figlio->getAttribute('name');
            $previsti[$nomeFiglio] = true;
            if (!array_key_exists($nomeFiglio, $valore) || self::isVuoto($valore[$nomeFiglio])) {
                continue;
            }
            $valori = is_array($valore[$nomeFiglio]) && array_is_list($valore[$nomeFiglio]) ? $valore[$nomeFiglio] : [$valore[$nomeFiglio]];
            foreach ($valori as $singolo) {
                if (!self::isVuoto($singolo)) {
                    $this->scrivi($elemento, $figlio, $singolo);
                }
            }
        }
        $estranei = array_filter(array_keys($valore), fn($chiave) => !str_starts_with((string) $chiave, '@') && !isset($previsti[$chiave]));
        if (count($estranei) > 0) {
            throw new \InvalidArgumentException(sprintf('Elementi non previsti dallo schema in %s: %s.', $nome, implode(', ', $estranei)));
        }
    }

    private static function isVuoto(mixed $valore): bool
    {
        if (is_array($valore)) {
            foreach ($valore as $chiave => $figlio) {
                if (!str_starts_with((string) $chiave, '@') && !self::isVuoto($figlio)) {
                    return false;
                }
            }
            return true;
        }
        return ($valore === null) || ($valore === '');
    }
}
