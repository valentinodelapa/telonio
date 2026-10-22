<?php

namespace Anagrafica\Application\Services\TabelleRiferimento;

/**
 * Lettura dell'archivio storico dei comuni ANPR (Ministero dell'Interno,
 * tabella 03 "ANPR Archivio comuni", CSV): una riga per ogni versione del
 * comune, con codice catastale attribuito dall'Agenzia delle Entrate e date
 * di istituzione/cessazione.
 *
 * @see https://www.anagrafenazionale.interno.it/area-tecnica/tabelle-di-decodifica/
 */
class ArchivioComuniAnpr
{

    public const URL = 'https://www.anagrafenazionale.interno.it/wp-content/uploads/ANPR_archivio_comuni.csv';
    public const COLONNE = ['id_anpr', 'codice_catastale', 'codice_istat', 'denominazione', 'sigla_provincia', 'valido_dal', 'valido_al'];
    private const DATA_APERTA = '9999-12-31';
    private const MINIMO_COMUNI_VIGENTI = 7000;
    /**
     * Comuni soppressi prima dell'attribuzione dei codici catastali: non
     * possono comparire in un codice fiscale e vengono esclusi.
     */
    private const CODICE_NON_DISPONIBILE = 'ND';

    private int $escluse = 0;

    /**
     * @return array<int, array<string, string|int|null>> righe indicizzate per id_anpr
     */
    public function leggi(string $percorso): array
    {
        $file = fopen($percorso, 'r') ?: throw new \RuntimeException('Impossibile aprire ' . $percorso);
        $intestazione = array_map(fn($colonna) => trim($colonna, "\u{FEFF} "), fgetcsv($file, escape: ''));
        $this->verificaIntestazione($intestazione);
        $righe = [];
        $vigenti = 0;
        $this->escluse = 0;
        while (($valori = fgetcsv($file, escape: '')) !== false) {
            if ($valori === [null]) {
                continue;
            }
            $campi = array_combine($intestazione, $valori);
            $riga = [
                'id_anpr' => (int) $campi['ID'],
                'codice_catastale' => strtoupper(trim($campi['CODCATASTALE'])),
                'codice_istat' => self::valoreONull($campi['CODISTAT']),
                'denominazione' => trim($campi['DENOMINAZIONE_IT']),
                'sigla_provincia' => self::valoreONull($campi['SIGLAPROVINCIA']),
                'valido_dal' => $campi['DATAISTITUZIONE'],
                'valido_al' => $campi['DATACESSAZIONE'] === self::DATA_APERTA ? null : $campi['DATACESSAZIONE'],
            ];
            if ($riga['codice_catastale'] === self::CODICE_NON_DISPONIBILE) {
                $this->escluse++;
                continue;
            }
            if ((preg_match('/^[A-Z]\d{3}$/', $riga['codice_catastale']) !== 1) || ($riga['denominazione'] === '')) {
                throw new \UnexpectedValueException('Riga non valida nell\'archivio comuni: ' . implode(',', $valori));
            }
            $vigenti += $riga['valido_al'] === null ? 1 : 0;
            $righe[$riga['id_anpr']] = $riga;
        }
        fclose($file);
        if ($vigenti < self::MINIMO_COMUNI_VIGENTI) {
            throw new \UnexpectedValueException('Archivio comuni incompleto: solo ' . $vigenti . ' comuni vigenti.');
        }
        return $righe;
    }

    /**
     * Righe escluse dall'ultima lettura perché prive di codice catastale.
     */
    public function getEscluse(): int
    {
        return $this->escluse;
    }

    private function verificaIntestazione(array $intestazione): void
    {
        $mancanti = array_diff(['ID', 'DATAISTITUZIONE', 'DATACESSAZIONE', 'CODISTAT', 'CODCATASTALE', 'DENOMINAZIONE_IT', 'SIGLAPROVINCIA'], $intestazione);
        if (count($mancanti) > 0) {
            throw new \UnexpectedValueException('Tracciato dell\'archivio comuni cambiato, colonne mancanti: ' . implode(', ', $mancanti));
        }
    }

    private static function valoreONull(?string $valore): ?string
    {
        $valore = trim((string) $valore);
        return $valore === '' ? null : $valore;
    }
}
