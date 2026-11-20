<?php

namespace Dichiarativi\Application\Services\TabelleRiferimento;

/**
 * Schema XSD di una fornitura XML dell'Agenzia delle Entrate (cartella con
 * i file .xsd, anche in sottocartelle): tipi complessi e semplici ed
 * elementi globali per namespace, figli di un tipo nell'ordine dello schema
 * (con le estensioni e i riferimenti risolti) e namespace di ogni elemento,
 * che è quello dello schema in cui l'elemento è dichiarato
 * (elementFormDefault "qualified").
 *
 * Serve a leggere i campi delle specifiche IRAP (foglie sotto
 * DatiContabili) e a scrivere il file XML nell'ordine dello schema.
 */
class SchemaXsd
{

    public const XS = 'http://www.w3.org/2001/XMLSchema';

    /** @var array<string, \DOMElement> tipi per "namespace|nome" */
    private array $tipi = [];

    /** @var array<string, \DOMElement> elementi globali per "namespace|nome" */
    private array $elementi = [];

    public function __construct(string $cartella)
    {
        $file = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cartella, \FilesystemIterator::SKIP_DOTS));
        foreach ($file as $percorso) {
            if (strtolower($percorso->getExtension()) !== 'xsd') {
                continue;
            }
            $documento = new \DOMDocument();
            if (@$documento->load($percorso->getPathname()) === false) {
                throw new \RuntimeException('Schema XSD non leggibile: ' . $percorso->getFilename());
            }
            $schema = $documento->documentElement;
            $namespace = $schema->getAttribute('targetNamespace');
            foreach ($schema->childNodes as $nodo) {
                if (!$nodo instanceof \DOMElement) {
                    continue;
                }
                $nome = $nodo->getAttribute('name');
                if (in_array($nodo->localName, ['complexType', 'simpleType'], true) && ($nome !== '')) {
                    $this->tipi[$namespace . '|' . $nome] ??= $nodo;
                } elseif (($nodo->localName === 'element') && ($nome !== '')) {
                    $this->elementi[$namespace . '|' . $nome] ??= $nodo;
                }
            }
        }
        if (count($this->tipi) === 0) {
            throw new \RuntimeException('Nessuno schema XSD in ' . $cartella);
        }
    }

    /**
     * Namespace dello schema in cui è dichiarato il nodo.
     */
    public static function getNamespace(\DOMElement $nodo): string
    {
        return $nodo->ownerDocument->documentElement->getAttribute('targetNamespace');
    }

    /**
     * Risolve un nome qualificato ("ir:Dati_Contabili_IRAP_Type") nel
     * namespace del prefisso, dichiarato nello schema del nodo.
     */
    private static function risolvi(\DOMElement $nodo, string $qname): string
    {
        [$prefisso, $nome] = str_contains($qname, ':') ? explode(':', $qname, 2) : [null, $qname];
        $namespace = $nodo->lookupNamespaceURI($prefisso) ?? '';
        return $namespace . '|' . $nome;
    }

    public function getElementoGlobale(string $namespace, string $nome): ?\DOMElement
    {
        return $this->elementi[$namespace . '|' . $nome] ?? null;
    }

    /**
     * Tipo complesso o semplice dell'elemento (con nome o anonimo); null per
     * i tipi predefiniti dello schema XML.
     */
    public function getTipo(\DOMElement $elemento): ?\DOMElement
    {
        $tipo = $elemento->getAttribute('type');
        if ($tipo !== '') {
            return $this->tipi[self::risolvi($elemento, $tipo)] ?? null;
        }
        foreach ($elemento->childNodes as $figlio) {
            if (($figlio instanceof \DOMElement) && in_array($figlio->localName, ['complexType', 'simpleType'], true)) {
                return $figlio;
            }
        }
        return null;
    }

    /**
     * Nome del tipo dell'elemento (senza prefisso), "anonimo" senza nome.
     */
    public static function getNomeTipo(\DOMElement $elemento): string
    {
        $tipo = $elemento->getAttribute('type');
        return $tipo === '' ? 'anonimo' : (str_contains($tipo, ':') ? explode(':', $tipo, 2)[1] : $tipo);
    }

    /**
     * Dichiarazione effettiva dell'elemento (risolve "ref").
     */
    public function getDichiarazione(\DOMElement $elemento): \DOMElement
    {
        $ref = $elemento->getAttribute('ref');
        if ($ref === '') {
            return $elemento;
        }
        return $this->elementi[self::risolvi($elemento, $ref)] ?? throw new \RuntimeException('Elemento di riferimento non trovato nello schema: ' . $ref);
    }

    /**
     * Elementi figli di un tipo complesso, nell'ordine dello schema, con le
     * estensioni del tipo base prima di quelle proprie.
     *
     * @return list<array{dichiarazione: \DOMElement, ripetibile: bool}>
     */
    public function getFigli(?\DOMElement $tipo): array
    {
        if (($tipo === null) || ($tipo->localName !== 'complexType')) {
            return [];
        }
        $figli = [];
        $visita = function (\DOMElement $nodo) use (&$visita, &$figli): void {
            foreach ($nodo->childNodes as $figlio) {
                if (!$figlio instanceof \DOMElement) {
                    continue;
                }
                if ($figlio->localName === 'element') {
                    $massimo = $figlio->getAttribute('maxOccurs');
                    $figli[] = ['dichiarazione' => $this->getDichiarazione($figlio), 'ripetibile' => ($massimo !== '') && ($massimo !== '1')];
                } elseif (in_array($figlio->localName, ['sequence', 'choice', 'all', 'complexContent'], true)) {
                    $visita($figlio);
                } elseif ($figlio->localName === 'extension') {
                    $base = $this->tipi[self::risolvi($figlio, $figlio->getAttribute('base'))] ?? null;
                    if (($base !== null) && ($base->localName === 'complexType')) {
                        $visita($base);
                    }
                    $visita($figlio);
                }
            }
        };
        $visita($tipo);
        return $figli;
    }

    /**
     * Foglie (elementi senza figli) sotto l'elemento, in ordine: percorso
     * dei nomi (con "[]" sui gruppi ripetibili) e nome del tipo.
     *
     * @return list<array{percorso: string, tipo: string}>
     */
    public function getFoglie(\DOMElement $elemento, string $percorso = ''): array
    {
        $dichiarazione = $this->getDichiarazione($elemento);
        $figli = $this->getFigli($this->getTipo($dichiarazione));
        if (count($figli) === 0) {
            return [['percorso' => $percorso, 'tipo' => self::getNomeTipo($dichiarazione)]];
        }
        $foglie = [];
        foreach ($figli as ['dichiarazione' => $figlio, 'ripetibile' => $ripetibile]) {
            array_push($foglie, ...$this->getFoglie($figlio, ltrim($percorso . '/' . $figlio->getAttribute('name') . ($ripetibile ? '[]' : ''), '/')));
        }
        return $foglie;
    }
}
