<?php

namespace Deleghe\Application\Services\TabelleRiferimento;

use Deleghe\Application\Enumerations\TabellaF24;

/**
 * Tabella dei codici degli uffici finanziari e delle direzioni regionali e
 * provinciali (codice ufficio della sezione Erario): non è nell'archivio
 * delle tabelle F24, si legge dalla pagina del servizio "Ricerca codici
 * tributo" dell'Agenzia delle Entrate.
 */
class UfficiAgenzia
{

    private const PAGINA = 'VisualizzaTabella.php';
    private const MINIMO = 300;

    public function __construct(
            private FonteAgenzia $fonte = new FonteAgenzia(),
            private LettoreTabellaHtml $lettore = new LettoreTabellaHtml(),
    ) {

    }

    /**
     * @return array<array<string, string|int|null>> righe per la tabella codice_tabella_f24
     */
    public function leggi(): array
    {
        $html = $this->fonte->leggi(self::PAGINA, ['ArcName' => 'UFFICI']);
        $uffici = [];
        foreach ($this->lettore->leggi($html, ['Settore', 'Codice', 'Descrizione'], 'tabella degli uffici') as $campi) {
            if (preg_match('/^[A-Z0-9]{3}$/', $campi['Codice']) === 1) {
                $uffici[$campi['Codice']] = ['tabella' => TabellaF24::uffici->value, 'codice' => $campi['Codice'], 'descrizione' => mb_substr($campi['Descrizione'], 0, 255), 'dati' => $campi['Settore'], 'in_uso' => 1];
            }
        }
        if (count($uffici) < self::MINIMO) {
            throw new \UnexpectedValueException('Tabella degli uffici incompleta: ' . count($uffici) . ' codici.');
        }
        return array_values($uffici);
    }
}
